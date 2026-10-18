# CSS Change Log

Tracks every CSS-layer override decision per the workflow at
languages/css/css-change-workflow.md. The AI reads this file
at the start of every CSS change request and updates it at Step 4.

Layer tags use the `drupal-canvas-mercury` adapter's L1-L5 mapping: L1 web-root `theme.css` / `fonts.css`
`:root` and theme settings; L2 `src/main.css` `@theme inline` and derived values; L3 `.dark` and zone props
(`section.background_color`); L4 component `html_cva` variant maps and enum props; L5 one component or instance,
or a new SDC.

DOM-inspection gate (Pass 1), re-run in rework round 1 against the current page path, `https://badcamp.ddev.site:8493/perks-trial`
(alias of `/page/4` after the recipe round trip). Tier 1 evidence, `curl -sk .../perks-trial | grep`:
- wrapper chain renders: 4 `section.@container ... cq-full` and 4 `div.container.mx-auto.px-4` (one per top-level section), each holding
  `div.lg:max-w-*` then `div.grid`; `lg:max-w-3/4` appears twice (the overlap and closing sections); no upstream rule caps width below that.
- `panel`: `relative z-10 flex flex-col gap-3 rounded-xl border border-border bg-background p-6 ... -mt-8 md:-mt-10 xl:-mt-12` is a direct
  child of the section's grid, so the negative margin reaches the hero's bottom padding (`pb-10 md:pb-13 xl:pb-16`) and nothing clips it.
- zone pairing: `bg-primary text-primary-foreground` appears once (the closing `group`); the hero `section` emits `text-primary-foreground`
  on its own wrapper plus a separate `bg-primary` layer.
- `rounded-pill` appears 4 times (3 buttons and the one eyebrow badge with `shape: pill`).
L1/L3 entries: N/A, no wrapper involved.

Layer-tag provenance of token values: neutrals come from the profile's badcamp.org neutral scale (`#fff` 000, `#eee` 100, `#ccc` 200,
`#929292` 300, `#757575` 350, `#646464` 400, `#3d3d3d` 500, `#242424` 600). Brand colours come from the profile's brand block
(badcamp.org stylesheet). Nothing in `theme.css` is an invented value.

Trace timing, stated honestly: every entry was traced in the same cycle as its edit. Entries tagged `[removed]` or `[superseded]` were
changed in rework round 1 after A's review (W1, W2, W4, W5, W8); their old rulings are kept for history. The contrast fixes
(`eyebrow` /80, and the original `on-primary`) were found by axe T2 failures rather than predicted.

## Active

[L1] --background/--foreground/--card/--card-foreground/--muted/--muted-foreground/--border/--input in :root -> #fff / #3d3d3d / #eee / #3d3d3d / #eee / #3d3d3d / #ccc / #ccc  web/theme.css:L12-L27  2026-10-18
  Provenance: profile neutral scale: `#fff` (000) background; `#3d3d3d` (500, text) foreground and both *-foreground; `#eee` (100) card and muted surfaces; `#ccc` (200) border and input.
  Ruling: neutrals are site-wide semantic tokens; every consumer should change, so L1 is the only correct layer. Rework round 1 replaced the earlier invented `#f4f6f8` / `#dce1e6`.

