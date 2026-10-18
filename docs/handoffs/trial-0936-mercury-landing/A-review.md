# A review: trial-0936-mercury-landing

Reviewer: A (Architecture Reviewer), Website Front-End Pipeline. Adapter: `drupal-canvas-mercury` (unmerged worktree copy, "Layer hierarchy" and "Blast-radius gate"). Read-only review: no code, config, content or database was changed. Headless only.

## Verdict: PASS (0 BLOCK, 9 WARN, 3 INFO)

Every style-affecting change in the working tree has a log entry and a trace, and every layer F chose is defensible. I disagree with F's layer on none of them outright. Seven items are "defensible but suboptimal" (W1 to W7), and two more are provenance and process warnings. The seven edited Mercury components are additive for every stock enum value, with two exceptions that F logged as intended site-wide L4 changes (`button`, `badge`). The blast-radius gate has one finding, and it is pre-existing and not introduced by this cycle.

## Method and baseline

- Baseline for "contrib Mercury 1.0.5": `web/themes/contrib/mercury` is not in git (`/web/themes/contrib` is ignored) and Composer deleted it, so `git show HEAD:...` has nothing. I used the pristine Composer-cached package `~/Library/Caches/composer/files/drupal/mercury/8e615fe5...zip` (its `mercury.info.yml` says `version: '1.0.5'`), unzipped to a scratch dir, and ran `diff -rq` against `web/themes/custom/mercury` (excluding `node_modules`).
- Changed files vs baseline (complete list): `src/main.css`; `components/{badge,button,card-icon,grid,group,heading,text}/*.twig`; `components/{card-icon,grid,group,heading,text}/*.component.yml`; `build/main.min.css`; new `components/panel/`, `components/stat-badge/`. `build/motion.js` is byte-identical to stock. No `*.tailwind.css` edit, no `src/theme.css` or `src/fonts.css` edit.
- Web root: `web/theme.css` diffed against stock `src/theme.css`; `web/fonts.css` and `web/fonts/` are new.
- Canvas inputs: I read all 401 lines of `recipes/badcamp_pages/content/canvas_page/7562db90-....yml`. Zero raw hex, oklch, rgb, px or rem, zero `style`, zero arbitrary values. Every non-copy input is an enum or a boolean.
- Home and other Canvas content: `canvas_page__components` holds page 1 (Home: `button`, `cta`) and page 3 (this page). No other Canvas page exists.

## 1. Per-change table

Layer names are the adapter's `[L1]` to `[L5]` tags. "Log" is the `docs/css-change-log.md` entry (16 entries, all present).

