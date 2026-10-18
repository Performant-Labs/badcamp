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
