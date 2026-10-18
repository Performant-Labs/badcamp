# T handoff: trial-0936-mercury-landing

Tester (T), Website Front-End Pipeline, adapter `drupal-canvas-mercury` (unmerged worktree copy). Branch `trial-0936-mercury-landing`, HEAD `9fd5050`, pristine Mercury baseline `3e99366`. Site `https://badcamp.ddev.site:8493`. Headless only (Playwright, chrome-headless-shell). Nothing committed, no product code, theme, recipe or committed file changed. Screenshots are in `T-artifacts/`.

**Overall verdict: FAIL, one blocking item** (T2, light-scheme focus-ring contrast on the two "Donate" buttons, 2.15:1 against the required 3:1). Everything else passes, including the fresh-install reproducibility check. F must address the focus ring; the cycle resumes at A.

| # | Item | Result |
|---|---|---|
| 1 | T1 headless | PASS |
| 2 | T2 structural (axe, headings, landmarks, overflow, focus ring) | **FAIL** (focus ring, light only) |
| 3 | Breakpoint agreement | PASS |
| 4 | `npm run lint:css` | PASS |
| 5 | Fresh-install reproducibility | PASS (restore completed and verified) |

## A precondition

A-review.md: PASS (0 BLOCK, 9 WARN, 3 INFO); F's rework round 1 is on top of baseline `3e99366`. Not BLOCK, so T proceeded.

Working-tree note: `git status` showed one uncommitted change I did not make, `docs/frontend/frontend-pipeline-profile.md` (+1 line, "Chrome route"). Left untouched.

## T1 results (PASS)

| Check | Command / method | Result |
|---|---|---|
| Cache clear | `ddev drush cr` | `[success] Cache rebuild complete.` |
| Serves | `curl -sk -o /dev/null -w '%{http_code}'` | `/perks-trial` 200, `/` 200, `/theme.css` 200, `/fonts.css` 200 |
| Sections and copy | read `<main>` text of served HTML | Eyebrow "OPTIONAL ATTENDEE DONATION"; h1 "Give $100 or more. Get the [Bundle name]." (highlight in a span); sentence; "Donate $100" (`/donate`), "See the six perks" (`#perks`); badge "$100+" / "unlocks 6 perks"; h2 "Where your donation goes" + bracket copy; `id="perks"` x1; h2 "What you get" + intro; 6 cards "Perk 1..6" each with "[Perk title]" h3 and sentence; h2 "Donate and claim your bundle", sentence, "Donate" (`/donate`), two bracket notes. No amount pills. Matches brief. |
| Token override live | grep served HTML | `<link ... href="/theme.css?...">` and `/fonts.css?...` present; `grep -c 'src/theme.css\|src/fonts.css'` = 0 |
| Font URLs | `curl` each `url()` in served `/fonts.css` | `/fonts/{Lexend,Inter,Aleo}/{latin,latin-ext}.woff2`: 6/6 HTTP 200, `font/woff2` (34-85 KB) |
| Fonts actually render | Chromium CDP `CSS.getPlatformFontsForNode` + `document.fonts` | h1 and h2: **Lexend** (39-40 / 23-24 glyphs), body `p`: **Inter** (70-71 glyphs), at 360/768/1280, light and dark. `document.fonts.check('16px Lexend'/'16px Inter', 'Hello World 123')` true; loaded faces declare `U+0-FF...` (latin range). ASCII text is not on a fallback. |
| Build in sync | `npm run build` in `web/themes/custom/mercury` then `git diff --exit-code -- web/themes/custom/mercury/build/` | exit 0, no diff (nothing to restore). `build/` is tracked. |

## T2 results (FAIL on focus ring, light)

### axe

Two runs, so both the pipeline tool and the NFR viewports are covered.

1. The pipeline's own `core/tools/axe-check.cjs` (as shipped: 375 and 1280 only, `wcag2a`/`wcag2aa` only), `AXE_BASE_URL=https://badcamp.ddev.site:8493 NODE_PATH=<repo>/node_modules`, paths `/perks-trial /`, light and dark. Needed a preload shim for the browser path (see adapter finding 1). Result in every run: exactly 1 violation, `[serious] link-name` on the site-chrome logo link `#block-aa3884ff-... > a[href="/"][rel="home"]`, **identical on `/`** (pre-existing stock Mercury `alt=""`, badcamp#10 chrome).
2. My own `@axe-core/playwright` 4.14.0 harness at **360 / 768 / 1280**, tags `wcag2a wcag2aa wcag21a wcag21aa wcag22aa best-practice`, light, dark and fresh install: the same single chrome `link-name`; **0 violations on page content** in all 9 runs. One `incomplete` (needs review): `aria-prohibited-attr` on the hero eyebrow `<span aria-label="OPTIONAL ATTENDEE DO...">`. That markup is stock Mercury `badge.twig` (line 58, unchanged by F); advisory.