| # | Change | F's layer | My layer | Verdict | Reason |
|---|---|---|---|---|---|
| 1 | `web/theme.css` `:root` neutrals: `--background/--foreground/--card/--muted/--border/--input` (+ foregrounds) to `#fff #3d3d3d #f4f6f8 #dce1e6` | L1 | L1 | PASS layer; WARN provenance (W8) | Site-wide semantic tokens, so L1 is the only layer that can carry them. But only `#3d3d3d` is in the profile or the badcamp.org stylesheet. I fetched the live stylesheet: `#f4f6f8` and `#dce1e6` do not occur in it. The log calls them "brand neutrals". |
| 2 | `--primary/--secondary/--accent` (+ foregrounds) to `#0076a8 / #d44418 / #fdd362` | L1 | L1 | PASS | Brand colours; highlight into `--accent` follows the adapter's "brand colour with no slot" rule. All three hex values are in the live stylesheet. 600-shades unused: correct. |
| 3 | `--font-sans/--font-body/--font-serif` to Lexend / Inter / Aleo, plus `web/fonts.css` `@font-face` and `web/fonts/*` | L1 | L1 | PASS | Token values at L1; `@font-face` only in `fonts.css`. All 6 `url()` return HTTP 200 (checked). Latin and latin-ext declared for every family (avoids the stock `unicode-range` gotcha). No third-party CDN. |
| 4 | `--radius` 0.5rem to 0.75rem | L1 | L1 | PASS | Brand "containers 0.75rem"; every `rounded-sm/md/lg/xl` derives from it (L2), so one L1 edit is the right size. Intended site-wide. |
| 5 | `--radius-pill: 2rem` in `:root` | L1 (new token) | L1 | PASS with WARN (W5) | Defensible. Tailwind's `rounded-4xl` is exactly 2rem, so adapter step 2 ("a scale step fits, use it") could be read the other way, but the value is a brand value that should live in one token (step 1). Fallback gap noted in W5. |
| 6 | `src/main.css` `@theme inline` `--radius-pill: var(--radius-pill)` | L2 | L2 | PASS | Needed for the `rounded-pill` utility to exist; same self-referencing pattern as the `--shadow-*` and `--font-sans` lines. Built CSS has `.rounded-pill{border-radius:var(--radius-pill)}`. |
| 7 | `button.twig` base `rounded-lg` to `rounded-pill` | L4 | L4 | PASS (intended site-wide L4) | Only buttons should change, and `--radius` is shared with cards, so L1 to L3 cannot do it. Logged. **Blast radius is wider than the log says:** it also restyles the buttons that `navbar` (site chrome, badcamp#10) and `card-pricing` embed, and Home's CTA button (verified: `rounded-pill` count is 1 on `/`). |
| 8 | `badge.twig` base `rounded-sm` to `rounded-pill` | L4 | L4 | WARN (W1) | Logged as an intended site-wide L4 change, so not a BLOCK. But the need is one instance (the hero eyebrow), the profile's brand lists "buttons 2rem, containers 0.75rem" and says nothing about badges, and the log entry's whole ruling is "same reasoning as button". Every badge on the site now changes without an operator decision. Better: an additive optional prop (for example `shape: pill`, default unchanged), or confirm the site-wide intent in the profile. |
| 9 | `.dark` block mirrors the brand tokens | L3 | L3 | PASS layer; WARN provenance (W8) | `.dark` is the L3 zone for the `scheme` setting. The dark neutrals `#2e2e2e #333333 #4a4a4a #f4f6f8` have no source. `#242424` (footer) as page background is reasoned. `scheme` is restored to `light` (checked with `drush config:get`). |
| 10 | `text_color: on-primary` added to `text` and `heading` maps | L4 | L4 | PASS for `text`; WARN for `heading` (W2) | See W2: the log's L3 ruling is factually wrong for `heading`. |
| 11 | `card-icon` optional `eyebrow` element (`text-xs ... uppercase text-foreground/80`) | L5 | L5 | PASS (see A2) | A new optional element with its own hard-coded utilities is "a component's own Twig utilities" = L5; no token or zone can add an element. `/80` (opacity derivation) is the right L2 step. Additive; byte-impact only whitespace (W4). |
| 12 | `group` `flex_direction: column-sm-row` (`flex-col sm:flex-row`) | L4 | L4 | PASS | Additive enum value on the variant map; mobile-first. Log has no explicit "L1 to L3 ruled out" line (W6). |
| 13 | `grid` `columns: '3 columns, 2 on tablet'` (`md:grid-cols-2 lg:grid-cols-3`) | L4 | L4 | PASS | No stock variant gives 2 at md then 3 at lg (stock has 2 at md, 3 at md, 4 at sm/lg). Additive; mobile-first. |
| 14 | `heading` `highlight_text` / `highlight_color` + span | L4 | L4 | PASS layer; WARN (W4) | Token-backed (`text-accent/primary/secondary`), additive props, reuse of `heading` rather than a new component. Default output differs only in whitespace. |
| 15 | New SDC `stat-badge` | L5 | L5 | PASS | Reuse search is real (see section 4). |
| 16 | New SDC `panel` | L5 | L5 | PASS | Reuse search is real (see section 4). Overlap is the novel capability. |
| 17 | `web/fonts/*`, `build/main.min.css` rebuild, `.stylelintrc.json` | n/a | n/a | PASS | Derived or tooling. Build in sync: I rebuilt to a scratch path with the theme's own `tailwindcss --input ./src/main.css --minify`, `shasum` identical to `build/main.min.css` (69,932 B, `f16830a1...`). |

Completeness: I found no style-affecting change without a log entry. Canvas instance prop choices (section zones and enum paddings, `badge style: secondary`, button variants, `card-icon icon_style/background_color`) are "choose an existing variant" and need no code; they were not logged and I do not require it (A5).

Log accuracy: file and line references in the log match the files (`button.twig:L10`, `badge.twig:L13`, `text.twig:L25`, `heading.twig:L18`, `group.twig:L8`, `grid.twig:L31`, `main.css:L34`, `theme.css:L34`). One stale reference: the DOM-inspection gate cites `curl .../page/2`, which no longer exists (the page is `/page/3` after the recipe round trip).

## 2. Existing-component additivity (the seven L4/L5 edits)

I rendered the stock Twig and the converted Twig side by side with PHP (`twig/twig` from `vendor/` plus the `cva` module's `html_cva` extension, stubs for `create_attribute`, `attach_library` and the icon include), over the cartesian product of every **stock** enum and boolean value in each stock `*.component.yml` (capped at 6,000 combinations, sampled by `shuffle`), crossed with string-prop samples (HTML-special characters, with and without `url`, with and without `icon`). The harness lives in my scratch dir, not in the repo.

```
component  combos  byte-diff  whitespace-normalised-diff
heading     3600     3600        1800   (url cases only; see W4)
text          21        0           0
card-icon   9720     9720           0   (blank lines only; see W4)
grid         216        0           0
group       6000        0           0
button      2304     2304        2304   (rounded-lg -> rounded-pill, logged)
badge         16       16          16   (rounded-sm -> rounded-pill, logged)
```

- `text`, `grid`, `group`: byte-identical for every stock value. Purely additive.
- `heading` and `card-icon`: the new markup capture adds whitespace around the text. Visually identical (whitespace at line edges collapses, accessible name is trimmed), but not byte-identical. With `url`, stock `<a href="/donate">Text</a>` is now `<a href="/donate"> Text </a>`. W4.
- `button`, `badge`: the only change in output is the intended radius class.
- Home (`/`): the served HTML has exactly one `rounded-pill` (the CTA button) and nothing else of the seven components; reasoned from the Twig diff, the only difference from stock rendering is that class.

## 3. Blast-radius gate (adapter's Stylelint rulesets, verbatim)

Stylelint is not installed in the repo (no root `package.json`). I used 17.15.0 from another project's `node_modules` (`/Users/andreangelantoni/Sites/language-buddy/node_modules/stylelint`). The adapter's two rulesets were written verbatim to scratch files (`hand.json`, `tok.json`, `font.json`). `SL=<that path>/bin/stylelint.mjs`, `T=web/themes/custom/mercury`.

```
$ node $SL --config hand.json --allow-empty-input $T/src/main.css $T/components/*/*.tailwind.css
web/themes/custom/mercury/components/anchor/anchor.tailwind.css
  4:3  x  Too many type selectors in "&:is(span, a)", maximum 1  selector-max-type
1 problem (1 error, 0 warnings)   exit=2

$ node $SL --config hand.json <stock 1.0.5>/src/main.css <stock>/components/*/*.tailwind.css      (baseline)
.../anchor/anchor.tailwind.css
  4:3  x  Too many type selectors in "&:is(span, a)", maximum 1  selector-max-type
1 problem (1 error, 0 warnings)   exit=2

$ node $SL --config tok.json web/theme.css             -> no output, exit=0
$ node $SL --config font.json web/fonts.css            -> no output, exit=0

$ negative control: copy of theme.css + ".card { color: red; } @import 'x.css';"
  98:9  x  Disallowed property "color"   property-allowed-list
  99:1  x  Disallowed at-rule "@import"  at-rule-allowed-list
2 problems   exit=2

$ node $SL --config .stylelintrc.json $T/src/main.css "$T/components/**/*.tailwind.css" web/theme.css web/fonts.css   (repo config as committed)
  anchor.tailwind.css 4:3 selector-max-type            exit=2
```

Reading:
- `.stylelintrc.json` matches the adapter's rule values exactly (merged with `overrides` and a top-level `"rules": {}`).
- The only hand-written CSS this cycle changed is `src/main.css` (one `@theme inline` line): 0 findings. `theme.css` and `fonts.css`: 0 findings. The negative control fails as designed.
- The single error is in `anchor.tailwind.css`, **unchanged from stock** and reported identically on the pristine 1.0.5 copy. Pre-existing, not introduced: noted, not blocking. It contradicts the adapter's "stock passes with 0 findings" under Stylelint 17.15 (A1). F reported 0 findings and did not record the Stylelint version; that is probably a 16.x vs 17.x difference in how `:is()` arguments are counted for `selector-max-type`.
- `prettier --check` (the theme's own `npm run format` rule) on every changed component directory and `src/main.css`: all pass.

Alongside the gate (A reads the diff):

| Telltale | Result |
|---|---|
| Raw hex/oklch/rgb in Twig or Canvas inputs | None. Raw colours appear only in `web/theme.css` (L1, where they belong). |
| Arbitrary-value utilities where a scale step or token exists | None in new or changed Twig. (`[&_a]:hover:...` in the new `on-primary` text entry copies the stock `inverted` line; `gap-[2px]` and similar are stock.) |
| Inline `style=` | None. |
| `!important`, ID selectors | None. |
| `max-width` media queries | None. All new utilities are unprefixed-first (`flex-col sm:flex-row`, `md:grid-cols-2 lg:grid-cols-3`, `md:w-64 xl:w-72`, `-mt-8 md:-mt-10 xl:-mt-12`). |
| Edits under `web/themes/contrib/mercury` | None (directory removed by Composer; nothing patched in place). |

## 4. Reuse searches for the two new components

- **`panel`: real.** Checked against source: `card.twig` renders the `<h3>` only inside `{% if is_clickable %}` (line 150 to 152), so a non-link card has no heading, as F says. `card-icon` hard-codes an `<h3>` (would skip a heading level under an h2 page structure). `group` has padding and radius but its `background` enum is `primary/secondary/accent` only, with no border, shadow or overlap. `section` has no negative-margin value (`margin_block_start` is 0 to 128) and its background is a full-bleed layer. The overlap is genuinely absent from Mercury. Alternative considered: extend `group` additively (a neutral background value, border, shadow and an overlap prop). That is three props on a hot component for one use, so a new SDC is reasonable. PASS. The panel's overlap amounts (`-mt-8/-mt-10/-mt-12`) are each at most the hero's `pb-10/13/16`; the coupling between the panel's overlap and the previous section's bottom padding is documented in the prop description but is invisible to the layer model (A5).
- **`stat-badge`: real.** `badge` is a small rectangular label with one text node. `card-icon` has the hard-coded `h3`, an icon block and a radius enum that stops short of a circle. `group` could approximate it (padding, `radius` up to `xl`) but has no `rounded-full` or `aspect-square`, and would need a heading and a text child, giving two levels of markup for a value-over-label. A new SDC is reasonable. PASS.

## 5. Other findings

### WARN

- **W1 `badge` pill is a site-wide change justified by one instance** (row 8). Needs an operator decision or an additive `shape` prop. Not a BLOCK because it is logged as intended L4.
- **W2 `heading` `on-primary` ruling is wrong.** The log says L3 is ruled out because "heading/text set their own colour, so the zone colour is not inherited". `heading` defaults to `text-inherit`, and `section` already puts `text-primary-foreground` on the primary zone. In the served HTML the hero `<h1>` would inherit white with `text_color: default`. So the hero heading's `on-primary` instance prop is unnecessary (L3 inheritance already works); only `text` (default `text-foreground`, its own colour) genuinely needs it. The `heading` variant is needed only in the closing panel because `group background: primary` sets `bg-primary` without the paired `text-primary-foreground`, unlike `section`, which pairs them. The highest correct layer for the closing-panel case is fixing the zone wrapper (`group`'s background variants), not adding a colour variant to each child. Defensible and additive as built, but suboptimal; the log should say so.
- **W3 DOM-inspection evidence is stale** (cites deleted `/page/2`). Re-run against `/page/3`.
- **W4 `heading` and `card-icon` default output is not byte-identical.** Extra whitespace from the new `{% set ... %}` capture; for `heading` with `url` it lands inside the `<a>`. Visually and semantically the same. Fix: use whitespace control (`{%- ... -%}`) inside the new capture blocks so unused-feature output matches stock. Cheap, and it makes "additive" mechanically true.
- **W5 `--radius-pill` has no fallback.** It exists only in `web/theme.css`. Mercury's own `src/theme.css` (the fallback when the web-root file is absent) does not define it, so without the override every button and badge loses its radius silently (invalid `var()`). Either add the token to `src/theme.css` as well (L1 source of the fallback) or record the dependency in the profile.
- **W6 Three L4 log entries lack an explicit "higher layers ruled out" line** (`badge` defers to `button`; `group` and `heading highlight` state the gap but not L1 to L3). The adapter says to record why each higher layer was ruled out.
- **W7 The gate is not reproducible from the repo.** No `package.json`, no pinned Stylelint, no pre-commit hook (F's "left undone" notes this). Until it exists, the gate result depends on which Stylelint is on the machine (see the `anchor` finding).
- **W8 Provenance of values.** Light neutrals `#f4f6f8`, `#dce1e6` and the dark neutrals `#2e2e2e`, `#333333`, `#4a4a4a`, `#f4f6f8` are not in the profile or the badcamp.org stylesheet; they are F's choices, labelled "brand neutrals" in the log. Layer is right; the label is not. Add them to the profile as decided values or mark them provisional.
- **W9 Process: the pristine conversion was never committed as a baseline** (F finding 7). My baseline came from the Composer cache zip. If that cache were cleared, nothing in git or the tree could prove "additive vs stock". O should commit the conversion separately before anything else lands.

### INFO

- **I1** The button radius change reaches the site chrome (navbar, card-pricing embeds), which badcamp#10 owns. Intended by the brand, but worth stating in the PR.
- **I2** `secondary-inverted` is used in place of the mockup's outline button (recorded deviation). Choosing an existing variant is the adapter-preferred route; no new variant needed.
- **I3** `highlight_color: primary` on a primary band would be invisible; the prop description warns, which is sufficient.

## 6. Adapter findings (ambiguities and gaps hit by real changes this cycle)

- **A1 Stylelint version and "stock passes with 0".** With Stylelint 17.15.0 the stock 1.0.5 `anchor.tailwind.css` yields one `selector-max-type` error. The adapter must pin a Stylelint version or raise the ceiling for `:is()`/`:where()` lists. Also, the adapter's two JSON rulesets cannot be dropped in as one config (F's finding 6, confirmed); it should supply a combined `.stylelintrc.json` with `overrides`. And it never says where Stylelint is installed.
- **A2 L4 vs L5 for "add to an existing component".** The hierarchy puts "a component's own Twig utilities" in L5 and "html_cva variant maps" in L4. F logged `heading` highlight as L4 and `card-icon` eyebrow as L5, both additive optional features on a shared component. The adapter needs a rule: additive entry in an existing variant map = L4; new hard-coded element or utilities in an existing component = L5; and say that either affects every instance that opts in (but none that do not).
- **A3 New brand value: token or scale step?** Step 2 says to use a Tailwind step if one fits (`rounded-4xl` is exactly 2rem), step 1 says brand values are tokens. The adapter does not say which wins, nor that a new token also needs the `@theme inline` line (F's finding 12) and a fallback in `src/theme.css` (W5).
- **A4 Zone-awareness is uneven and undocumented.** `section` pairs `bg-*` with `text-*-foreground`; `group.background` does not; `heading` inherits colour, `text` does not. The adapter's L3 text ("choose the zone prop on the wrapper") implies children follow, which is true for some and not others. It should list zone-aware wrappers and the child components that inherit (F's finding 11 hit this; W2 is the consequence).
- **A5 Cross-instance dependencies and instance-level choices are outside the layer model.** The panel's overlap works only because the previous section's `padding_block_end` is at least the overlap; changing one Canvas prop on one instance silently breaks another. The adapter also does not say whether Canvas prop choices on instances need log entries (L5 "one-off prop value" suggests yes, practice here was no).
- **A6 No mechanical check for L4 blast radius.** The gate covers hand-written CSS and token files only; the adapter says "no Twig-aware linter covers" Twig edits. The stock-vs-converted render diff I built (section 2) is the check that makes "additive" verifiable, and the adapter should ship one, plus require the pristine baseline to be committed first (W9). It should also say how A sizes an L1 token change (unbounded by design): list consumers, for example count the utility in served HTML for Home and chrome.
- **A7 The conversion deletes the baseline.** `composer remove drupal/mercury` deletes `web/themes/contrib/mercury`; the baseline survives only in git if the conversion is committed first, or in the Composer cache. The adapter's "commit the rebuilt build as baseline" step should be an explicit O step before F starts (F's finding 7, confirmed).

## Notes for F
None required (PASS). If O wants the WARNs closed before T: W4 (whitespace control), W5 (fallback token), W2 (log ruling; optionally `default` for the hero heading), W1 (decide badge scope).

## Patterns referenced
- `web/themes/custom/mercury/components/section/section.twig` (zone-aware `backgroundColor` map pairing `bg-*` and `text-*-foreground`)
- `web/themes/custom/mercury/components/card/card.twig` (heading renders only when linked)
- `web/themes/custom/mercury/components/group/group.twig` (variant map, `background` without paired foreground)
- Stock baseline: `~/Library/Caches/composer/files/drupal/mercury/8e615fe51c9c97ebd8d6f015a50f897b0c33e8bf.zip` (Mercury 1.0.5)

---

# Round 2 (re-review after F's rework round 1)

Inputs: `F-handoff.md` "Rework round 1", the updated `docs/css-change-log.md` (20 entries, 2 tagged `[removed]`), the updated profile, O's baseline commit `3e99366` (pristine Mercury 1.0.5), and the current adapter text in the playbook worktree (L4/L5 rule, brand-value-is-a-token with three edits, zone-aware wrappers, additivity check, Stylelint pin, L1-token sizing). Judged against that text. Read-only; headless; scratch files only.

## Round 2 verdict: PASS (0 BLOCK, 0 WARN, 3 INFO)

All nine round-1 WARNs are closed. Each one was checked against the tree, not against F's description of it.

## R2.1 Per-change table (every new or changed CSS entry)

Diff basis: `git diff 3e99366 -- web/themes/custom/mercury` (17 files) plus untracked `web/theme.css`, `web/fonts.css`, `components/panel/`, `components/stat-badge/`. `panel` and `stat-badge` are unchanged since round 1 (file mtimes) and their round-1 PASS stands.

| # | Change (log entry) | F's layer | My layer | Verdict | Reason |
|---|---|---|---|---|---|
| 1 | Neutrals in `web/theme.css` `:root` to `#fff #3d3d3d #eee #3d3d3d #eee #3d3d3d #ccc #ccc` | L1 | L1 | PASS | Closes W8. Every value is on the profile's neutral scale, and `#eee`, `#ccc`, `#3d3d3d`, `#fff` also occur in the live badcamp.org stylesheet. The invented `#f4f6f8` / `#dce1e6` are gone. Provenance is per value in the log. |
| 2 | `--primary/--secondary/--accent` (+ foregrounds) | L1 | L1 | PASS | Unchanged from round 1. |
| 3 | `--font-*` plus `fonts.css` / `web/fonts/*` | L1 | L1 | PASS | Unchanged. 6 of 6 font URLs return 200 (re-checked). |
| 4 | `--radius: 0.75rem` | L1 | L1 | PASS | Unchanged. |
| 5 | `--radius-pill: 2rem` in `web/theme.css` | L1 | L1 | PASS | Matches the adapter's rule that a brand value is a token even when `rounded-4xl` equals it. |
| 6 | `--radius-pill` in `src/theme.css` `:root` and `.dark` (lines 33, 80) | L1 | L1 | PASS | Closes W5. This is the second of the adapter's "three edits"; verified in the diff against `3e99366`. |
| 7 | `--radius-pill` in `src/main.css` `@theme inline` | L2 | L2 | PASS | Third of the three edits. The log has it as three entries (web-root L1, fallback L1, L2), which satisfies "log it as two entries" and then some. |
| 8 | `button` base `rounded-lg` to `rounded-pill` | L4 | L4 | PASS (intended site-wide L4) | Unchanged and logged, with L1 to L3 each ruled out. Consumers of the radius in served HTML are in R2.3. |
| 9 | `.dark` block derived from the neutral scale | L3 | L3 | PASS | Closes the dark half of W8. Values `#242424 #eee #3d3d3d #646464` are the profile's stated derivation, and the profile and log both label it provisional. Contrast spot checks: dark foreground on background 13.4:1, on card 9.4:1; light foreground on `#eee` 9.4:1; white on `#0076a8` 5.05:1. Scheme is `light` (`drush config:get`). |
| 10 | `group` `background`: `bg-* text-*-foreground` pairing | L4 | L4 | PASS (intended site-wide L4; effect on existing uses stated and verified) | Matches the adapter's zone-aware text: the wrapper pairs the foreground, with no per-child colour variants. The log states the effect: instances with a background now also set the paired text colour; none exist outside this page. I verified that independently: `canvas_page__components` has `group` only on page 4 (3 instances, one with a background); no other component or template includes `group`; Home is `button` and `cta`. Closes W2. |
| 11 | `text` `text_color: inherit` | L4 | L4 | PASS | Additive enum value. This is the adapter's "an inheriting option" for `text`. It replaces `on-primary`; the hero and panel headings now use `default` (verified in the recipe: no `on-primary` anywhere, `text_color: inherit` x4, `default` x6). |
| 12 | `badge` `shape` (default / pill), radius chosen before `html_cva` | L4 | L4 | PASS | Closes W1. Opt-in per instance; matches the profile's new "badges: pill is opt-in" line. Default output is byte-identical to stock (R2.2). Hero eyebrow uses `shape: pill`. |
| 13 | `card-icon` `eyebrow` | L5 | L5 | PASS | New hard-coded element = L5 under the adapter's rule. Eyebrow now printed on the same line as the `<h3>` (the adapter's stable form). Closes the card-icon half of W4. |
| 14 | `group` `column-sm-row` | L4 | L4 | PASS | Now with an explicit L1 to L3 ruling. Closes W6 for this entry. |
| 15 | `grid` `3 columns, 2 on tablet` | L4 | L4 | PASS | Same. |
| 16 | `heading` `highlight_text` / `highlight_color` | L4 | L4 | PASS | Same; whitespace control in place (R2.2). |
| 17 | `stat-badge`, `panel` (new SDCs) | L5 | L5 | PASS | Unchanged from round 1; the log gains an "L1 to L4 cannot create a component" line. |
| 18 | `[removed]` entries (site-wide badge radius; `on-primary` pair) | n/a | n/a | PASS | Both are truly gone from the tree: `badge.twig` has no unconditional `rounded-pill`, and `grep on-primary` finds nothing in the theme, recipe or log's active section. |

