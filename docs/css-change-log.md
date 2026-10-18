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

[L1] --radius in :root -> 0.75rem  web/theme.css:L32  2026-10-18
  Provenance: profile "containers 0.75rem".
  Ruling: Mercury's rounded-sm/md/lg/xl all derive from --radius (L2), so one L1 edit restyles every container.

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

[L4] heading `highlight_color` variant map (accent/primary/secondary -> text-*) + highlight span  components/heading/heading.twig:L54-L82  2026-10-18
  Ruling: the headline highlights part of its text; the heading component has no inline-highlight prop. L1 ruled out because colours are already tokens, the need is a span; L2 ruled out because no derivation creates an inline element; L3 ruled out because a zone colours a whole area, not one phrase. Colours stay token-backed (text-accent), no raw value. Added to heading rather than a new headline component. Whitespace control keeps output identical to stock when `highlight_text` is unset (rework W4).

[L5] stat-badge (new SDC): circle with value/label  components/stat-badge/stat-badge.twig  2026-10-18  [added]
  Ruling: no existing component is a circular value-over-label badge. Rejected candidates: badge (small rectangular pill, one label), card-icon (hard-coded h3, radius enum stops at 32px, heading max 36px, has an icon block). Token-backed colours only. L1-L4 cannot create a component.

[L5] panel (new SDC): bordered card with slot and optional overlap of the previous section  components/panel/panel.twig  2026-10-18  [added]
  Ruling: nothing in Mercury overlaps the previous section (no negative margins in any enum), and card/card-icon/group cannot render a white card around a level-2 heading plus text. Rejected: card (heading only renders when `url` is set), card-icon (hard-coded h3 skips heading level), group (background enum is primary/secondary/accent only), section (its background is a full-bleed absolute layer). Overlap amounts (-mt-8/-mt-10/-mt-12) are all <= the hero's bottom padding at each breakpoint (pb-10/13/16). L1-L4 cannot create a component.

[L4] badge base radius rounded-sm -> rounded-pill  components/badge/badge.twig  2026-10-18  [removed 2026-10-18]
  Ruling: (kept for history) site-wide pill badges, A review W1. Reverted: the brand sets no badge shape. Replaced by the `shape` entry above.

[L4] text_color `on-primary` (text-primary-foreground) in text and heading maps  components/text/text.twig, components/heading/heading.twig  2026-10-18  [discovered] [removed 2026-10-18]
  Ruling: (kept for history) added after axe found `inverted` = #242424 on the blue band in `.dark` (3.07:1). A review W2: the L3 ruling was wrong for `heading` (it inherits) and the right layer for the closing-panel case is the zone wrapper. Replaced by the `group` pairing entry and the `text` `inherit` entry above; the hero and panel headings now use the default colour.

## Archive

(superseded and removed entries: periodic archival pass)