[L1] --primary/--secondary/--accent (+ -foreground) in :root -> #0076a8 / #d44418 / #fdd362 (foregrounds #fff, #fff, #3d3d3d)  web/theme.css:L16-L23  2026-10-18
  Provenance: profile brand block (badcamp.org stylesheet): primary #0076a8, secondary #d44418, highlight #fdd362; foregrounds are scale values `#fff` and `#3d3d3d`.
  Ruling: brand colours (badcamp#10). Highlight #fdd362 fills --accent (adapter: a brand colour with no slot goes to --accent). Brand "600" shades (#005d85, #c23d14) have no semantic slot and are NOT used. L2 `/85` opacity steps already derive hover shades.

[L1] --font-sans / --font-body / --font-serif in :root -> Lexend / Inter / Aleo  web/theme.css:L28-L31  2026-10-18
  Provenance: profile fonts line. Files from @fontsource-variable 5.3.0 (OFL 1.1).
  Ruling: brand fonts are token values; @font-face lives in web/fonts.css. No component code touched.

[L1] --radius in :root -> 0.75rem  web/theme.css:L32  2026-10-18  [superseded by L50]
  Provenance: profile "containers 0.75rem".
  Ruling: Mercury's rounded-sm/md/lg/xl all derive from --radius (L2), so one L1 edit restyles every container.
  Superseded: brand container radius maps to Mercury's `xl` step at the stock token; moving the token shifted the whole scale.

[L1] --radius in :root and .dark -> 0.5rem (stock value restored)  web/theme.css:L34, L84  2026-10-18
  Provenance: Mercury 1.0.5 stock `--radius: 0.5rem`; profile "containers 0.75rem".
  Trace (Pass 1): `@theme inline` derives `--radius-sm/md/lg/xl` as `--radius` -4px, -2px, +0, +4px, so at the stock 0.5rem Mercury's `xl` (the step cards, groups and panels use) is exactly 12px = 0.75rem. The brand container radius therefore maps to the `xl` step at the stock token.
  Ruling: rework round 4 (A W10, O decision). Moving `--radius` (L1) to 0.75rem shifted the whole scale (every `sm/md/lg/xl` consumer, including the 6px icon tiles and any `rounded-lg` use) and forced per-consumer compensations. L1 stays at stock; no L2 change either (`--radius-xl` is already the right derivation). Pill radius is a separate token (`--radius-pill`) and is unaffected. Measured on `/perks-trial` at 360 and 1280: overlap panel, 6 perk cards and closing panel 12px; buttons and eyebrow 32px; icon tiles 6px (stock).

[L4] card-icon `border_radius: medium` rounded-xl md:rounded-2xl -> rounded-xl  components/card-icon/card-icon.twig:L27  2026-10-18
  Trace (Pass 1): `medium` = `rounded-xl md:rounded-2xl`; `xl` is `--radius` + 4px (12px at stock), but `2xl` is Tailwind's fixed 1rem (16px), not token-derived, so from `md` up the stock card-icon ignores the radius scale.
  Ruling: the one genuine gap in the radius scale. L1 ruled out: `--radius` is correct at stock and changing it would not move a fixed 2xl; L2 ruled out: `--radius-2xl` is not defined by Mercury and adding one would still leave `medium` off the scale; L3 ruled out: no zone sets radius. An additive new option would leave the default card-icon at 16px, off the brand container radius, so this is a logged intended site-wide L4 change: every `medium` card-icon is now 12px at all widths. Existing uses affected: the 6 perk cards on this page only (Home uses `button` and `cta`). Output differs from stock only when `border_radius` is `medium` (checked: 142 of 400 random combinations, all of them `medium`).

[L1] --radius-pill in :root -> 2rem  web/theme.css:L34 (and .dark L81)  2026-10-18  [added]
  Provenance: profile "buttons 2rem".
  Ruling: no derivation of --radius gives 2rem (L2 steps are --radius +/- 2-4px), and Tailwind's rounded-4xl would hard-code the value in Twig instead of tokenising it. New token, L1.

[L1] --radius-pill in :root and .dark -> 2rem  web/themes/custom/mercury/src/theme.css:L33, L80  2026-10-18  [added]
  Provenance: same value as the web-root entry above.
  Ruling: rework W5. `src/theme.css` is Mercury's fallback when the web-root `theme.css` is absent; without the token every `rounded-pill` (buttons, pill badges) would lose its radius silently. Same L1 layer, second copy so the fallback is complete.

[L2] --radius-pill -> var(--radius-pill) in @theme inline  web/themes/custom/mercury/src/main.css:L34  2026-10-18  [added]
  Ruling: a token only becomes a Tailwind utility (rounded-pill) through the @theme inline map. Same self-referencing pattern as the existing --font-sans line.

[L4] button base radius rounded-lg -> rounded-pill  components/button/button.twig:L10  2026-10-18
  Ruling: every button should be pill-shaped (brand "buttons 2rem"), so this is the variant map, not an instance. L1 ruled out because --radius is shared with cards (changing it would round cards to 2rem); L2 ruled out because no derived radius step equals 2rem; L3 ruled out because no zone or `.dark` rule can target buttons only. Intended site-wide: it also restyles buttons inside `navbar`, `card-pricing` and Home's CTA (badcamp#10 owns the chrome).

[L3] .dark block derived from the neutral scale + brand accents  web/theme.css:L60-L81  2026-10-18
  Provenance: profile dark-scheme rule. background `#242424` (600), foreground `#eee` (100), card and muted `#3d3d3d` (500), border and input `#646464` (400), accents as in :root (`#0076a8`, `#d44418`, `#fdd362`), accent-foreground `#242424`. badcamp.org has no dark scheme, so this is provisional, not a brand decision.
  Ruling: layer tag is L3 (the `scheme` zone); L1 values only. Verified with axe in both schemes.

[L1] --ring in :root -> #242424, in .dark -> #eeeeee  web/theme.css:L28, L78  2026-10-18  [added]
  Provenance: profile neutral scale: `#242424` (600) light, `#eee` (100) dark. Rework round 2, T blocker: focus ring 2.15:1 on the primary band.
  Trace (Pass 1): button markup has `focus-visible:ring-ring/50 border-ring ring-[3px]` -> Tailwind `ring-ring` needs `--color-ring` -> `@theme inline` has no `--color-ring` and neither `theme.css` nor `src/theme.css` defines `--ring` -> the utilities generated nothing and the ring fell back to `currentColor` (the button's own text colour, `#3d3d3d` on blue = 2.15:1).
  Ruling: the focus-ring colour is site-wide, so it is a token (L1). No existing token carries it, so this is a new token. One value must clear 3:1 against every surface a focusable element sits on, both schemes. Light `#242424`: 15.52 on #fff, 13.38 on #eee, 3.07 on #0076a8, 10.84 on #fdd362, 3.43 on #d44418. Dark `#eee`: 13.38 on #242424, 9.36 on #3d3d3d, 4.35 on #0076a8, 3.90 on #d44418. A single colour suffices, so no two-tone `ring-offset` was needed. Known limit: dark `#eee` on an `accent` zone is 1.23:1; no focusable element sits on one here, but one added later would need the two-tone form.

[L1] --ring in :root and .dark -> #242424 / #eeeeee  web/themes/custom/mercury/src/theme.css:L28, L77  2026-10-18  [added]
  Provenance: same values as the web-root entry.
  Ruling: the fallback when the web-root `theme.css` is absent, so the token exists either way (as for --radius-pill). Same L1 layer.

[L2] --color-ring -> var(--ring) in @theme inline  web/themes/custom/mercury/src/main.css:L24  2026-10-18  [added]
  Ruling: a token becomes the `ring-ring` / `border-ring` utilities only through the `@theme inline` map; Mercury's existing markup already uses them, so this activates them. Same pattern as `--color-input`.

[L4] button and badge focus ring `ring-ring/50` -> `ring-ring` (full opacity)  components/button/button.twig:L12, components/badge/badge.twig:L19  2026-10-18
  Ruling: with the token defined, `/50` blends the ring half into the background and drops it below 3:1 (light ring on the blue band would be about 1.8:1). L1 ruled out because the token is already the right colour and the problem is the opacity modifier in the variant map; L2 ruled out because the `/50` is a derivation chosen in the component, not in the map; L3 ruled out because no zone or `.dark` value changes opacity. Intended site-wide: every button and (linked) badge focus ring is now fully opaque. Existing uses affected: all buttons (Home CTA, navbar, card-pricing, this page); before this round the ring was `currentColor`, so none of them had a coloured ring.

[L4] group `background` variants pair their foreground: primary/secondary/accent -> bg-* text-*-foreground  components/group/group.twig:L41-L43  2026-10-18
  Ruling: rework W2. A coloured zone should set its own text colour the way `section` does, so children inherit it. L1 ruled out because changing --foreground or --background would alter every non-zone surface; L2 ruled out because no derived value sets a text colour; L3 ruled out because the zone wrapper IS the group (its variant map is where the pairing belongs), there is no separate zone rule to edit. Intended site-wide change. Existing uses affected: none in git or the database (Canvas content is Home: `button` + `cta`; `group` is used only on this page, 3 instances, one with a background, the closing panel). Instances with a background now also get `text-*-foreground` (previously inherited the page `text-foreground`). Byte-checked: with `background` unset, output is identical to stock.

[L4] text_color `inherit` (text-inherit [&_a]:hover:opacity-70)  components/text/text.twig:L25  2026-10-18
  Ruling: rework W2. `text` always sets its own colour (default `text-foreground`), so a zone's paired foreground cannot flow through; `inherit` lets any zone (section, group) decide. L1 ruled out because no token should recolour all `text` instances; L2 ruled out because there is no derivation that means "inherit"; L3 ruled out because zones already set the colour (`section`, now `group`) and `text` overrides it, so the zone cannot reach it. Additive enum value; default unchanged and byte-identical.

[L4] badge `shape` (default | pill): radius class chosen before html_cva  components/badge/badge.twig:L10-L16  2026-10-18
  Ruling: rework W1, replaces the removed site-wide badge change. The pill is wanted on one instance (hero eyebrow); the brand sets no badge shape. L1 ruled out because the profile says "badges: pill is opt-in per instance, not site-wide"; L2 ruled out because --radius-pill already exists, the question is which component uses it; L3 ruled out because no zone selects badge shape. Additive optional prop; `default` output is byte-identical to stock (class order unchanged).

[L5] card-icon `eyebrow` (text-xs uppercase text-foreground/80)  components/card-icon/card-icon.twig:L112-L116, L142, L151  2026-10-18
  Ruling: the mockup needs a small label above each perk title; no card-icon variant or prop has one, so this is a new optional element in the component. /80 not /70: axe measured 4.47:1 at /70. L1 ruled out because no token adds an element; L2 ruled out because /80 is itself the derivation used; L3 and L4 ruled out because no zone or variant map carries an extra node. Whitespace control keeps output identical to stock when `eyebrow` is unset (rework W4).

[L4] group flex_direction `column-sm-row` (flex-col sm:flex-row)  components/group/group.twig:L8  2026-10-18
  Ruling: two buttons side by side overflow 360px when `row` has no wrap; Mercury's group offers only column or row. L1 ruled out because direction is not a token; L2 ruled out because no scale step switches direction; L3 ruled out because zones colour, they do not lay out. Mobile-first variant (unprefixed = column, sm: = row) added to the existing variant map; additive, byte-identical for stock values.

[L4] grid columns `3 columns, 2 on tablet` (md:grid-cols-2 lg:grid-cols-3)  components/grid/grid.twig:L31  2026-10-18
  Ruling: the brief's perk grid is 2 columns at md and 3 at lg; no stock grid/section variant gives 2 then 3. L1 ruled out because column count is not a token; L2 ruled out because the breakpoints are Tailwind defaults and unchanged; L3 ruled out because zones do not lay out. One new variant on the map instead of a duplicate grid component. Additive, byte-identical for stock values.

[L4] heading `highlight_color` variant map (accent/primary/secondary -> text-*) + highlight span  components/heading/heading.twig:L54-L93  2026-10-18
  Ruling: the headline highlights part of its text; the heading component has no inline-highlight prop. L1 ruled out because colours are already tokens, the need is a span; L2 ruled out because no derivation creates an inline element; L3 ruled out because a zone colours a whole area, not one phrase. Colours stay token-backed (text-accent), no raw value. Added to heading rather than a new headline component. Whitespace control keeps output identical to stock when `highlight_text` is unset (rework W4).

[L5] stat-badge (new SDC): circle with value/label  components/stat-badge/stat-badge.twig  2026-10-18  [added]
  Ruling: no existing component is a circular value-over-label badge. Rejected candidates: badge (small rectangular pill, one label), card-icon (hard-coded h3, radius enum stops at 32px, heading max 36px, has an icon block). Token-backed colours only. L1-L4 cannot create a component.

[L5] panel (new SDC): bordered card with slot and optional overlap of the previous section  components/panel/panel.twig  2026-10-18  [added]
  Ruling: nothing in Mercury overlaps the previous section (no negative margins in any enum), and card/card-icon/group cannot render a white card around a level-2 heading plus text. Rejected: card (heading only renders when `url` is set), card-icon (hard-coded h3 skips heading level), group (background enum is primary/secondary/accent only), section (its background is a full-bleed absolute layer). Overlap amounts (-mt-8/-mt-10/-mt-12) are all <= the hero's bottom padding at each breakpoint (pb-10/13/16). L1-L4 cannot create a component.

[L1] --secondary-600 in :root and .dark -> #c23d14  web/theme.css:L20, L71  2026-10-18  [added]
  Provenance: profile brand block: secondary 600 `#c23d14`, "use as the background wherever small text sits on orange" (white on it 5.29:1; on #d44418 only 4.53:1). Rework round 3, S hold H2.
  Ruling: a brand shade with no Mercury slot is a profile decision; the profile now says where it goes. Changing `--secondary` itself (L1) is ruled out because the profile keeps #d44418 as the secondary colour; no L2 derivation produces it (the `/90` opacity step only blends toward the page). So a second token.

[L1] --secondary-600 in :root and .dark -> #c23d14  web/themes/custom/mercury/src/theme.css:L20, L70  2026-10-18  [added]
  Provenance: same value. Ruling: fallback copy when the web-root `theme.css` is absent, as for --radius-pill and --ring.

[L2] --color-secondary-600 -> var(--secondary-600) in @theme inline  web/themes/custom/mercury/src/main.css:L16  2026-10-18  [added]
  Ruling: the token only becomes a utility (`bg-secondary-600`) through the `@theme inline` map.

[L4] badge `secondary` style background bg-secondary -> bg-secondary-600 (hover /90 follows)  components/badge/badge.twig:L24  2026-10-18
  Ruling: badge text is small (14px), the profile's rule for small text on orange applies to every secondary badge, so the variant map is the place, not the instance. L1 ruled out (changing --secondary would break the brand colour used by large areas); L2 ruled out (no derived step is #c23d14); L3 ruled out (no zone sets a badge's own fill). Intended site-wide: every `secondary` badge. Existing uses: none outside this page (one badge). Foreground stays `text-secondary-foreground` (white).

[L4] button `outline` variant (border-current bg-transparent text-inherit hover:bg-current/10)  components/button/button.twig:L26  2026-10-18
  Ruling: rework R2. The brief and mockup want an outline button on the blue band; the stock variants are all fills (`secondary-inverted` renders a charcoal fill with a yellow border, outweighing the primary CTA). L1 ruled out: no token makes a button transparent; L2 ruled out: opacity steps derive colours, not fills; L3 ruled out: zones set the foreground the button inherits, but not its fill; choosing an existing variant ruled out because none is transparent. New additive variant, zone-agnostic (uses the zone's foreground via currentColor, white on #0076a8 = 5.05:1). Keeps the opaque `ring-ring` focus.

[L4] heading `break_after` (empty `span.lg:block` after the phrase)  components/heading/heading.twig:L66-L93  2026-10-18
  Ruling: rework R3. The headline must break between its two sentences from lg up and wrap naturally below. L1 ruled out: no token carries a line break; L2 ruled out: breakpoints are Tailwind defaults and unchanged; L3 ruled out: zones do not touch inline flow; no existing heading prop or variant breaks text. Additive optional prop; output with no `break_after` and no `highlight_text` is byte-identical to stock. Rework round 4 (A W11): highlight and break are now one `replace({...})` call over the escaped text (strtr: single pass, longest match first), so neither can split the other's markup; when only one is set its entry is written twice with the same key and value. A break phrase that contains the highlight phrase wins (no nested span). Each replaces every occurrence of its phrase. Checked: none, highlight only, break only, both, overlapping and HTML-special cases render correctly, and unused output is byte-identical to stock (400 combinations).

[L4] section `padding_block_start` / `padding_block_end` value `96` (pt/pb-16 md:20 xl:24)  components/section/section.twig:L42, L49  2026-10-18
  Ruling: rework R4. The hero needs about 96px below its content so the overlapping card leaves ~48px clear (overlap 48px at xl). L3 prop exhausted: the enum stops at 64 (and 64 minus the 48px overlap left 16px). L1 and L2 ruled out: spacing scale unchanged, `--spacing` is the base unit, not a per-section value. Same scale as the existing margin `96`. Additive enum value; byte-identical for the stock values.

[L4] text `divider_above` (w-full border-t border-current/30 pt-4)  components/text/text.twig:L37-L38, L49  2026-10-18
  Ruling: rework R6b. The mockup has a rule above the small notes. A raw `<hr>` in the rich text is stripped by the text format (verified: 0 `<hr>` in the served HTML), and Mercury has no divider component. L1 ruled out: no token draws a rule; L2 ruled out: `/30` is the derivation used on `currentColor`; L3 ruled out: zones colour, they do not add lines. Additive boolean, zone-agnostic (current colour), default off and byte-identical to stock. `w-full` so it spans the group, not the text width.

[L4] card-icon `border_radius` value `container` (rounded-lg)  components/card-icon/card-icon.twig:L29  2026-10-18  [removed 2026-10-18]
  Ruling: rework R6c. See the radius trace below. `medium` is `rounded-xl md:rounded-2xl`; no stock value equals `--radius`.
  Removed: not needed once `--radius` is stock; containers use their natural `xl`.

[L5] panel corner radius rounded-xl -> rounded-lg  components/panel/panel.twig:L3  2026-10-18  [removed 2026-10-18]
  Ruling: rework R6c, own new component. See the radius trace below.
  Removed: reverted to `rounded-xl` (12px at the stock token).

[L5] stat-badge wrapper `items-center` (stops the cross-axis stretch)  components/stat-badge/stat-badge.twig:L15  2026-10-18
  Ruling: rework R1. The wrapper is `flex` (row); its child's default `align-items: stretch` stretched the circle to the grid cell's height and overrode `aspect-square` (237x321 at 768, 288x315 at 1280). Fixed in the component, where the aspect ratio is declared, and it centres the circle vertically in the 33% column. L1-L4 ruled out: no token, scale step, zone or existing variant controls alignment inside a new SDC. Measured: 224, 237, 288 px circles at 360, 768, 1280.

[L1] Radius trace (no token change): containers render 0.75rem by choosing the scale step that equals `--radius`  web/theme.css:L32 unchanged  2026-10-18  [removed 2026-10-18]
  Trace (Pass 1): `rounded-xl` -> `--radius-xl` -> `calc(var(--radius) + 4px)` (`src/main.css` `@theme inline`) = 16px; card-icon `medium` = `rounded-xl md:rounded-2xl` where `2xl` is Tailwind's fixed 1rem, not token-derived; `group` radius `xl` and my `panel` used `rounded-xl`.
  Ruling: L1 `--radius: 0.75rem` is already correct. L2 ruled out as the place to fix it: setting `--radius-xl` to `var(--radius)` would make `xl` equal `lg` for every other `rounded-xl` consumer (`card`, `hero-side-by-side`), collapsing the scale. L3 ruled out: no zone changes radius. The exact step is `rounded-lg` (= `--radius`). Fixed per consumer: `group` instance prop `radius: lg` (existing enum, no code), `panel` L5 above, `card-icon` additive `container` value above. Measured 12px on the overlap card, perk cards and closing panel at all widths.
  Removed: its conclusion (choose `lg`) came from moving the token; replaced by the stock-token entry above.

[L3] Instance prop choices with no code (Canvas inputs): hero and perks `width: 90%` (H1), closing section `background_color: muted`, margins 0, `padding_block_end: 64` (R6a), perks intro heading and text wrapped in a `group` (column, gap md) (R5), hero heading `break_after`, hero secondary button `variant: outline`, closing notes one `text` with `divider_above`, `text_size: text-xs`, closing group `radius: xl` (the default step, 12px at the stock token)  recipes/badcamp_pages/content/canvas_page/7562db90-....yml  2026-10-18
  Ruling: all choose existing (or the additive) variants, so the highest layer is the Canvas prop. L1 ruled out: none of these should apply site-wide; L2 ruled out: no derivation involved. H1: narrowing uses Mercury's existing `section` `width` prop, not the site container (badcamp#10 owns that); only inner content narrows, the zone backgrounds stay full-bleed. R5: the section header slot puts no gap between children, so a `group` supplies the gap with no code (site-wide change to `section` header ruled out as broader than needed).

[L4] badge base radius rounded-sm -> rounded-pill  components/badge/badge.twig  2026-10-18  [removed 2026-10-18]
  Ruling: (kept for history) site-wide pill badges, A review W1. Reverted: the brand sets no badge shape. Replaced by the `shape` entry above.

[L4] text_color `on-primary` (text-primary-foreground) in text and heading maps  components/text/text.twig, components/heading/heading.twig  2026-10-18  [discovered] [removed 2026-10-18]
  Ruling: (kept for history) added after axe found `inverted` = #242424 on the blue band in `.dark` (3.07:1). A review W2: the L3 ruling was wrong for `heading` (it inherits) and the right layer for the closing-panel case is the zone wrapper. Replaced by the `group` pairing entry and the `text` `inherit` entry above; the hero and panel headings now use the default colour.

## Archive

(superseded and removed entries: periodic archival pass)