Completeness: no style-affecting change lacks a log entry. Per the adapter, new prop values (`inherit`, `shape`, `column-sm-row`, the grid value, `eyebrow`, `highlight_*`) are all logged; existing-variant instance choices correctly are not.

## R2.2 Additivity, render-diff harness against `3e99366`

Baseline components extracted with `git archive 3e99366 web/themes/custom/mercury/components`, rendered side by side with the same PHP harness as round 1 (`twig/twig` plus the `cva` extension, stubs for `create_attribute`, `attach_library`, icon include) over the cartesian product of every **baseline** enum and boolean value (capped at 6,000 per component) crossed with string samples (HTML-special characters, with and without `url`, `icon`).

```
component  combos  byte-diff
heading     3600        0
text          21        0
card-icon   9720        0
grid         216        0
badge         16        0
button      2304     2304   rounded-lg -> rounded-pill (logged, intended)
group       6000     6000   every one of these combos sets `background`; see below
group, background unset or empty (9000 combos, all other props varied): 0 byte-diff
```

- Group with a background set differs only by the added `text-<zone>-foreground` class (example: `bg-secondary` becomes `bg-secondary text-secondary-foreground`), exactly the logged change. With `background` unset or empty, output is identical to stock across 9,000 combinations.
- New-prop-unset checks, all identical to stock: `badge` `shape` unset and `shape: default`; `card-icon` `eyebrow` empty; `heading` `highlight_text` empty or not found in the text (with and without `url`).
- New-prop-set sanity: `shape: pill` gives `rounded-pill border` (class order intact); `eyebrow` prints directly before the `<h3>`; `highlight_text` wraps the phrase in `<span class="text-accent">`.
- Round-1 W4 (whitespace drift in `heading` / `card-icon`) is closed: 0 of 13,320 combinations differ.
- Build in sync: `tailwindcss --input ./src/main.css --minify` to a scratch path is byte-identical to `build/main.min.css` (69,726 B). `prettier --check` passes on every changed component directory and `src`. (`npm run format:check` also flags `mercury.info.yml`; that file is byte-identical to the baseline commit, so it is a stock Mercury formatting nit, not this cycle.)

