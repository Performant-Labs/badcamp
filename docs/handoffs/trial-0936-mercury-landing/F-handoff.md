# F handoff: trial-0936-mercury-landing

Branch `trial-0936-mercury-landing` (not pushed, nothing committed). Adapter: `drupal-canvas-mercury` (unmerged worktree copy, including the mid-cycle L1-L5 rewrite of *Layer hierarchy*). Mode: autonomous.

## 1. What was built

**Page:** Canvas page "Perks (trial)", `/page/3` on this DB (no alias; see finding 9 on why the id moved from 2 to 3), uuid `7562db90-6285-4f93-b533-75efa67696ff`, 31 components in 4 top-level sections. Reproducible from `recipes/badcamp_pages/` (below).

| Mockup block | Components (layer) | Why |
|---|---|---|
| Hero | `section` (L3 zone `background_color: primary`, `columns: 67-33`) > `group` (column) > `badge` (eyebrow), `heading` h1 (+`highlight_text`), `text`, `group` (`column-sm-row`) > 2x `button` (`secondary`, `secondary-inverted`); `stat-badge` in the 33% column | Reuse first. `hero-side-by-side` rejected: needs an image, right side is not a slot. `hero-billboard` rejected: content slot only, image background. `cta` rejected: centred, no side column. |
| Overlap card | `section` (muted zone, width 75%) > `panel` (new) > `heading` h2, `text` | See new components. |
| What you get | `section` (muted zone) header slot: `anchor#perks`, `heading` h2, `text`; main slot: `grid` (`3 columns, 2 on tablet`) > 6x `card-icon` (`eyebrow`, `icon_style: primary_background`, `background_color: background`) | `card` rejected (heading renders only when `url` is set). `card-icon` fits with one optional prop added. |
| Closing panel | `section` (width 75%) > `group` (`background: primary`, `radius: xl`, `padding: md`) > `heading` h2, `text`, `button`, 2x `text` (small) | Existing `group` suffices; no new component. No amount pills (badcamp#14). |

**Edits to existing Mercury components** (each is an additive, optional prop or enum value, so existing instances are unchanged): `heading` (+`highlight_text`, `highlight_color`, `text_color: on-primary`), `text` (+`on-primary`), `card-icon` (+`eyebrow`), `grid` (+`3 columns, 2 on tablet`), `group` (+`column-sm-row`), `button` and `badge` (radius `rounded-lg`/`rounded-sm` to `rounded-pill`).

**New components (reuse failed):**
- `stat-badge`: circle with value over label. Rejected `badge` (small rectangle, one label), `card-icon` (hard-coded h3, radius enum stops at 32px, heading max 36px).
- `panel`: bordered card with a slot and an `overlap: previous-section` prop. Nothing in Mercury overlaps the previous section. Rejected `card` (heading hidden without `url`), `card-icon` (hard-coded h3 would skip a heading level), `group` (background enum is primary/secondary/accent), `section` (background is a full-bleed layer).

Brand tokens: web-root `theme.css` / `fonts.css`, self-hosted Lexend, Inter, Aleo (`@fontsource-variable` 5.3.0, OFL). Per-change traces are in `docs/css-change-log.md` (16 entries, tagged L1-L5; none retroactive).

**Deviations from the mockup (all deliberate):** badge is a pill but filled orange, not outline; "See the six perks" is Mercury's `secondary-inverted` (dark fill, yellow border), not a transparent outline; hero is brand blue (no navy exists in the brand); closing sentence drops "Choose an amount." (no picker); hero buttons stack on mobile. Brand shades `600` (#005d85, #c23d14) and footer #242424 have no semantic slot and are unused (#242424 is used only as the `.dark` background).

## 2. Files changed

**Conversion** (separable)
- `web/themes/custom/mercury/` copied from contrib 1.0.5 (1,633 files, 1,512 are Phosphor icons), `node_modules` ignored by the theme's own `.gitignore`
- `composer.json`: `drupal/mercury` removed, `drupal/cva` ^1 added (see finding 1)
- `composer.lock`
- `web/themes/contrib/mercury` removed by Composer (ignored by git)

**Tokens**
- `web/theme.css`, `web/fonts.css`, `web/fonts/{Lexend,Inter,Aleo}/{latin,latin-ext}.woff2` + `OFL.txt` each
- `.stylelintrc.json`, `docs/css-change-log.md`

**Page / recipe**
- `recipes/badcamp_pages/recipe.yml`
- `recipes/badcamp_pages/content/canvas_page/7562db90-6285-4f93-b533-75efa67696ff.yml`
- `.gitignore` (`/recipes` became `/recipes/*` + `!/recipes/badcamp_pages`; finding 5)

**Other (theme edits made after conversion, for the page)**
- `web/themes/custom/mercury/src/main.css` (+`--radius-pill` in `@theme inline`)
- `components/{button,badge,heading,text,card-icon,grid,group}/*.twig|*.component.yml`
- new `components/stat-badge/*`, `components/panel/*`
- `build/main.min.css`, `build/motion.js` (rebuilt)
- `docs/handoffs/trial-0936-mercury-landing/perks-{light,dark}-{360,768,1280}.png` (the dark ones are from `/page/2`, pre-recipe; light ones are from the recipe-built `/page/3`)

`web/robots.txt` was rewritten by Composer's scaffold and restored with `git checkout` (finding 8). Scratch build script deleted per protocol.

## 3. T1 / T2 self-check (run against https://badcamp.ddev.site:8493, CSS aggregation ON)

| Check | Command / method | Result |
|---|---|---|
| Cache clear | `ddev drush cr` | ok |
| Page serves | `curl -sk -o /dev/null -w '%{http_code}' /page/3` | 200 |
| Token override live | `grep` page for `/theme.css`, `/fonts.css`; count of `src/theme.css` | both linked, 0 stock links; served `theme.css` has `--primary:#0076a8`, `--font-sans: "Lexend"`, `--radius-pill:2rem` |
| Font check | `curl` each of 6 `url()` in `fonts.css` | 6/6 HTTP 200, `font/woff2`; Lexend and Inter loaded in browser (Aleo declared, unused so not fetched) |
| Anchor / links | grep | `id="perks"` x1, `href="#perks"` x1, `href="/donate"` x2 (`/donate` itself is 404: out of scope) |
| Nav breakpoint agreement | grep | no `--breakpoint-md` override (48rem), `matchMedia("(min-width: 48rem)")`, `theme.css` `@media (min-width: 768px)`: all 768px |
| Build in sync | `npm run build` twice, `shasum` | byte-identical. Conversion rebuild was 68,562 B (matches adapter); with page edits 69,932 B. Not diffed against a committed baseline (finding 7). |
| Blast-radius gate | `stylelint --config .stylelintrc.json` over `src/main.css`, `*.tailwind.css`, `theme.css`, `fonts.css` | 0 findings; negative test (`.card{color:red}` in `theme.css`) fails as designed |
| axe T2 | Playwright 1.64 headless (cached chrome-headless-shell 1243 via `executablePath`) + axe-core 4.10.2, tags wcag2a/aa, 21a/aa, 22aa, best-practice, at 360/768/1280, light and dark (`mercury.settings scheme` toggled, restored to `light`) | 0 violations on page content in all 6 runs after fixes. 1 remaining `link-name` (serious) on the site-chrome logo link (`alt=""`), identical on `/page/1` (Home): out of scope (badcamp#10) |
| Overflow / taps | same script | no horizontal overflow at 360/768/1280; the 3 elements under 44px are site chrome |
| Perf (informational) | `curl` sizes | 4 stylesheets: core aggregate 1 KB, `main.min.css` 69.9 KB (12.0 KB gz), `theme.css` 3.8 KB, `fonts.css` 2.7 KB; woff2 total 284 KB |

Contrast fixes made from axe findings: eyebrow `/70` opacity gave 4.47:1, now `/80`; dark scheme `inverted` text gave 3.07:1 on the blue band, fixed with `on-primary`.

Recipe round trip: deleted the page, applied `ddev drush recipe /var/www/html/recipes/badcamp_pages`, page recreated with the same uuid, 31 components, 0 NULL `component_version`, axe clean (finding 2 explains the detour). Not a fresh install.

SRI: N/A (no third-party assets). srcset: N/A (no images).

## 4. Adapter findings (most serious first)

1. **`composer remove drupal/mercury` breaks the site.** `ddev composer why drupal/cva` shows only `drupal/mercury 1.0.5 requires drupal/cva`. `ddev composer remove drupal/mercury --dry-run` lists `Removing drupal/cva (1.0.1)`; Mercury's `info.yml` has `dependencies: cva:cva`, so the enabled module would vanish. I stopped that path, ran `ddev composer require drupal/cva:^1` first, then `composer remove drupal/mercury` (removed only mercury; site 200, cva still enabled). The adapter's conversion steps (and Mercury's own `CUSTOMIZING.md`) need this step before the remove. Also its "Never edit contrib in place" note should add that `web/themes/contrib/mercury` is deleted by the remove, so the copy must already exist.
2. **"Delete the page first" does not work on Drupal CMS.** The `trash` module soft-deletes entities. `$page->delete()` left the row (`deleted` set); `drush recipe` then hit the same uuid, skipped it, yet printed "Created content for BADCamp pages recipe" and "applied successfully", and the page returned 404 with no log entry. A hard delete needs `trash.manager->executeInTrashContext('inactive', fn() => $page->delete())`; a fresh install avoids it. The adapter's reproducibility check should say this, and that a successful recipe message is not proof the page imported.
3. **`drush content:export --dir=<relative>` resolves against `web/`.** `--dir=recipes/badcamp_pages/content` wrote `web/recipes/badcamp_pages/content/canvas_page/<uuid>.yml`. It needs an absolute path (`/var/www/html/recipes/...` under DDEV) or a `../`. The output also adds the `canvas_page/` subdirectory, which the adapter's example omits.
4. **Mercury cannot express several things this kind of page needs, and the adapter's "prefer Layer 3 props, no code" framing hides it.** For this mockup, gaps needing Twig edits: inline highlighted text in a heading, a perk grid of 2 then 3 columns (Mercury: 3 at md, or 4 at lg), a flex row that wraps (`group` row overflows at 360px), a pill radius separate from `--radius`, an overlap between sections, a circle badge, an eyebrow on `card-icon`. The adapter lists reuse sources but no gap list; a short "known gaps in 1.0.5" would let a brief be scoped to "convert first" up front.
5. **`.gitignore` ignores all of `/recipes`**, so the profile's `recipes/badcamp_pages/` could not be committed. Changed to `/recipes/*` + `!/recipes/badcamp_pages`. The adapter's recipe section should say to check this. (`web/themes/contrib` ignored and `custom` not is the right way round.)
6. **Stylelint rule sets are given as two JSON objects.** A single root `.stylelintrc.json` needs `overrides` with `files` globs and a top-level `"rules": {}`, otherwise stylelint 16 throws `No rules found within configuration`. The fonts.css override must also repeat the `at-rule-allowed-list`. The adapter does not say where to install stylelint (the repo root has no `package.json`; I ran it from a scratch dir).
7. **Conversion baseline ordering.** The adapter says the conversion commits the rebuilt `build/main.min.css` as the baseline, but F does not commit and then keeps working in the same tree, so the baseline is lost once page edits rebuild the file. I confirmed the number (68,562 B, matches the adapter) but cannot produce a baseline commit now. Suggest: O commits the conversion before F starts page work. Also: the theme's `package.json` says 1.0.3 while `info.yml` says 1.0.5 (the adapter's "converted-from version" should come from `info.yml`); `npm run build` also builds `build/motion.js` (adapter mentions CSS only); `.nvmrc` is v24.12.0 and host Node is 26.7 (built fine; `npm ci` warns about uncovered install scripts for esbuild/@parcel/watcher).
8. **Composer side effects not mentioned.** `composer require`/`remove` re-ran the scaffold and rewrote `web/robots.txt` (dropped the `Sitemap:` line), and installed three packages that were in the lockfile but not in `vendor/` (`league/csv`, `drupal/csv_serialization`, `drupal/views_data_export`). The lock diff is therefore larger than "mercury removed". I restored `robots.txt`; the pre-existing vendor drift is untouched beyond that. The adapter should say to review `git diff` after any Composer step.
9. **Canvas scripting details the protocol and adapter omit or get stale.** (a) After editing a `.component.yml`, `drush cr` must run before a script writes inputs: the enum is validated against the stored Canvas component config, and my first run threw `Does not have a value in the enumeration ["column","row"]` for the new value. (b) Canvas re-registers changed SDCs on `drush cr` and changes their `active_version`; new SDCs appear automatically. (c) A re-applied page gets a new id (3), so `/page/{id}` is not stable across recipe round trips; give pages an alias. (d) `canvas-scripting-protocol.md` is written around DripYard (`flex-wrapper`, `title-cta`, `target_id` images, a v1.3.2 stack); what carried over cleanly here: `title` field, `/page/{id}`, `canvas_page__components` table, `component_version` copied from `get('active_version')`, exported YAML without `component_version` (import set it: 0 NULL of 31).
10. **A `none` hash key in `html_cva` breaks the required formatter.** Twig treats `none` as a keyword and `@zackad/prettier-plugin-twig` throws "Invalid Token", and rewrites a quoted `'none'` back to unquoted. Mercury's `AGENTS.md` requires `npm run format`; the adapter should warn against `none`/`null`/`true` as variant keys (I used `normal`; `off` is risky as a YAML 1.1 boolean).
11. **Mercury's `text_color: inverted` is scheme-relative, so the adapter's "choose the variant, no code" fails in dark.** `inverted` = `text-background`, which is `#242424` on a primary band in `.dark` (axe 3.07:1). The adapter's NFR step "every shipped variant" catches it, but nothing tells F that zone props (`section.background_color`) do not set the colour of child `heading`/`text`. Added `on-primary`. (Stock `button` has the matching `primary-inverted`/`secondary-inverted` trap.)
12. **Profile/adapter ambiguity on brand tokens.** "secondary 600 `#c23d14`, for small text on orange" and "footer `#242424`" have no semantic slot; the adapter's rule ("profile decides, usually `--accent`") only covers one colour. I left both unused. A brand radius with no slot (2rem pill) needed a new L1 token + L2 `@theme inline` line + L4 edits; the adapter's L1-L5 text handles this but its token list (`--radius`, `--shadow-*`) does not mention that a new token needs the `@theme inline` line to become a utility.
13. **Stock `src/fonts.css` Inter rule carries the latin-ext `unicode-range` only** (read from the file, not verified in a browser against stock): copying it to the web root as the adapter says would leave ASCII text on the fallback font. The adapter's font gotcha mentions URLs but not this. My own `fonts.css` declares latin and latin-ext for each family.
14. **Tooling.** `core/verification-tiers.md` names `tools/axe-check.cjs`; it does not exist in the playbook (`ls playbook/tools` has nothing). I used a scratch Playwright + axe script. Playwright 1.49 wanted a Chromium that was not cached; I pinned the cached headless shell by `executablePath`. The CSS-change-workflow's pre-commit hook and `lefthook` were not set up (no root `package.json`).
15. **Scope cap.** Core F says to propose a split above ~6 files / more than one component family. The brief already prescribes the split (conversion separate), so I did not stop; autonomous decision, recorded here.
16. **Mercury 1.0.5 defects seen while reading (not adapter errors):** `card.twig` renders the `<h3>` only when `url` is set; `grid.twig` uses undefined `section_classes` and `default('100')` for a column enum that has no `'100'`; `icon.twig` emits a `min-w:{{size}}` class that is not valid Tailwind; the stock logo link has `alt=""` (axe `link-name` on every page).

## 5. Left undone

- Fresh-install reproducibility not run (only delete, hard-purge, re-apply). The recipe depends on the converted theme being present in code; Canvas component config entities are generated on `drush cr`, not shipped in the recipe.
- Focus-ring contrast (3:1) and hover states not measured, either scheme. T2.5 N/A (no stateful surface). U and T3 (S) not run by me.
- Baseline commit of the pure-conversion build (finding 7); pre-commit hook for stylelint.
- No path alias for the page; `/donate` target does not exist yet; site header/footer untouched (badcamp#10).
- `docs/css-change-log.md` header uses the workflow's text plus a layer-tag preamble; entries were not checked by A.

---

## Rework round 1

Triggered by A's review (PASS with warnings). Baseline is now O's commit `3e99366` (pristine Mercury 1.0.5), so `git diff web/themes/custom/mercury` is exactly F's change. Sections 1 to 5 above describe the first pass; where they conflict with this section, this section wins (notably: page path `/perks-trial`, no `on-primary`, badge no longer site-wide, neutrals).

### What changed

| # | A item | Change |
|---|---|---|
| 1 | W1 badge | Reverted the site-wide radius. New optional `shape: default \| pill` prop on `badge`; the radius class is chosen in a variable before `html_cva` so default output (including class order) is byte-identical to stock. Hero eyebrow uses `shape: pill`. |
| 2 | W2 zone colour | `group` `background` variants now pair `bg-* text-*-foreground` (primary/secondary/accent), like `section`. Removed `on-primary` from `heading` (hero and panel headings now use `default`, which inherits). `text`: `on-primary` replaced by additive `inherit`; used in every zone child. Logged as intended site-wide L4. Existing uses affected: none (Canvas content is Home `button`+`cta`; `group` appears only on this page). |
| 3 | W4 whitespace | Whitespace control in the new capture blocks of `heading` and `card-icon`; `card-icon` prints the eyebrow on the same line as the `<h3>` so the unused case adds no bytes. |
| 4 | W5 fallback | `--radius-pill` added to the theme's own `src/theme.css` (`:root` and `.dark`), L1 entry. |
| 5 | W8 neutrals | Light neutrals are the profile scale only: background `#fff`, foreground and card-foreground and muted-foreground `#3d3d3d`, card and muted `#eee`, border and input `#ccc`. `.dark` derived per the profile: background `#242424`, card and muted `#3d3d3d`, border and input `#646464`, foreground `#eee`. Provenance per value is in the log. |
| 6 | W7 gate | Root `package.json` (private; only script `lint:css`) with exact devDependencies `stylelint 16.26.1`, `playwright 1.64.1`, `@axe-core/playwright 4.14.0`; `package-lock.json`; `/node_modules` added to `.gitignore`. Installed with `npm install` at the repo root (no symlinks). Theme CSS rebuilt after the Twig changes (69,726 B). |
| 7 | W3/W6 log | DOM evidence re-run against `/perks-trial`; every L4 and L5 entry now has an explicit "L1 to L3 ruled out because" line. Log now has 20 entries (2 tagged `[removed]`, kept for history). |
| 8 | alias | `/perks-trial` set on the page; recipe re-exported with an absolute `--dir`; the exported YAML carries `path.alias`. |

### Results

- `npm run lint:css` (stylelint 16.26.1, root `.stylelintrc.json`, over `src/main.css`, `components/**/*.tailwind.css`, `web/theme.css`, `web/fonts.css`): exit 0, 0 findings, including `anchor.tailwind.css` (A's one finding was a Stylelint 17.15 artifact). Negative control (`.card { color: red }` appended to `theme.css`) fails with `property-allowed-list`; file restored.
- Byte-identity vs stock (`git show HEAD:` sources vs working tree, 400 random combinations of the stock enums, booleans and strings per component, rendered through Drupal's renderer): `heading`, `card-icon`, `badge`, `text`, `grid` 0 differences. `group` and `button` differ in every combination: `button` is the logged `rounded-lg` to `rounded-pill`; `group` differs only when `background` is set (checked: with `background` unset, 0 differences), the intended pairing.
- axe (`@axe-core/playwright`, tags wcag2a/aa, 21a/aa, 22aa, best-practice; 360/768/1280; light and dark via `mercury.settings scheme`, restored to `light`): 0 violations on page content in all 6 runs. Only finding is the site-chrome logo `link-name`, identical on Home. No horizontal overflow, no tap target under 44px inside `<main>`.
- T1: `/perks-trial` 200; `/theme.css` and `/fonts.css` linked, 6/6 font URLs 200; served `theme.css` has `--card:#eeeeee`, `--border:#cccccc`, `--radius-pill:2rem`; two consecutive `npm run build` are byte-identical; nav breakpoint unchanged (48rem / 768px in all three places).
- Recipe round trip, done properly this time: hard-deleted the page via `trash.manager->executeInTrashContext('inactive', ...)` loading **by id**, confirmed `/perks-trial` was 404 and the alias row gone, applied `drush recipe /var/www/html/recipes/badcamp_pages`; page came back (id 4, same uuid), alias `/perks-trial -> /page/4` restored by the recipe, 31 components, 0 NULL `component_version`, axe clean. (My first attempt used `loadByProperties(['title' => ...])` in the same context; it found nothing, the page was not deleted, and the recipe silently skipped. Load by id.)
- Screenshots regenerated under `docs/handoffs/trial-0936-mercury-landing/perks-{light,dark}-{360,768,1280}.png` from `/perks-trial`.

### Files changed in this round

`components/{badge,group,heading,text,card-icon}/*` (twig and yml), `src/theme.css`, `build/main.min.css`, `web/theme.css`, `package.json`, `package-lock.json`, `.gitignore`, `docs/css-change-log.md`, `recipes/badcamp_pages/content/canvas_page/7562db90-...yml` (re-exported), screenshots. Scratch harness files were deleted.

### Layer I would place differently now

- The first-pass `on-primary` pair (heading and text) and the site-wide badge radius: both replaced as above.
- `button` `rounded-pill` remains L4 site-wide by design; it reaches `navbar` and `card-pricing` buttons (badcamp#10 chrome). Flag in the PR.

### New adapter findings

1. **Twig drops the first newline after any block tag, and the formatter undoes hand-placed whitespace control.** "Additive, byte-identical" needs `{%- ... -%}` on new top-level blocks and care at the *end* of the last block (leave the right side untrimmed, or the stock blank lines are lost). Inserting a line inside an existing branch adds whitespace that `prettier` then re-wraps onto its own line; the only stable form I found is printing the new output on the same line as an existing element (`{{ eyebrow_markup }}<h3 ...>`). The adapter's "additive edit" guidance should say this and name the byte-compare as the check.
2. **A byte-identity harness is cheap and belongs in the adapter.** `git show HEAD:<component>.twig` vs the working tree, rendered with `renderer->renderInIsolation(['#type' => 'inline_template', ...])` inside `drush scr`, over random enum combinations. It found three real whitespace/class-order differences that reading the diff did not (A found the same two by a PHP harness).
3. **`~` inside `html_cva(base: [...])` keeps class order**, so a conditional radius can be chosen by a variable without changing default output; a variant map would have moved the class. Worth a line next to "prefer enum props".
4. **Zone colour is per component, not per zone.** Confirmed in practice: `section` pairs bg and fg, `group` did not, `heading` inherits, `text` does not. The adapter's L3 text should list which wrappers and children participate.
5. **`content:export` with an absolute `--dir` works and exports `path.alias`**; the alias is the only stable URL across recipe round trips (`/page/{id}` moved 2 to 3 to 4).
6. **Trash purge by property lookup silently does nothing**; purge by id in the `inactive` context. (Refines first-pass finding 2.)
7. **Stylelint 16.26.1 passes stock Mercury 1.0.5 and this cycle's changes with 0 findings** (including `anchor.tailwind.css`), so the adapter's "stock passes with 0" holds for the pinned 16.x; 17.15 reports `selector-max-type` on `&:is(span, a)`. The adapter should pin the version it was calibrated on (the profile now does).
8. **Playwright via npm installs no browser.** The root `package.json` pins `playwright` but a browser must already be cached; I pointed `executablePath` at the cached chrome-headless-shell (1243). A `npx playwright install chromium` step (a download) is needed on a clean machine; I did not run it.

### Still not done

Fresh-install reproducibility (round trip used the existing DB), focus-ring contrast, U and T3, pre-commit hook for `lint:css`, and `/donate` still 404.

---

## Rework round 2

Blocker from T: light-scheme focus ring on the two "Donate" buttons was 2.15:1 on the blue band (3:1 required).

**Cause (traced):** Mercury's markup uses `focus-visible:ring-ring/50 border-ring ring-[3px]`, but nothing defines `--ring` or `--color-ring`, so those utilities generate nothing and the ring falls back to `currentColor` (`#3d3d3d` on `#0076a8`).

**Fix (3 token edits + 1 variant-map edit; all logged as L1/L1/L2/L4 in `docs/css-change-log.md`, 24 entries):**
- L1 `--ring` in web-root `theme.css`: light `#242424` (neutral 600), `.dark` `#eeeeee` (neutral 100).
- L1 the same two values in the converted theme's `src/theme.css` (fallback).
- L2 `--color-ring: var(--ring)` in `src/main.css` `@theme inline`.
- L4 `button` and `badge`: `focus-visible:ring-ring/50` to `focus-visible:ring-ring`. Without this the `/50` blends the ring into the band (about 1.8:1). Logged as intended site-wide; it reaches every button.

One colour per scheme reaches 3:1 on every surface, so no two-tone `ring-offset` was needed. Computed ratios, ring vs surface: light `#242424` on #fff 15.52, #eee 13.38, #0076a8 3.07, #fdd362 10.84, #d44418 3.43; dark `#eee` on #242424 13.38, #3d3d3d 9.36, #0076a8 4.35, #d44418 3.90.

**Measured (headless Playwright, real focus, 500 ms settle for the 300 ms transition; changed pixels within 6px of each element, ring colour vs the unfocused colour behind it; scripts in scratch, not in the repo):**

| Element (in `<main>`) | Light 360 and 1280 | Dark 360 and 1280 |
|---|---|---|
| "Donate $100" (hero) | `#242424` on `#0076a8` = 3.07:1 PASS | `#eee` on `#0076a8` = 4.35:1 PASS |
| "See the six perks" (hero) | 3.07:1 PASS (was 3.53 with `currentColor` yellow) | 4.35:1 PASS |
| "Donate" (closing panel) | 3.07:1 PASS | 4.35:1 PASS |

All 12 main-content measurements pass, and `:focus-visible` matched for each. Roughly 83 to 95% of changed ring pixels are at or above 3:1; the rest are anti-aliased edge pixels. Chrome (badcamp#10, out of scope): skip link and logo use the browser default `#005fcc` ring, 5.98:1 on white (pass) but 2.59:1 on `#242424` in dark (logo and Home links, fail; unchanged and not introduced here).

**Gate and checks:** `npm run lint:css` exit 0, 0 findings. axe at 360/768/1280, light and dark: 0 violations on page content (only the chrome logo `link-name`). Theme rebuilt (69,861 B, was 69,726; two consecutive builds byte-identical). `git diff --exit-code -- web/themes/custom/mercury/build/` is non-zero against HEAD by design (the new `--color-ring` and ring-colour utilities are the only change, 1 minified line); once this round is committed the check is clean. `mercury.settings scheme` restored to `light`. Page, recipe and alias untouched.

**Files changed this round:** `web/theme.css`, `web/themes/custom/mercury/src/theme.css`, `src/main.css`, `components/button/button.twig`, `components/badge/badge.twig`, `build/main.min.css`, `docs/css-change-log.md`, this handoff.

**New adapter findings:**
1. **Mercury ships utilities that depend on tokens it never defines.** `ring-ring`, `border-ring` appear in `button` and `badge` but there is no `--ring`/`--color-ring` in stock `theme.css` or `@theme inline`, so they silently emit nothing. The adapter's token list (colours, fonts, radius, shadows) omits `--ring`, and its "Per-variant WCAG contrast + focus (G5)" check has no step that finds this; a grep of the Twig for `ring-` against `@theme inline` would.
2. **Defining the token activates the stock `/50` opacity**, which then fails 3:1. A token-only fix is not enough; the L4 `ring-ring/50` edit is part of the same change. The adapter should say that when a token that was previously absent is added, the utilities that reference it start generating and need re-measuring.
3. **Focus-ring measurement needs a settle delay** (buttons have `duration-300`) and `focus({ focusVisible: true })`; pressing Tab first reveals the skip link and pollutes the pixel diff. T's method (diff of focused vs blurred pixels against the unfocused backdrop) is the right one and worth putting in the adapter's T section.
4. **One ring colour per scheme works when it is a near-black or near-white neutral**: against a mid-tone brand blue the usable range is L <= 0.019 or L >= 0.574, which `#242424` and `#eee` satisfy. A brand where the band is mid-luminance on both sides of that range would need the two-tone form.

---

## Rework round 3

From S's audit (REWORK: R1 to R6, plus decisions on holds H1 and H2). Base is commit `52978de`. Every CSS change has an L1 to L5 trace and a "ruled out" line in `docs/css-change-log.md` (now 37 entries).

| Item | Trace and fix | Layer |
|---|---|---|
| **R1** badge not a circle | The wrapper is a flex row; the child's default `align-items: stretch` stretched the circle to the grid cell and overrode `aspect-square`. Wrapper now `items-center`, which also centres it vertically in the 33% column. Measured 224 / 237 / 288 px circles at 360 / 768 / 1280. | L5 (the new SDC) |
| **R2** outline button | No stock variant is transparent (`secondary-inverted` is a charcoal fill). Added additive `button` variant `outline` (`border-current bg-transparent text-inherit hover:bg-current/10`), zone-agnostic; white on `#0076a8` 5.05:1. Used on "See the six perks". | L4 additive |
| **R3** headline break | Additive `heading` prop `break_after`: an empty `span.lg:block` after the phrase (no `<br>`), so it breaks between the sentences from `lg` up and wraps naturally below. 1280: 2 lines; 768 and 360: 3. Highlight and break now share one escaped-string replace. | L4 additive |
| **R4** hero rhythm | The section padding enum stopped at 64 (leaving 16px under the buttons). Added additive padding value `96`; hero `padding_block_end: 96`. Clear space under the buttons: 48px at 1280, 40px at 768 (badge 62 / 82px). | L4 additive + L3 prop |
| **R5** heading to intro gap | No code: the section header slot adds no gap, so heading and intro sit in an existing `group` (column, gap md). Measured 16px at every width. A site-wide `section` header change was ruled out as broader than needed. | L3 props |
| **R6a** closing zone | Closing section is now `background_color: muted`, margins 0, `padding_block_end: 64`; the page stays grey to the footer. | L3 props |
| **R6b** notes | Mercury has no divider and the text format strips a raw `<hr>` (checked: 0 `<hr>` in served HTML). Added additive `text` boolean `divider_above` (`w-full border-t border-current/30 pt-4`); notes merged into one `text`, `text-xs` (12 / 14 / 16px against the 16 / 18 / 20px sentence). | L4 additive |
| **R6c** radius | `rounded-xl` = `--radius` + 4px = 16px; `card-icon` `medium` is `rounded-xl md:rounded-2xl` (Tailwind's fixed 1rem). `--radius` itself (0.75rem) was right. Changing `--radius-xl` in L2 would collapse `xl` into `lg` for every other consumer (`card`, `hero-side-by-side`), so not done. Containers now use the exact step `rounded-lg`: `group` instance prop `radius: lg` (no code), `panel` (L5), and additive `card-icon` `border_radius: container`. Measured 12px on the overlap card, perk cards and closing panel at all widths. | L3 prop, L5, L4 additive |
| **H1** content width | `section` `width: 90%` on the hero and perks sections (existing prop; only the inner content narrows, backgrounds stay full-bleed); the inset panels stay at 75%. Content is 1123px at 1280 (was 1248). The container (badcamp#10) is untouched. | L3 props |
| **H2** eyebrow on orange | New token `--secondary-600: #c23d14` (L1 web-root light and `.dark`, L1 fallback in `src/theme.css`, L2 `--color-secondary-600`), and the badge `secondary` style now uses `bg-secondary-600` (L4, intended site-wide, since badge text is always small). Eyebrow measured `rgb(194, 61, 20)`; white on it is 5.29:1. | L1 + L2 + L4 |

### Results

- **Page:** recipe re-exported (absolute `--dir`, only the page's yml; an early export without an id also wrote Home, which I deleted); hard-deleted the page by id, applied the recipe: `/perks-trial` 200 again (id 5, alias carried), 31 components, 0 NULL `component_version`.
- **Gate:** `npm run lint:css` exit 0, 0 findings.
- **Byte-identity vs stock (`3e99366`, 400 random combinations per component):** `heading`, `card-icon`, `text`, `grid`, `section` 0 differences. `badge`, `button` and `group` differ as logged (ring and secondary-600; ring, radius and outline; background pairing).
- **axe (360 / 768 / 1280, light and dark):** 0 violations on page content in all 6 runs; only the chrome logo `link-name`. No overflow, no tap target under 44px in `<main>`.
- **Focus contrast** (same method as round 2; the new outline button included): light `#242424` on `#0076a8` = 3.07:1, dark `#eee` on `#0076a8` = 4.35:1, all three buttons at 360 and 1280. Pass.
- **Build:** `npm run build` twice byte-identical (70,837 B); `git diff` of `build/main.min.css` against HEAD is the expected one-line change from the new utilities. `mercury.settings scheme` restored to `light`.
- Screenshots in the handoff dir refreshed (`perks-{light,dark}-{360,768,1280}.png`).

### Files changed this round

`web/theme.css`, `src/theme.css`, `src/main.css`, `components/{badge,button,heading,section,text,card-icon,panel,stat-badge}/*` (twig and yml where props were added), `build/main.min.css`, `recipes/badcamp_pages/content/canvas_page/7562db90-...yml`, `docs/css-change-log.md`, this handoff, screenshots.

### Layer I would place differently

Nothing flagged. The `outline` variant and `divider_above` are additive L4 edits to shared components because no existing variant could carry them; both are zone-agnostic (`currentColor`) so they work on any zone.

### New adapter findings

1. **A fixed-shape child of a flex wrapper is stretched by the parent's default `align-items: stretch`**, which overrides `aspect-ratio`. S found it, F's own screenshots at 768 showed it. Add to the adapter's "new component" guidance: set `items-center` (or `self-center`) on any wrapper around an aspect-ratio element, and measure its box at every breakpoint.
2. **The Canvas text format strips `<hr>` from rich-text props at render** (stored in the input, absent in the HTML). Anything the text prop needs beyond basic inline and block tags has to be a component feature. Mercury 1.0.5 has no divider component.
3. **Mercury's `--radius` scale never gives containers the brand radius.** Only `rounded-lg` equals `--radius`; `sm`, `md`, `xl` are offsets and `card-icon` uses Tailwind's fixed `2xl`. The adapter's L1 token notes should list which utilities equal the token, so a brand radius is applied by choosing `lg`, not by editing L2 (which would collapse the scale). Same note as S's finding 3.
4. **Escaped-string replace beats splitting for inline markup in a Twig prop.** Building `heading` markup with `'' ~ (text|e)` then `replace({...})` and printing with `|raw` keeps both highlight and break in one path and leaves the unused case byte-identical. (`|e` returns Markup; coerce with `'' ~` before `replace`, and for mapping keys.)
5. **Instance spacing enums can be exhausted by a design.** `section` padding stopped at 64 while the mockup's rhythm needed about 96; the adapter's L3 ("choose the prop") should say the next step is an additive enum value in the variant map (L4), logged, with the existing margin `96` scale as the model.
6. **`content:export` without an entity id exports every entity of the type.** An early export with a shell variable that came back empty wrote Home too. Always pass the id and check the file list.

---

## Rework round 4

Two items from A's Round 4 (W10, W11). Still uncommitted on top of `52978de`.

**W10, radius placement (O's decision).** Round 3 had moved L1 `--radius` to 0.75rem and compensated per consumer. Trace: `@theme inline` derives `sm/md/lg/xl` as `--radius` -4px, -2px, +0, +4px, so at the stock 0.5rem Mercury's `xl` (what cards, groups and panels use) is already 12px = the brand's 0.75rem. Moving the token shifted the whole scale (for example icon tiles went 6px to 10px) and needed the compensations.
- L1 `--radius` reverted to stock **0.5rem** in web-root `theme.css` (`:root` and `.dark`). `src/theme.css` already held the stock value (never changed), so nothing mirrors it. `--radius-pill` is separate and untouched.
- Compensations reverted: `panel` back to `rounded-xl`; the `card-icon` `container` option removed; the closing `group` back to the default `radius: xl` (page input).
- The one genuine gap, `card-icon` `medium` (`rounded-xl md:rounded-2xl`, where `2xl` is Tailwind's fixed 1rem): changed to `rounded-xl`. I chose a logged intended site-wide L4 change over an additive option, because an option would leave the default `card-icon` off the brand container radius. Existing uses: the 6 perk cards on this page only. Checked byte-identity: output differs from stock only when `border_radius` is `medium` (142 of 400 random combinations, all `medium`).
- Log: the old L1 `--radius -> 0.75rem` entry is `[superseded by L50]` with the reason ("brand container radius maps to Mercury's `xl` step at the stock token; moving the token shifted the whole scale"); the `container`, `panel` and "Radius trace" entries are `[removed]`; two new entries (stock `--radius`, `card-icon` medium). 39 entries in total.

**Measured corner radius, `/perks-trial`, 360 and 1280 (computed style of every element in `<main>` with a radius):**

| Element | 360 | 1280 |
|---|---|---|
| Overlap panel, 6 perk cards, closing panel | 12px | 12px |
| Buttons and eyebrow pill | 32px (pill token) | 32px |
| Icon tiles | 6px (stock `rounded-md`) | 6px |
| Stat badge | full circle | full circle |

Confirmed 12px for every card, panel and container; the pill radius and the rest of the scale are unaffected (icon tiles are back to the stock 6px).

**W11, heading.** `break_after` and highlight now go through a single `replace({...})` over the escaped text (strtr: one pass, longest match first), so neither can split the other's markup. When only one feature is set its entry is written twice with the same key and value (avoids `merge`, which would renumber integer-like phrases). Rendered cases checked: none, highlight only, break only, both, a break phrase containing the highlight phrase (break wins, no nested span), HTML-special characters. Unused output is byte-identical to stock (400 combinations, 0 differences).

**Checks:** `npm run lint:css` exit 0. axe light at 360/768/1280: 0 violations on page content (chrome logo `link-name` only), no overflow, no tap target under 44px in `<main>`. `npm run build` twice byte-identical (70,837 B); against HEAD `build/main.min.css` differs by the one expected minified line. Recipe re-exported (page id 5 exported by id; the only file is the page's yml) and re-applied after a hard delete: `/perks-trial` 200, 31 components, 0 NULL `component_version`.

**Files changed this round:** `web/theme.css`, `components/{panel,card-icon,heading}/*`, `build/main.min.css` (rebuilt, unchanged in content apart from the one line), the page input (via the DB, exported to `recipes/.../7562db90-....yml`), `docs/css-change-log.md`, screenshots (`perks-light-*`), this handoff.

**New adapter findings:**
1. **A brand radius is met by choosing the right step of Mercury's scale, not by editing `--radius`.** At the stock 0.5rem, `xl` = 12px; moving the token shifts every consumer. The adapter's L1 radius note should say this and name `card-icon` `medium` (fixed `md:rounded-2xl`) as the only stock consumer that ignores the scale. This corrects my round 3 finding 3.
2. **For several phrase replacements in one Twig string, use one `replace({...})` (strtr).** Sequential replaces let the second match inside the first's markup. Duplicate-key padding is the simple way to keep the map static when a feature is off.