Dark scheme: `ddev drush config:set mercury.settings scheme dark -y` + `drush cr` (served `<html class="dark js">` confirmed); restored with `config:set ... light`; `ddev drush config:get mercury.settings scheme` -> `'mercury.settings:scheme': light`. Confirmed again after the snapshot restore.

### Headings, landmarks, overflow (identical light, dark, fresh)

- 360: h1, h2, h2, h3 x6, h2 (no skips, 1 h1). 768/1280: the same plus a site-chrome `h2` "Main navigation" that precedes the h1 in DOM order (stock Mercury navbar; axe `heading-order` does not flag it). Advisory.
- Landmarks: banner 1, main 1, nav 2, contentinfo 1.
- Horizontal overflow: none at 360, 768, 1280 (`scrollWidth == clientWidth`, no element past the viewport).
- Tap targets under 44px: inside `<main>`, none interactive (the only hit is the 0x0 non-interactive `<a id="perks">` anchor target). Under 44px but site chrome (badcamp#10): logo link 214x32 (360) / 248x40, "Show menu" 32x32, "Home" 61x40/69x40.

### Focus ring (measured, 3:1 required) -> FAIL

Method: Tab through every focusable element, wait 500 ms (the buttons have `duration-300`, so a computed-style read taken immediately after Tab sees a transparent ring), screenshot the element's neighbourhood focused and blurred, take the changed pixels as the indicator, and compute WCAG contrast of the indicator colour against the unfocused colour of the adjacent outside pixels.

| Element (in `<main>`) | Light: ring vs adjacent | Dark: ring vs adjacent |
|---|---|---|
| "Donate $100" (hero, primary band) | ring `#3d3d3d` on `#0076a8`: **2.15:1 FAIL** | ring `#242424` on `#0076a8`: 3.07:1 pass |
| "See the six perks" (hero) | ring `#fdd362` on `#0076a8`: 3.53:1 pass | 3.53:1 pass |
| "Donate" (closing panel, primary group) | ring `#3d3d3d` on `#0076a8`: **2.15:1 FAIL** | 3.07:1 pass |

Same numbers at 360, 768 and 1280. The ring is a 3px solid box-shadow plus a 1px border change (`ring-ring/50`, `border-ring` in the markup, but see adapter finding 2: those utilities do not exist, so the colour is the button's own text colour via `currentColor`). Against the button's own yellow fill it is 8.9:1, but WCAG 1.4.11 measures against the adjacent outside colour, which on the blue band is 2.15:1. Evidence: `T-artifacts/ring-focused-donate100.png` / `ring-unfocused-donate100.png`.

Chrome (out of scope, recorded): skip link and logo link use the browser default `auto` ring `#005fcc`: 5.98:1 on white (light) but 2.59:1 on `#242424` (dark logo link). Skip link rows in my raw output (CR 1) are a method artifact: it is revealed only on focus.

## T2.5 interaction results

N/A. The profile's stateful-surface inventory is "none yet"; the page has only anchor links. No new stateful surface introduced.

## Breakpoint agreement (PASS)

| Place | Value |
|---|---|
| `src/main.css` `--breakpoint-md` | not declared (grep: none) -> Tailwind default 48rem. Built `main.min.css` uses `@media (min-width:48rem)` 27x. |
| `components/navbar/navbar.js:27` | `window.matchMedia("(min-width: 48rem)")` |
| Served `/theme.css` line 55 (web-root, overrides `src/theme.css`) | `@media (min-width: 768px)` |
| `src/theme.css` fallback line 54 | `@media (min-width: 768px)` |

All 768px (48rem). Served web-root `theme.css` and the fallback agree.

## Gate (PASS)

`npm run lint:css` from the repo root: stylelint 16.26.1, `.stylelintrc.json`, over `src/main.css`, `components/**/*.tailwind.css`, `web/theme.css`, `web/fonts.css`. **Exit 0**, no findings.

## Fresh-install reproducibility (PASS)

Safety first: `ddev snapshot --name t-pre-fresh` (4.8 MB), plus `ddev export-db` and a tar of `web/sites/default/files` and a copy of `settings.php` in the scratchpad.

How Drupal CMS 2 is installed here: `core.extension` profile is `null`; `drupal_cms_installer` (in `web/profiles/contrib`) is a one-shot installer whose `SiteTemplateForm` defaults to `drupal_cms_starter` when run non-interactively. So:

```
ddev drush site:install drupal_cms_installer -y --account-name=<n> --account-pass=<generated> --account-mail=<m> --site-name="BADCamp fresh test" --site-mail=<m>
```

Output: DROP all tables confirmed, tasks `install_install_profile`, `SiteTemplateForm`, `install_profile_modules`, `install_profile_themes`... `[success] Installation complete.` (27 s, no network needed). Result: `system.theme default: mercury` (the converted `web/themes/custom/mercury`), `system.site page.front: /home`, `canvas_page` only Home (id 1), `/` 200, `/perks-trial` **404** (as expected before the recipe). `settings.php` unchanged.

Apply the page recipe (relative path resolves from `web/`):

```
ddev drush recipe ../recipes/badcamp_pages   ->  "Created content for BADCamp pages recipe." "BADCamp pages applied successfully"
ddev drush cr
```

Verification (the recipe message is not taken as proof):
- `/perks-trial` -> **200**, `/` 200. `path_alias`: `/perks-trial` (from the recipe's exported `path.alias`), `/home`, `/privacy-policy`.
- `canvas_page`: Home (id 1, 2 components), "Perks (trial)" (id 2, uuid `7562db90-6285-4f93-b533-75efa67696ff`, **31 components, 0 NULL and 0 empty `components_component_version`**).
- The `<main>` markup of the fresh page is **byte-identical** to the pre-existing site's `/perks-trial` `<main>` (after stripping asset query hashes): 23,719 bytes each.
- `/theme.css` and `/fonts.css` linked, 0 `src/` links; Lexend/Inter rendered on ASCII (CDP); axe: only the chrome logo `link-name`; no overflow at 360/768/1280.

I did not need to enable any extra module: the recipe's own `install: [canvas, mercury]` suffices, and `badcamp_sponsors` is not required by the page. (`badcamp_sponsors` is **Disabled** in this DB both before and after; the brief's "still enabled" premise does not hold. The module set is identical pre and post: md5 of the sorted enabled non-core list matches.)

**Restore (mandatory, done):**

```
ddev snapshot restore t-pre-fresh   -> restored in 41s
ddev drush cr
```

Post-restore: `/` 200, `/perks-trial` 200, `/theme.css` 200; `canvas_page` Home (1) and Perks (trial) (**id 4**, as before); 31 components, 0 NULL versions; aliases `/home`, `/privacy-policy`, `/perks-trial -> /page/4`; `system.theme default: mercury`; `scheme: light`; enabled non-core module list identical to pre-run; `settings.php` unchanged. One side effect: after the sequence `web/robots.txt` showed modified (the `Sitemap: /sitemap.xml` line gone; `git status` was clean of it right after `site:install`, so it appeared during recipe/cache-rebuild/restore, I did not isolate which). Restored with `git checkout -- web/robots.txt`; a following `ddev drush cr` did not reproduce it. The snapshot `t-pre-fresh` is left in `.ddev/db_snapshots/` (git-ignored); delete with `ddev snapshot --cleanup` or leave it.

## Acceptance criteria / WCAG / mobile

- Brief scope (hero, overlap card, 6-card grid, closing panel, no amount pills, bracket copy kept): PASS (T1).
- Breakpoints mobile-first, 1 col at 360, no overflow at 360/768/1280: PASS. (2-col at `md`, 3 at `lg` is a visual check: S.)
- Reproducible from git: PASS (fresh install).
- Brand fonts self-hosted, no CDN: PASS.
- NFR per-variant contrast + focus (G5): **FAIL** for focus in the light variant (above); contrast per axe passes in both variants. Tap targets (G6): PASS inside `<main>`.
- Performance/SRI/CSS health (G2/G4/G3): not measured by T (no third-party assets; stylelint 0). T3 visual not run (S owns it).

## Blocking issues (F must address)

1. **Light-scheme focus ring on "Donate $100" (hero) and "Donate" (closing panel): 2.15:1 against the blue band, below 3:1.** Cause: Mercury defines no `--color-ring` / `--ring` token, so `ring-ring/50` and `border-ring` generate nothing and the ring falls back to `currentColor`, the button's text colour (`#3d3d3d`). Fix is token-level (adapter L1 + L2: a `--ring` in `web/theme.css`, the same in `src/theme.css`, and `--color-ring: var(--ring)` in `@theme inline`; a ring colour that clears 3:1 on both the blue band and the page background, in `:root` and `.dark`), then re-measure every button in both schemes. The yellow-on-blue "See the six perks" already passes in both.

## Advisory notes

- Hero eyebrow `aria-label` on a `<span>` (axe `aria-prohibited-attr`, needs review): stock Mercury `badge.twig`.
- Site-chrome items (logo `link-name`, `h2` before `h1`, chrome tap targets, dark logo focus ring 2.59:1) are pre-existing and belong to badcamp#10.
- `/donate` still 404 (out of scope).
- Build ran under host Node 26.7 versus `.nvmrc` 24: byte-identical, no diff.

## Adapter findings

1. **`core/tools/axe-check.cjs` is not usable as the adapter describes.** (a) It launches with bare `chromium.launch()`; Playwright 1.64.1 (the pinned dev dependency) wants `chromium_headless_shell-1248`, not in the cache (1243 was), so it throws until `npx playwright install chromium` (a download). I used a `--require` preload shim that injects `executablePath`; the adapter should either say to run the install or give a supported override (an `AXE_CHROMIUM_PATH` env var). (b) Hard-coded viewports are 375 and 1280, but the NFR checklist and brief require **360 / 768 / 1280**. (c) Tags are only `wcag2a`/`wcag2aa`, not 2.1/2.2 or best-practice. (d) No colour-scheme handling: dark needs a manual `drush config:set` toggle and restore, which the adapter should script or document. (e) It exits 1 on the pre-existing chrome `link-name`, so a page-content pass needs a scope option (`include('main')`).
2. **Mercury ships no ring token, and the adapter does not say so.** `ring-ring/50`, `focus-visible:border-ring` are in `button.twig` but `--color-ring` exists neither in stock `src/theme.css`, `src/main.css`, nor the built CSS (checked at `3e99366`: 0 matches). The focus ring is therefore `currentColor` and its contrast depends on the button variant against whatever zone it sits in. The adapter's "contrast and focus in every shipped variant" step needs this, plus the instruction to add `--ring` as a new token (the three-edit rule) when the brand needs one.
3. **A focus-ring contrast check must wait for the transition.** Mercury buttons use `duration-300`; computed style read immediately after Tab is a transparent ring. Add "wait >= 300 ms, then measure; measure against the adjacent outside colour (pixel diff), not the element's own fill" to the T2 instructions. axe cannot see this at all.
4. **Fresh-install procedure is missing from the adapter, and the working one is short.** `ddev drush site:install drupal_cms_installer -y --account-name=... --account-pass=... --site-name=...` installs Starter non-interactively with no network, and the installed theme is the converted same-name `mercury`; then `ddev drush recipe ../recipes/badcamp_pages` (relative to `web/`) and verify the alias returns 200. Document it, plus the safety steps: `ddev snapshot` is DB-only, so also export the DB, tar `web/sites/default/files` and keep `settings.php` (it was unchanged here); and check `git status` afterwards.
5. **Unexplained `web/robots.txt` change** (Sitemap line removed) appeared during the install/restore window, the same file F found rewritten by Composer scaffold. The adapter's "review `git diff` after every Composer step" should become "after any install, recipe or snapshot restore".
6. **The brief's checklist assumed `badcamp_sponsors` was enabled.** It was disabled before and after; the profile should say what a fresh-install check is allowed to assume enabled (nothing beyond `canvas` + `mercury` for this page).
7. **Page ids are not stable** (fresh install: Perks = 2; this DB: 4; F saw 2 -> 3 -> 4): the adapter already says to alias; T's checks should use the alias only, as done here.
8. **Component-version check column name.** The adapter says "`component_version` non-NULL" but the table is `canvas_page__components` and the column is `components_component_version` (grouped per `entity_id`). A one-line query belongs in the adapter: `select entity_id, count(*), sum(components_component_version is null) from canvas_page__components group by entity_id`.

## Re-verification (after F's Rework round 2)

Working tree: HEAD `9fd5050` plus F's uncommitted round 2 (`--ring` in `web/theme.css` and `src/theme.css`, `--color-ring` in `src/main.css`, `ring-ring` instead of `ring-ring/50` in `button.twig` and `badge.twig`, rebuilt CSS). Served `/theme.css` has `--ring: #242424` (`:root`) and `#eeeeee` (`.dark`). No `ring-ring/50` left in the two Twig files. Headless Playwright; screenshots `T-artifacts/T2-{light,dark}-*.png`.

**Overall: PASS (4 of 4).**

| # | Item | Result |
|---|---|---|
| 1 | Focus ring >= 3:1, every focusable in `<main>`, light and dark, 360 and 1280 | PASS |
| 2 | axe on page content, both schemes | PASS |
| 3 | `npm run lint:css` and build in sync | PASS |
| 4 | Scheme restored to light | PASS |

### 1. Focus ring (PASS)

Method as before: Tab to the element, wait 500 ms (buttons have `duration-300`), diff the focused vs blurred pixels around the element, and compare the ring colour with the unfocused colour of the adjacent outside pixels. `:focus-visible` matched for every element. Focusable elements inside `<main>`: exactly three links (`main a[href], button, input, select, textarea, summary, [tabindex], [contenteditable]`; the only other hit is the `<a id="perks">` anchor target, `tabindex=-1`, not tabbable).

| Element | Light 360 | Light 1280 | Dark 360 | Dark 1280 |
|---|---|---|---|---|
| "Donate $100" (hero) | `#242424` on `#0076a8` **3.07:1** | 3.07:1 | `#eeeeee` on `#0076a8` **4.35:1** | 4.35:1 |
| "See the six perks" (hero) | 3.07:1 (was 3.53 yellow) | 3.07:1 | 4.35:1 | 4.35:1 |
| "Donate" (closing panel) | 3.07:1 | 3.07:1 | 4.35:1 | 4.35:1 |

All 12 pass; 768 gives the same values. The solid 3px ring colour is exactly the token. Share of changed outside ring pixels at or above 3:1 is 78 to 93%; the rest are anti-aliased edge pixels on the pill, not ring colour. The light-scheme 3.07 is a thin margin over 3.0 (the margin is set by `#242424` against the mid-tone `#0076a8`), so any future change to either colour needs a re-measure.

Out of scope, unchanged (badcamp#10 chrome): the default `#005fcc` ring on the logo link is 2.59:1 on `#242424` in dark; "Home" and "Show menu" use white or `#0076a8` rings and the skip link is revealed only on focus (method artifact).

### 2. axe (PASS)

- Pipeline tool `core/tools/axe-check.cjs /perks-trial` (375 and 1280, via the browser-path preload shim), light and dark: 1 violation each run, the site-chrome logo `link-name` (pre-existing, also on `/`).
- My harness at 360/768/1280, tags wcag2a/aa, 21a/aa, 22aa, best-practice, both schemes: 0 violations on page content; the same single chrome `link-name`. No horizontal overflow at 360/768/1280; Lexend and Inter still render on ASCII (CDP). The earlier `aria-prohibited-attr` needs-review item (stock `badge.twig` `aria-label` on a span) is unchanged.

### 3. Gate and build (PASS)

- `npm run lint:css`: exit 0, no findings.
- `npm run build` in `web/themes/custom/mercury`: exit 0; two consecutive builds byte-identical (`sha1 fa2cf498...`, 69,861 B, which is F's reported size). The working-tree `build/main.min.css` is identical to the rebuild, i.e. in sync with the sources. `git diff --exit-code -- web/themes/custom/mercury/build/` is non-zero against HEAD by design: the only diff is one minified line adding `.focus-visible\:ring-ring:focus-visible{--tw-ring-color:var(--ring)}` and the `--color-ring` mapping. Expected until round 2 is committed; I did not restore it.

### 4. Scheme (PASS)

Dark was set with `ddev drush config:set mercury.settings scheme dark -y` plus `drush cr` for the dark runs (served `<html class="dark js">` confirmed), then restored: `ddev drush config:get mercury.settings scheme` -> `'mercury.settings:scheme': light`. `/perks-trial` and `/` serve 200.

### Notes
- A is reviewing in parallel; I changed no config other than the scheme toggle, and no files other than this handoff and `T-artifacts/`.
- Adapter finding 2 and F's round-2 findings 1 to 3 are confirmed by this run: defining the token activates the stock `/50` opacity (which would fail), so the token and the `ring-ring` edit are one change; the settle delay and focused-vs-blurred pixel diff are the working method.

Decision: ready for S. The earlier blocking issue is resolved.