## R2.3 Sizing the L1 token changes (the adapter's new step)

Counts of the utility in the served HTML (`/perks-trial` / Home `/` / `/user/login` as a chrome probe):

| Consumer of the token | Perks | Home | Login |
|---|---|---|---|
| `rounded-pill` (`--radius-pill`) | 4 (3 buttons, 1 eyebrow badge) | 1 (CTA button) | 0 |
| `rounded-md` / `rounded-xl` (`--radius`) | 8 / 8 | 2 / 0 | 0 |
| `bg-primary` / `bg-muted` / `bg-background` | 2 / 2 / 8 | 1 / 1 / 1 | 0 |
| `text-foreground` / `border-border` | 16 / 9 | 1 / 2 | 0 |
| `font-body` / `font-sans` | 13 / 3 | 1 / 0 | 0 |

Every Home consumer is meant to change (brand tokens, badcamp#10 scope). The site chrome is not covered by the probe because the login route does not render these utilities; `navbar` and `card-pricing` embed the button component and so inherit the pill radius, which the `button` log entry already states.

## R2.4 Blast-radius gate

```
$ npm run lint:css            (stylelint 16.26.1, root .stylelintrc.json)
exit=0, no output
```

- Version check: `node_modules/.bin/stylelint --version` prints 16.26.1, matching the adapter pin and `package.json`. With the pin, `anchor.tailwind.css` no longer reports (the round-1 finding was the Stylelint 17 artifact, as suspected).
- Negative controls, run on scratch copies so the repo is untouched (`git status` shows `web/theme.css` untouched since the review began):
  - `.card { color: red; }` appended to a `theme.css` copy: `98:9 Unexpected property "color" property-allowed-list`.
  - `@import "x.css";` appended to a `fonts.css` copy: `72:1 Unexpected at-rule "@import" at-rule-allowed-list`. Exit 2 for both.
  - `#x .a .b .c .d { color: red !important; }` appended to a `main.css` copy: four errors, `selector-max-compound-selectors`, `selector-max-id`, `selector-max-specificity`, `declaration-no-important`. Exit 2.
- Telltales: none. Recipe YAML has no hex, oklch, rgb, `style`, arbitrary values or `!important`; new Twig adds only scale-step utilities, all mobile-first. The one new arbitrary-variant string (`[&_a]:hover:opacity-70` in `text` `inherit`) follows the stock `[&_a]:hover:...` pattern in the same map.

## R2.5 `/perks-trial` serves

- `GET /perks-trial` returns 200; `GET /page/4` returns 301 to `/perks-trial`; Home returns 200.
- The page links `/theme.css` and `/fonts.css` (both 200) and no `src/theme.css` or `contrib/mercury` path (0 matches). Served `theme.css` carries `--card:#eeeeee`, `--border:#cccccc`, `--radius-pill:2rem`, and the derived `.dark` block.
- Recipe: `path.alias: /perks-trial` is exported; inputs contain `shape: pill` (hero eyebrow), no `on-primary`, `text_color: inherit` on the zone children; the closing `group` serves `bg-primary text-primary-foreground`. 31 components, as before.
- Not re-run by me: the delete-and-apply recipe round trip (it changes the database, outside my remit); I rely on F's report of it, and the served alias, uuid and component state above are consistent with it.

## R2.6 Remaining findings

- **I4 (INFO)** The reviewed state is still uncommitted (`package.json`, `package-lock.json`, `.stylelintrc.json`, `web/theme.css`, `web/fonts*`, `recipes/`, the two new SDCs, `docs/`). O commits after the remaining gates; nothing to fix.
- **I5 (INFO)** `playwright` is pinned in the root `package.json` but installs no browser; a clean machine needs a browser download step before the axe script runs (F's adapter finding 8). Not a layering matter.
- **I6 (INFO)** Round-1 I1 and I2 stand: the pill button reaches `navbar` and `card-pricing` (badcamp#10 chrome), and `secondary-inverted` replaces the mockup's outline button. Both are recorded; flag the first in the PR.

## R2.7 Adapter findings, round 2

The updated adapter text answered round-1 A1 to A7 and was sufficient to place every change this round without ambiguity. Two small leftovers:

- **A8** The additivity method names `renderInIsolation` via `drush scr` and, separately, `twig/twig` from `vendor/`; I used the latter and it worked on the host without DDEV. Say that either route is acceptable, and that the harness must cross each baseline enum with an explicit "boolean/enum prop unset" case: my first pass could not reveal `group`'s unset behaviour because every stock combination sets `background`, so the "unset is byte-identical" claim needed a separate run.
- **A9** "Sizing an L1 token change" asks for counts on the changed page, Home and the chrome, but gives no way to fetch the chrome (the login route here rendered none of the utilities). Name a chrome route per project in the profile.

## Round 2 notes for F
None. PASS.

---

# Round 3 (re-review after F's rework round 2: the `--ring` token)

Inputs: `F-handoff.md` "Rework round 2", the 4 new entries in `docs/css-change-log.md` (24 entries), the profile's new "Chrome route" line, and the uncommitted diff on top of `9fd5050` (9 files). Judged against the current adapter text. Read-only. I did not toggle the colour scheme or change any config; colours are judged from code and the log's arithmetic (which I recomputed). Note: when I read `mercury.settings scheme` it was `dark`, presumably T's measurement in flight; F's handoff says it was restored to `light`, and O or T should confirm it is back before commit.

## Round 3 verdict: PASS (0 BLOCK, 0 WARN, 3 INFO)

## R3.1 Per-change table

Diff basis: `git diff 9fd5050`. Trace (Pass 1) is in the log entry: `ring-ring` / `border-ring` in the Twig, no `--color-ring` in `@theme inline`, no `--ring` in either `theme.css`, so both utilities silently emitted nothing and the ring fell back to `currentColor`. I confirmed the premise independently: the round-2 `build/main.min.css` contained no `--color-ring`, and the stock `src/theme.css` and `src/main.css` at `3e99366` have no `--ring`.

| # | Change (log entry) | F's layer | My layer | Verdict | Reason |
|---|---|---|---|---|---|
| 1 | `--ring: #242424` (`:root`), `#eeeeee` (`.dark`) in `web/theme.css` | L1 | L1 | PASS | The focus-ring colour is a site-wide semantic value with no existing slot, so a new L1 token. Values are profile neutrals (`#242424` = 600, `#eee` = 100), no invented colour. One colour per scheme is chosen, so no `ring-offset` two-tone hack in Twig. |
| 2 | `--ring` in `src/theme.css` `:root` and `.dark` (fallback) | L1 | L1 | PASS | Second of the adapter's three edits (fallback when the web-root file is absent). The fallback values match the web-root ones, as they did for `--radius-pill`. |
| 3 | `--color-ring: var(--ring)` in `@theme inline` | L2 | L2 | PASS | Third edit. Same pattern as the adjacent `--color-input`. Built CSS now has `.focus-visible\:ring-ring:focus-visible{--tw-ring-color:var(--ring)}` and `.focus-visible\:border-ring:focus-visible{border-color:var(--ring)}`. |
| 4 | `button` and `badge`: `focus-visible:ring-ring/50` to `focus-visible:ring-ring` | L4 | L4 | PASS (intended site-wide L4) | The `/50` is a derivation baked into each component's base class list, so L1 to L3 cannot remove it (the token is already the right colour; no zone changes opacity). A half-opacity ring on the `#0076a8` band is below 3:1, so the component edit is required, and it is part of the same change as the token (F's adapter finding 2). Logged with the L1 to L3 ruling-out line and its blast radius. |

Completeness: the three-edit rule is satisfied and logged as the adapter prescribes. No other component uses `ring-ring`, `border-ring` or `outline-ring` (grepped `components`, `templates`, `lib`), so there are no other L4 edits to make; the only two consumers are the two edited components.

## R3.2 Additivity: render-diff harness against `9fd5050` and `3e99366`

Same harness as rounds 1 and 2 (`twig/twig` plus the `cva` extension, baseline Twig extracted with `git archive`), crossing every enum and boolean of the baseline's schema (capped at 6,000 per component) with string samples. For button and badge I also diffed after normalising the new output back to the old focus string; "unexplained" counts the combinations still different.

```
baseline 9fd5050 (the round-2 state)
component  combos  byte-diff   unexplained
heading    10800        0          0
text          28        0          0
card-icon   9720        0          0
grid         234        0          0
group       6000        0          0
button      2304     2304          0   (focus classes only)
badge         32       32          0   (focus classes only)

baseline 3e99366 (pristine Mercury 1.0.5)
heading     3600        0          0
text          21        0          0
card-icon   9720        0          0
grid         216        0          0
group       6000     6000          0   (logged background pairing only; 9,000 combos with background unset were identical in round 2 and the group Twig is unchanged since)
button      2304     2304          0   (rounded-lg -> rounded-pill + focus classes, both logged)
badge         16       16          0   (focus classes only; shape default and unset otherwise identical)
```

The only difference this round is the focus-class string in `button` and `badge` (the new utility list is also reordered, `ring-[3px]` before `ring-ring`, which is the formatter's sort and is part of the logged edit). Nothing else moved. Against stock, the focus change plus the earlier logged `button` radius and `group` pairing account for every difference.

## R3.3 Gate and build

- `npm run lint:css` (Stylelint 16.26.1, root `.stylelintrc.json`): exit 0, no output. Negative control on a scratch copy (`.btn { outline: 0; }` appended to `theme.css`): `100:8 Unexpected property "outline" property-allowed-list`, exit 2. The new `--ring` lines are custom properties, so they pass the token-file rule as expected.
- Build in sync: rebuilding with the theme's own `tailwindcss --input ./src/main.css --minify` to a scratch path is byte-identical to `build/main.min.css` (69,861 B, matching F's figure; `git diff 9fd5050 --stat` for `build/` is 1 line changed). `prettier --check` passes on `components/{button,badge}` and `src`.
- Recipe, page and alias are untouched this round (not in the diff).

## R3.4 Sizing the L1 `--ring` token by its consumers

The token is read by exactly two generated utilities, `focus-visible:ring-ring` (ring colour) and `focus-visible:border-ring` (border colour), emitted only for the `button` and `badge` components. Counts in the served HTML (class counts only; no colour read from the pages):

| Route | `focus-visible:ring-ring` (opaque) | `ring-ring/50` | `focus-visible:border-ring` | links/buttons on page |
|---|---|---|---|---|
| `/` (profile chrome route) | 1 (the CTA button) | 0 | 1 | 8 |
| `/perks-trial` | 4 (3 buttons, 1 eyebrow badge) | 0 | 4 | 10 |

- Home gets one consumer, which is meant to change (every button's focus ring, logged as intended site-wide).
- The navbar, header and footer links on `/` do not use the token, so their focus ring is unchanged (F reports the browser default `#005fcc`). That includes the dark-scheme chrome contrast that F flagged as pre-existing and owned by badcamp#10.
- The eyebrow badge is a non-link `<span>`, so its `focus-visible` styles are dormant; they apply only to a badge with a `url`.
- Contrast figures recomputed from the hex values (WCAG relative luminance), matching the log: light ring `#242424` on `#fff` 15.52, `#eee` 13.38, `#0076a8` 3.07, `#fdd362` 10.84, `#d44418` 3.43; dark ring `#eee` on `#242424` 13.38, `#3d3d3d` 9.36, `#0076a8` 4.35, `#d44418` 3.90. Each surface a focusable element actually sits on in this page clears 3:1 in its scheme. The log's stated limit (dark `#eee` on an `accent` zone is 1.23:1) is correct and is recorded as a known limit.

## R3.5 Remaining findings

- **I7 (INFO)** `border-ring` also became active. Mercury's markup already carried `focus-visible:border-ring`, and the L2 entry covers it ("activates them"), but the L4 entry mentions only the ring. Effect: on focus the 1px border of a badge or of the `secondary-inverted` button (which has `border-accent`) turns the ring colour. It is the same colour as the ring around it, so it is visually benign, but the log should name it so a later reader is not surprised.
- **I8 (INFO)** Against the button's own fill the light-scheme ring is low (`#242424` on the `#3d3d3d` `secondary-inverted` fill is 1.43:1). The 3:1 test is against the surrounding surface, where it passes, so this is T's call, not a layering matter.
- **I9 (INFO)** The scheme setting read as `dark` mid-review (T's toggle). Confirm it is restored to `light` before O commits; it is config, so it is not in the diff.

## R3.6 Adapter findings, round 3

- **A10** F's finding that Mercury ships utilities (`ring-ring`, `border-ring`) whose tokens it never defines is a genuine adapter gap. The adapter's token list should include `--ring`, and the placement procedure should say: when a previously absent token is added, every utility that references it starts generating, so re-run the additivity check and the contrast check on those consumers. (The "three edits" text covered the token mechanics well; it only lacked the "latent utility" case.)
- **A11** The adapter's three-edit rule worked unchanged for a colour token, not just a radius. No edit needed there.

## Round 3 notes for F
None. PASS.

---

# Round 4 (re-review after F's rework round 3: S's R1 to R6, H1, H2)

Inputs: `F-handoff.md` "Rework round 3", `S-audit.md`, the 13 new log entries in `docs/css-change-log.md` (37 entries), the re-read profile lines (orange shade, content width), and the uncommitted diff on top of `52978de` (27 files). Judged against the current adapter text. Read-only; I did not toggle the colour scheme or change config.

## Round 4 verdict: PASS (0 BLOCK, 2 WARN, 3 INFO)

Every change is at a defensible layer and additive where it touches an existing component. Two WARNs: one is a layering question the operator should decide (W10, the container radius), one is a robustness defect in the new `heading` markup (W11).

## R4.1 Per-change table (every new entry)

| # | Change (log entry) | F's layer | My layer | Verdict | Reason |
|---|---|---|---|---|---|
| 1 | `--secondary-600: #c23d14` in `web/theme.css` `:root` and `.dark` | L1 | L1 | PASS | H2. A brand shade with no Mercury slot; the profile now says where it goes ("the background wherever small text sits on orange"). Changing `--secondary` itself is rightly ruled out (the profile keeps `#d44418` as the secondary). Values recomputed: white on `#c23d14` 5.29:1, on `#d44418` 4.53:1. |
| 2 | `--secondary-600` in `src/theme.css` (both blocks) | L1 | L1 | PASS | Fallback edit of the three-edit rule. |
| 3 | `--color-secondary-600` in `@theme inline` | L2 | L2 | PASS | Third edit; built CSS has the `bg-secondary-600` utility. |
| 4 | `badge` `secondary` style: `bg-secondary` to `bg-secondary-600` (hover follows) | L4 | L4 | PASS (intended site-wide L4) | The profile rule is about small text on orange and badge text is always small, so the variant map is the right layer, not the instance. Other consumers: only this page's one badge. |
| 5 | `button` `outline` variant (`border border-current bg-transparent text-inherit hover:bg-current/10`) | L4 | L4 | PASS | No stock variant is transparent; a new additive variant is the adapter's L4 rule, and `currentColor` keeps it zone-agnostic. Uses a scale step and the zone's foreground, no colour. Opaque `ring-ring` focus inherited. |
| 6 | `heading` `break_after` (empty `span.lg:block`) | L4 | L4 | PASS layer; WARN defect (W11) | Additive optional prop, mobile-first (`lg:` only). Unused-case output is byte-identical (R4.2). Defect below. |
| 7 | `section` `padding_block_start/end` value `96` | L4 | L4 | PASS | The L3 prop is exhausted (enum stopped at 64), so the next step is an additive enum value, the same scale as the existing margin `96`. Byte-identical for every stock value (6,000 combinations). |
| 8 | `text` `divider_above` (`w-full border-t border-current/30 pt-4`) | L4 | L4 | PASS | A raw `<hr>` is stripped by the text format and Mercury has no divider component, so a feature on `text` is the lowest-impact option; `currentColor` + `/30` opacity keeps it token-free and zone-agnostic. Default off, byte-identical (also with `false` and `null`). A new divider SDC (L5) would also have been defensible. |
| 9 | `card-icon` `border_radius: container` (`rounded-lg`) | L4 | L4 | PASS layer; see W10 | Additive enum value. W10 questions whether the value should exist as `rounded-lg`, not whether L4 is right. |
| 10 | `panel` `rounded-xl` to `rounded-lg` | L5 | L5 | PASS layer; see W10 | Own new component. |
| 11 | `stat-badge` wrapper `items-center` | L5 | L5 | PASS | R1. The aspect-ratio box was stretched by the parent flex's default `align-items`; fixing it where the ratio is declared is correct. |
| 12 | "Radius trace" entry (no token change) | L1 | see W10 | WARN | See W10. |
| 13 | Instance props: `width: 90%` (hero, perks), closing `background_color: muted`, margins 0, `padding_block_end: 64`, perks intro wrapped in a `group`, `break_after`, `variant: outline`, `divider_above`, `text-xs`, `radius: lg` | L3 | L3 | PASS | All choose existing or the additive props above. H1 uses Mercury's existing `section` `width` and leaves the site container (badcamp#10) alone, as the profile line requires; only inner content narrows (backgrounds stay full-bleed). R5's `group` wrapper for the intro gap avoids a site-wide `section` header change. I read the recipe: no raw colours, no `style`, no arbitrary values, no `on-primary`. |

## R4.2 Additivity: render-diff harness against `52978de` and `3e99366`

Same harness (baseline Twig from `git archive`, `twig/twig` plus the `cva` extension), now also covering `section`. Every enum and boolean of each baseline schema (capped at 6,000), crossed with string samples; new-prop-unset behaviour tested explicitly. "Unexplained" is the count left after normalising the logged changes back.

```
baseline 52978de                      baseline 3e99366 (pristine 1.0.5)
component  combos  diff  unexplained  combos  diff  unexplained
heading    10800     0       0         3600     0       0
text          28     0       0           21     0       0
card-icon   9720     0       0         9720     0       0
grid         234     0       0          216     0       0
group       6000     0       0         6000  6000       0   (logged bg pairing)
button      2304     0       0         2304  2304       0   (logged radius + ring)
badge         32    16       0           16    16       0   (style=secondary only: bg-secondary-600, logged)
section     6000     0       0         6000     0       0
```

- The only change in existing-component output since `52978de` is the `badge` `secondary` style fill (16 of 32 combinations, exactly the `style: secondary` ones). All other existing components are byte-identical for every baseline value.
- New props, unset: `text` `divider_above` unset, `false` and `null` all identical to before; `heading` with `break_after` and `highlight_text` empty identical (10,800 combinations).
- New props, set: `divider_above` emits `w-full border-t border-current/30 pt-4`; `break_after` emits `<span class="lg:block"></span>` after the phrase; combined with the highlight the page's headline renders `Give $100 or more.<span class="lg:block"></span> Get the <span class="text-accent">[Bundle name]</span>.`, and HTML in the heading text stays escaped.
- Behaviour change inside the new feature: highlight now replaces every occurrence of the phrase, not only the first (logged). Not a stock behaviour.

## R4.3 Gate and build

- `npm run lint:css` (Stylelint 16.26.1): exit 0, no output. Negative controls on scratch copies: `.badge { background: #c23d14; }` in `theme.css` fails `property-allowed-list`; `html body div.a .b .c .d { margin: 0 !important; }` in `main.css` fails four rules (`selector-max-compound-selectors`, `selector-max-specificity`, `selector-max-type`, `declaration-no-important`). Exit 2 both.
- Build in sync: rebuild with the theme's own `tailwindcss --input ./src/main.css --minify` to a scratch path is byte-identical to `build/main.min.css` (70,837 B, F's figure). The new utilities are in the build: `bg-secondary-600`, `border-current`, `bg-transparent`, `hover:bg-current/10`, `border-current/30`, `lg:block`, `pt-16`, `md:pb-20`, `xl:pb-24`. `prettier --check components src` passes.
- `/perks-trial` serves 200; Home serves 200; no `<hr>` in the served page (as the log says).

## R4.4 Sizing the L1 changes by consumers

- **`--secondary-600`** is read by one utility, `bg-secondary-600` (and its `/90` hover), emitted only for the `badge` `secondary` style. Served counts: `/perks-trial` 1 (the hero eyebrow), `/` 0. The `secondary` zone props on `section` and `group` still use `bg-secondary` (`#d44418`); none is used on this site, but they would carry small text on 4.53:1 if ever used (I10).
- **`--radius` and `--radius-pill`:** no token change this round. Radius consumers in the served HTML: `rounded-lg` 8 on `/perks-trial` (6 perk cards, overlap card, closing group) and 0 on `/`; `rounded-xl` and `rounded-2xl` 0 on both.

## R4.5 Findings

### WARN

- **W10 The container radius is fixed with three per-consumer edits where one L1 value would do.** The trace in the log shows `rounded-xl` = `--radius` + 4px, so with the new `--radius: 0.75rem` Mercury's `xl` containers render 16px. F kept `--radius` at 0.75rem and compensated: `group` `radius: lg`, `panel` `rounded-lg`, and a new `card-icon` value `container`. But Mercury's own scale already gives the brand's 0.75rem at `xl` when `--radius` is the **stock 0.5rem** (8px + 4px = 12px). I approved the `--radius: 0.75rem` L1 entry in round 1 on the assumption that "every container derives from it"; the R6c trace shows that assumption was only half true, and that was my miss as well as F's. The highest correct layer is to revert `--radius` to its stock 0.5rem, which delivers 12px on every stock `rounded-xl` container (`card`, `group` `xl`, `hero-side-by-side`, the new `panel` as first written) with no edit, and leaves only the `card-icon` value (its `medium` is a fixed `md:rounded-2xl`; it would need `rounded-xl`, not `rounded-lg`). Net: the `group` instance prop returns to its stock choice, the `panel` edit disappears, and the L1 deviation from stock goes away. Side effect to check before choosing it: `sm/md/lg` return to 4/6/8px for their consumers (the pill button is unaffected). This is a layering and over-engineering finding, not a rendering defect: the page is correct today (12px measured). Left as WARN for the operator because the shadcn reading ("`--radius` is the `lg`, i.e. the container") is also defensible and the current state is verified. If kept as is, the log's radius trace should record why the stock-`--radius` option was not taken.
- **W11 `heading` `break_after` can corrupt the highlight markup.** The break and the highlight are two sequential `replace` calls on the string that already contains the highlight `<span>`, so a `break_after` phrase that matches text inside that markup splits the tag. Reproduced with the new Twig: heading "Get the text now", highlight "now", break after "text" renders `<span class="text<span class="lg:block"></span>-accent">`; break after "class" renders `<span class<span class="lg:block"></span>="text-accent">`. No script injection (all user text is escaped, the inserted markup is a fixed string), but the HTML is broken and the editor sees no warning. Contained for this page (its phrases are `Give $100 or more.` and `[Bundle name]`). Fix: do both replacements in a single `replace` call (Twig's `replace` is a single-pass `strtr`, which never rescans inserted text), or insert placeholders first. Note the single call needs a rule for overlapping phrases (the longer match wins).

### INFO

- **I10** `section` and `group` `background: secondary` zones still pair `#d44418` with white text (4.53:1), which falls short of the profile's "small text on orange" rule if a project ever uses them with small text. Not used here; the profile's rule applies "wherever", so a later story that uses the secondary zone should decide.
- **I11** The explicit `group` wrapper for the perks intro (R5) puts presentation structure into content instead of the shared `section` header. Defensible and local, as the log says; revisit if a second page needs the same gap.
- **I12** Unlogged mention: the recipe re-export is the page only (F deleted an accidental Home export); the file list under `recipes/` shows one page file. Fine.

## R4.6 Adapter findings, round 4

- **A12** Brand radius: when the profile gives a "container radius", compute which Mercury step yields it (`sm/md/lg/xl` are `--radius` -4, -2, 0, +4 px) and set `--radius` so that step matches, before adding per-consumer values. F's finding 3 lists which utilities equal the token; add "and choose the token value from the step your containers actually use (`xl` on most stock components)". This is the lesson of W10.
- **A13** Markup built inside a Twig prop by string replacement must be a single `replace` pass (or placeholders), never sequential, or one phrase can match the other's inserted markup (W11).
- **A14** F's finding 2 stands: the text format strips `<hr>`, so divider-like needs become component features. Worth a line in the reuse-gap list.

## Round 4 notes for F
Optional before commit: W11 (single-pass replace) is a small, local fix with a test case ("text"/"class" as `break_after`). W10 is the operator's call; if taken, it removes the `panel` edit, the `group` radius prop change and one L1 deviation.

---

# Round 5 (final re-review: W10 placement correction, W11 fix)

Inputs: `F-handoff.md` "Rework round 4", the log (39 entries), the uncommitted diff on top of `52978de`. Read-only; the colour scheme was not touched.

## Round 5 verdict: PASS (0 BLOCK, 0 WARN, 2 INFO). Final verdict for the cycle: PASS.

## R5.1 Per-change table (round-4 entries)

| # | Change | F's layer | My layer | Verdict | Reason |
|---|---|---|---|---|---|
| 1 | `--radius` back to the stock `0.5rem` in `web/theme.css` (`:root` line 34, `.dark` line 84) | L1 | L1 | PASS | The placement correction I asked for in W10. Mercury derives `sm/md/lg/xl` as `--radius` -4/-2/0/+4 px, so stock `xl` is 12px, the brand container radius. The web-root file replaces Mercury's, so it must carry the value explicitly; it equals the stock `src/theme.css` (which was never changed: `git diff 3e99366` for `--radius` is empty). `--radius-pill` is separate and untouched. |
| 2 | `panel` back to `rounded-xl` | L5 | L5 | PASS | Matches the committed `52978de` file byte for byte (no diff). |
| 3 | `card-icon` `container` option removed | L4 | n/a | PASS | Enum, Twig and recipe inputs all back to `medium`. |
| 4 | Closing `group` back to `radius: xl` (page input) | L3 | L3 | PASS | The stock choice; the recipe has `radius: xl` and no `container`. |
| 5 | `card-icon` `border_radius: medium` `rounded-xl md:rounded-2xl` to `rounded-xl` | L4 (intended site-wide) | L4 | PASS | The one stock consumer that ignores the token scale (`2xl` is Tailwind's fixed 1rem). L1 cannot move it, L2 has no `--radius-2xl`, and an additive option would leave the default off the brand radius, so editing the variant map and logging it as intended site-wide is the right call. Blast radius verified: only `medium` differs (R5.2), existing uses are the page's 6 perk cards. |
| 6 | Log: old `--radius -> 0.75rem` entry marked `[superseded by L50]` | n/a | n/a | PASS | Marked correctly: it keeps its provenance and original ruling, adds a "Superseded" line with the reason, and points at the live entry (line 50: `--radius ... 0.5rem (stock value restored)`, with trace and ruling). The `container`, `panel` and "Radius trace" entries are tagged `[removed]` with a reason. Only active entry for `--radius` is line 50. (The pointer is a line number, which will drift when the log is edited; harmless.) |
| 7 | `heading` single-pass `replace` for highlight and break | L4 | L4 | PASS | W11 closed (R5.3). |

## R5.2 Additivity: harness against `52978de` and `3e99366`

Same harness and normalisations as round 4, plus the `card-icon` `medium` change normalised on the baseline side. Every baseline enum and boolean crossed with string samples (capped at 6,000 per component).

```
                        vs 52978de                   vs 3e99366 (pristine)
component   combos  diff  unexplained    combos  diff  unexplained
heading     10800     0       0           3600     0       0
text           28     0       0             21     0       0
card-icon    9720  3240       0           9720  3240       0   (one third = border_radius medium)
grid          234     0       0            216     0       0
group        6000     0       0           6000  6000       0   (logged bg pairing)
button       2304     0       0           2304  2304       0   (logged radius + ring)
badge          32    16       0             16    16       0   (style=secondary: logged -600 fill)
section      6000     0       0           6000     0       0
```

- `card-icon` differs only for `border_radius: medium`; `small`, `large` and the other options are identical to both baselines (checked individually). The difference is exactly `md:rounded-2xl` removed.
- Nothing else in an existing component moved this round. Against pristine Mercury, every remaining difference is a logged, intended change.

## R5.3 W11: single-`replace` fix and edge cases

The Twig now builds one map and calls `replace` once over the escaped text (a single `strtr` pass, longest key first, no rescan of inserted markup). When only one feature is on, the same key and value are written twice, which keeps the map static. Rendered with the real template:

| Case | Result |
|---|---|
| Page headline (break + highlight, disjoint) | `Give $100 or more.<span class="lg:block"></span> Get the <span class="text-accent">[Bundle name]</span>.` |
| Highlight only / break only / neither / empty strings / phrases not in the text | correct span, correct break, plain text, plain text, plain text |
| **Previously corrupt:** break "text" with highlight "now" in "Get the text now" | `Get the text<span class="lg:block"></span> <span class="text-accent">now</span>` (intact) |
| **Previously corrupt:** break "class" with highlight "now" in "A class act now" | `A class<span class="lg:block"></span> act <span class="text-accent">now</span>` (intact) |
| Break contains highlight / highlight contains break / same phrase for both | longest match wins: break only / highlight only / break only. No nested or split markup. |
| HTML-special characters (`&`, `<b>`, quotes) | all escaped (`&amp;`, `&lt;b&gt;`, `&quot;`) inside and outside the spans |
| Numeric-like phrases ("10", "20") | correct (no key renumbering, thanks to the duplicate-key form) |
| Repeated highlight phrase | every occurrence wrapped (logged behaviour) |
| With `url` | markup sits inside the `<a>`, still valid |

Unused case: `heading` is byte-identical to both baselines across 10,800 and 3,600 combinations. Remaining nuance (INFO I13): when the two phrases overlap, the longer one silently wins and the other feature is dropped for that heading; it is documented in the Twig comment and is the correct trade-off for a single-pass replace.

## R5.4 Gate, build, serve

- `npm run lint:css` (Stylelint 16.26.1): exit 0, no output. Negative control on scratch copies: `.card { border-radius: 12px; }` in `theme.css` fails `property-allowed-list`; `@import` in `fonts.css` fails `at-rule-allowed-list`; exit 2.
- Build in sync: rebuild to a scratch path is byte-identical to `build/main.min.css` (70,837 B, F's figure). `prettier --check components src` passes.
- `/` and `/perks-trial` return 200; served `theme.css` shows `--radius: 0.5rem` and `--radius-pill: 2rem` in both blocks.
- Sizing the L1 `--radius` change by consumers (it returns to stock, so it is a reduction): `/perks-trial` has `rounded-xl` 8 (6 perk cards, overlap panel, closing group) and `rounded-md` 8 (icon tiles), `rounded-2xl` 0; `/` has `rounded-md` 2 and `rounded-xl` 0. The home page and chrome are back on the stock scale, and no consumer other than `card-icon` `medium` needed a component edit.

## R5.5 Findings

- **I13 (INFO)** See above: overlapping `highlight_text` / `break_after` phrases resolve to the longer one.
- **I14 (INFO)** Round-4 INFO items I10 (secondary zones still `#d44418`) and I11 (explicit `group` wrapper for the perks intro) stand unchanged and need no action for this cycle.

## R5.6 Adapter findings, final

- **A12/A13/A14** from round 4 are confirmed by this round's outcome: choose the Mercury step that yields the brand radius and leave `--radius` at stock (the adapter should say so and name `card-icon` `medium` as the only stock consumer that ignores the scale); build multi-phrase markup in Twig with one `replace` pass; the text format strips `<hr>`.
- **A15** Lesson from W10 for the review process itself: when a log trace shows a token edit needing per-consumer compensations, re-ask whether the token value should simply be stock before accepting the compensations. The placement procedure's step 1 ("does it apply everywhere the token is used?") should add: and does the stock scale already produce the brand value at some step?

## Summary of the cycle (A)

- Round 1: PASS with 9 WARN. Round 2: PASS. Round 3 (`--ring`): PASS. Round 4: PASS with 2 WARN (W10 layering of the container radius, W11 break markup). Round 5: both closed, PASS.
- Every CSS change in the tree is logged, traced and at the highest correct layer; all existing-component edits are additive or logged as intended site-wide L4; the Stylelint gate is clean at the pinned 16.26.1; the build is in sync.

## Round 5 notes for F
None. PASS.
