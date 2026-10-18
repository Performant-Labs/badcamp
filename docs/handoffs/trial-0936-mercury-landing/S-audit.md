# S audit (T3): trial-0936-mercury-landing

S (Spec Auditor), Website Front-End Pipeline, adapter `drupal-canvas-mercury` (unmerged worktree copy). Branch `trial-0936-mercury-landing` at `52978de`. Page `https://badcamp.ddev.site:8493/perks-trial`, main content only (header and footer are out of scope, badcamp#10). Headless only: scripted Playwright 1.64.1 with the cached `chrome-headless-shell` 1243 via `executablePath` (the Playwright MCP server did not connect). No code, content or git state changed. The only config change was the temporary scheme toggle, restored (see the end of this file).

## Verdict: REWORK

6 REWORK items (1 major, 5 minor), 2 ADVISORY-HOLD items for the operator, everything else PASS or ACCEPTED. Every WCAG text-contrast measurement passes in both schemes at every width, and there is no overflow. The REWORK items are layout fidelity: the "circular" badge is not a circle at 768 and 1280, and the hero's secondary button is a filled charcoal pill, not the outline button the brief asks for.

## Preconditions

| # | Precondition | Result |
|---|---|---|
| 1 | A handoff shows PASS | PASS (`A-review.md`: 0 BLOCK, 9 WARN, 3 INFO; F's rework rounds 1 and 2 on top) |
| 2 | T shows zero blocking issues | PASS (`T-handoff.md` re-verification after F's round 2: PASS 4 of 4) |
| 3 | A real browser renders the page | PASS (headless Chromium, 200, `/theme.css` and `/fonts.css` applied, Lexend and Inter loaded) |
| 4 | Pixel-level diffs at the brief's breakpoints | Produced, but **informative only** (see "About the pixel diff"). The binding comparison is per-section geometry measured in the browser. |
| - | No horizontal overflow at 360 (checked first) | PASS: `scrollWidth 360 = innerWidth 360`, 0 elements in `<main>` past either edge, light and dark. Also 768/768 and 1280/1280. |
| - | Preview sanity check (is the mockup itself defective?) | PASS. The mockup has one H1, 44px-plus targets, and readable contrast. It shows one desktop frame only, so 360 and 768 are judged against the brief's breakpoint rules. |

### About the pixel diff

`compare -metric AE -fuzz 20%` of the mockup's main area against the live 1280 render, scaled to the mockup's width, differs on 48% of pixels. That number says nothing useful. The profile swaps the whole palette (navy and orange become badcamp.org blue, yellow and orange), the amount pills are dropped on purpose (badcamp#14), and the mockup frame is narrower than any brief breakpoint. A layout-only reference can't be judged by pixel delta, so every section below is judged by measured boxes, gaps, sizes and the side-by-side image. This gap is in the core role and the adapter: see adapter finding 1.

## Per-section audit (light; dark has the same geometry)

Sizes are CSS px from `getBoundingClientRect` / `getComputedStyle` (raw data: `S-artifacts/S-{light,dark}-metrics.json`). "Mockup" proportions are scaled from the 874px-wide mockup frame.

### 1. Hero

| Item | Mockup intent | Live 360 / 768 / 1280 | Class |
|---|---|---|---|
| Eyebrow pill | small outlined pill, uppercase, top left | pill (radius 32px), filled `#d44418`, white Lexend 14px, top left at all widths | ACCEPTED (pill shape matches; fill colour is the brand's, and the brief is silent on fill). Colour: see H2 |
| Headline, two lines | "Give $100 or more." / "Get the [Bundle name]." (break between the sentences) | Lexend 600, 40/48/60px, white. 3 / 3 / 2 lines. At 1280 the break falls **inside** the second sentence: "Give $100 or more. Get" / "the [Bundle name]." | **REWORK R3** |
| Highlighted bundle name | brand accent on the band | `#fdd362` on `#0076a8`: 3.53:1, large text (40px and up, 600), needs 3:1 | PASS |
| Sentence | one line or two, under the headline | Inter 18/20/24px white, 5.05:1 | PASS |
| Buttons | solid "Donate $100" plus an **outline** "See the six perks", side by side | Donate: yellow fill, `#3d3d3d` text, pill, 7.59:1 (light). See the six perks: **filled `#3d3d3d`** with yellow text and a 1px yellow border (Mercury `secondary-inverted`). Stacked at 360, side by side from 768 (`sm:flex-row`) | Donate PASS. Stacking at 360 ACCEPTED. **REWORK R2** for the second button |
| Circular badge on the right (desktop) | perfect circle, vertically centred beside the copy | 360: **224×224 circle**, centred below the buttons. 768: **237×321**, 1280: **288×315**, a vertical stadium shape, not a circle | 360 placement ACCEPTED. **REWORK R1** (major) at 768 and 1280 |
| Hero rhythm | balanced: eyebrow about 27px below the band top, card about 33px below the buttons (mockup scale) | 64px from the band top to the eyebrow, but only **16px (1280) / 12px (768)** from the buttons and the badge to the overlapping card | **REWORK R4** |
| Band colour | navy | brand primary `#0076a8` | ACCEPTED (profile: no navy in the brand) |

### 2. "Where your donation goes" (overlap card)

| Item | Mockup intent | Live | Class |
|---|---|---|---|
| Overlaps the bottom of the hero | about 32px into the band, centred, inset narrower than the content column | overlaps 32 / 40 / 48px; 1280 inset at 75% of the column (172 to 1108; mockup is about 79%); full width at 360 and 768 (`lg:max-w-3/4`) | PASS |
| Card treatment | white, light border, rounded, soft | white `#fff`, 1px `#ccc`, 16px radius, `shadow-md`; dark: `#242424` with `#646464` border | PASS. Radius: **REWORK R6** |
| Heading and text | Lexend heading, short paragraph | Lexend 600 24/24/32px `#3d3d3d` (10.86:1); Inter 16/18/20px (10.86:1) | PASS |

### 3. "What you get"

| Item | Mockup intent | Live | Class |
|---|---|---|---|
| Heading and intro | large heading, intro line a clear step below | Lexend 600 32/40/48px. The intro starts directly under the heading's line box, a **0px gap** (the descenders of "you get" touch the intro line at 1280) | **REWORK R5** |
| Grid columns | 1 / 2 / 3 (brief) | 1 col at 360, 2 at 768, 3 at 1280; gaps 16/24/32px; equal-height cards (220/236/236) | PASS |
| 3×2 at desktop | 6 cards, two rows of three | 3×2 at 1280 (rows at y 824 and 1092) | PASS |
| Icon tile | small square tile, top left | 48×48, 10px radius, yellow `#fdd362` with a blue `#0076a8` icon (3.53:1, decorative, `aria-hidden`) | ACCEPTED (brand mapping of the mockup's navy tile with an orange icon) |
| PERK n eyebrow | tiny uppercase label | Lexend 12px 600, tracking 0.6px, `foreground/80`: 5.94:1 light, 8.97:1 dark | PASS |
| Title and text | bold title, 1–2 lines of body | Lexend 600 24px; Inter 16px; 10.86:1 light, 13.38:1 dark | PASS |
| Card surface | white card on a light-grey page | white on `#eee` muted zone, 1px `#ccc`, 16px radius | PASS. Radius: **REWORK R6** |

### 4. Closing panel

| Item | Mockup intent | Live | Class |
|---|---|---|---|
| Panel | rounded dark panel, inset like the overlap card, sitting on the same light-grey page | blue `#0076a8` group, 16px radius, 32px padding, inset 75% at 1280. The section has **no zone**, so the page turns from grey to white between "What you get" and the panel. Section padding is 0/0, so the panel ends 24px above the footer | Colour ACCEPTED (brand). **REWORK R6** (zone and padding) |
| Heading, sentence | larger heading, one sentence | Lexend 600 24/24/32px white 5.05:1; Inter 16/18/20px 5.05:1. "Choose an amount." dropped | PASS; the copy change is ACCEPTED (no picker, badcamp#14) |
| Amount pills plus "Donate$100" | pills, a helper line, Donate | a plain "Donate" button (yellow, 7.59:1) to `/donate` | ACCEPTED (badcamp#14; brief) |
| Two small notes | visibly smaller, below a **divider** | Inter 14/16/18px, only 2px smaller than the sentence at 1280; **no divider** | **REWORK R6** |

### Page-wide

| Item | Result | Class |
|---|---|---|
| Content column and gutters | Mercury `container` plus `px-4`: 16px gutters at every width, so at 1280 the content runs 16 to 1264 (97.5% of the viewport). The mockup has a centred column with about 7.5% gutters, aligned with the header. Inner proportions (inset panels at 75–79% of the column) do match | **ADVISORY-HOLD H1** |
| Left alignment | hero copy, "What you get" heading and grid share x = 16; both inset panels share x = 172 (1280) | PASS |
| Type hierarchy | H1 60 > H2 48 ("What you get") > H2 32 (cards and closing) > H3 24 > body 16–20 > eyebrows 12–14 | PASS (the mockup's closing heading is a little larger than its overlap heading; the live page makes them equal. Too small to grade) |
| Brand fonts | every heading, button, eyebrow and badge is Lexend; every paragraph is Inter; Aleo unused; self-hosted | PASS |
| Brand palette | only profile values render: `#0076a8`, `#d44418`, `#fdd362`, `#3d3d3d`, `#fff`, `#eee`, `#ccc`; dark adds `#242424`, `#3d3d3d`, `#646464`, `#eee` | PASS |
| Brand radii | buttons and pills 32px (2rem) PASS. Containers render **16px**, not the profile's 0.75rem (12px) | **REWORK R6** |
| Orphaned single words | "perk.]" alone on line 2 of every card at 768 and 1280; "goes" alone at 360 | ACCEPTED for now (bracketed placeholder copy, brief says keep it). Re-check with real copy (badcamp#9) |
| Dark scheme | same layout; all colours from the derived `.dark` block; nothing unreadable | PASS (profile marks dark as provisional) |

## Exhaustive checklist (core role)

**Visual fidelity**, per breakpoint (360 / 768 / 1280):

| Item | 360 | 768 | 1280 |
|---|---|---|---|
| Layout and position vs reference | PASS (mobile stack is the brief's) | FAIL (R1 badge shape) | FAIL (R1, R3) |
| Spacing | FAIL (R5) | FAIL (R4, R5) | FAIL (R4, R5, R6 closing section) |
| Colour tokens | PASS | PASS | PASS |
| Typography (family, size, weight, line-height, letter-spacing) | PASS | PASS | PASS |
| Imagery | N/A (no images; icons are inline SVG) | N/A | N/A |
| Component treatment | FAIL (R2 outline button; R6 radius) | FAIL (R2, R6) | FAIL (R2, R6) |
| Hover / focus / active | Focus PASS (T measured 3.07:1 light, 4.35:1 dark; U saw the ring in all 6 cells); hover not in the mockup, not graded | same | same |
| No overlap, clipping or truncation | PASS (the card overlap is intended and lies above the band, `z-10`) | PASS | PASS |
| No horizontal overflow | PASS (360/360) | PASS | PASS |
| No orphaned single words | ACCEPTED (placeholder copy) | ACCEPTED | ACCEPTED |

**WCAG / accessibility** (light and dark, all widths):

| Item | Result | Evidence |
|---|---|---|
| Body text ≥ 4.5:1 | PASS | Lowest is the eyebrow, white on `#d44418`: **4.53:1** at 14px (see H2). Then hero and closing text, white on `#0076a8`: 5.05:1. Card eyebrow 5.94:1 (light) |
| Large text ≥ 3:1 | PASS | Highlight `#fdd362` on `#0076a8`: 3.53:1 (40–60px, 600). Headings on the band 5.05:1 |
| Focus ring ≥ 3:1 | PASS | T's re-verification: `#242424` on `#0076a8` 3.07:1 (thin margin), `#eee` on `#0076a8` 4.35:1 dark; all 3 main-content links, 360 and 1280 |
| Link text ≥ 4.5:1 | PASS | Donate 7.59:1 light / 10.84:1 dark; See the six perks 7.59 / 10.84 |
| Touch targets ≥ 44×44 | PASS | Buttons 162×47, 200×49, 113×47 at 360; 174×53, 217×55, 120×53 at 1280 |
| Heading hierarchy | PASS | One H1, then H2, H2, H3×6, H2; no skips (T confirmed) |
| Landmarks | PASS | `main`; the section header is a `header` (T confirmed) |
| Semantic interactive elements, ARIA | PASS | 3 `<a href>` controls; the eyebrow `span` carries `aria-label`; icons `aria-hidden` |
| Focus order matches reading order | PASS | U: Donate $100, See the six perks, Donate |
| Images have alt text | N/A | no `<img>` in main; decorative SVGs are hidden |

**Responsive:** 1 → 2 → 3 columns PASS; single column with no overflow at 360 PASS; mobile type steps (h1 40 → 48 → 60) PASS; hero two-column from `md` with the badge beside the copy PASS for placement, FAIL for shape (R1).

## REWORK items

| # | Severity | Item | Measured | Where the fix belongs (F to confirm the layer) |
|---|---|---|---|---|
| R1 | major | Stat badge is not a circle at 768 and 1280 | 237×321 and 288×315 (circle only at 360, 224×224) | `stat-badge.twig` (new component, L4): the outer `flex w-full justify-center` row stretches the child on the cross axis, which overrides `aspect-square`. Stop the stretch (`items-center` on the wrapper or `self-center` / `h-fit` on the circle). Also centre it vertically in the 33% column, as in the mockup |
| R2 | minor | "See the six perks" is a filled charcoal pill (`secondary-inverted`), not the brief's outline button | `bg #3d3d3d`, yellow text, 1px yellow border. On the blue band it outweighs the primary CTA, which reverses the mockup's hierarchy | Mercury has no outline variant. Additive `button` variant (L4): transparent fill, `currentColor`/white text and border on the zone. White on `#0076a8` = 5.05:1. Keep the opaque `ring-ring` focus |
| R3 | minor | Headline break | 1280: "Give $100 or more. Get" / "the [Bundle name]."; the two-sentence two-line structure is lost | Let the heading break after the first sentence from `md` up (an additive line-break or `max-w` option on `heading`, L4), or a Canvas-side break if the component allows one. 360 may wrap to 3–4 lines |
| R4 | minor | Hero bottom spacing is cramped and unbalanced | 64px above the eyebrow, but 16px (1280) / 12px (768) from the buttons and badge to the overlapping card | L3 prop: raise the hero section's `padding_block_end`, or reduce the panel's `-mt-*` overlap so about 48px stays clear at 1280. Re-check after R1, which changes the badge height |
| R5 | minor | No gap between "What you get" and its intro | 0px between line boxes; descenders touch the next line at 1280 | Section header slot: a gap between the header slot's children (`section` header, L4), or a margin prop on the heading if one exists |
| R6 | minor | Closing area and container radius | (a) The closing section has no `muted` zone and 0/0 padding, so the page goes grey → white → footer, 24px below the panel. (b) The notes have no divider and are only 2px smaller than the sentence. (c) Every container renders a 16px radius (`rounded-xl` = `--radius`+4px; card-icon `md:rounded-2xl` = 1rem, not token-derived), not the profile's 0.75rem | (a) L3 Canvas props on section 4: `background_color: muted`, real block padding. (b) A divider before the notes (an existing Mercury divider, if any; otherwise a `group` border-top) and one step smaller text. (c) Use `lg` (= `--radius`) on the `group` and `panel`; `card-icon` has no `--radius` option, so add one (L4) |

## ADVISORY-HOLD items (operator decision)

| # | Item | Why it needs you |
|---|---|---|
| H1 | Desktop content width. Mercury's `container` gives 16px gutters at every width, so at 1280 the content is 1248px wide; the mockup has a centred column with about 7.5% gutters, aligned with its header | Fixing it is site-wide (container / gutter tokens) and changes the header and footer too (badcamp#10). Decide: accept Mercury's full-width container, or set a site max-width under badcamp#10 |
| H2 | Eyebrow is white 14px text on secondary `#d44418` at 4.53:1 | The profile says secondary 600 `#c23d14` is "for small text on orange", and this is small text on orange. F read that line as ambiguous (F finding 12). If it means "the background behind small text", the eyebrow should be `#c23d14` (5.29:1, more margin). One token or variant choice; your call on the meaning |

## ACCEPTED (intended, documented deviations)

- Palette: navy → `#0076a8`, mockup orange → `#fdd362` highlight and badge, navy icon tile → yellow tile with a blue icon (profile: layout only, brand colours).
- No amount pills, no "Choose an amount.", one plain "Donate" (brief, badcamp#14).
- Header and footer not audited (badcamp#10). The `/donate` 404 is a known gap (U).
- Hero buttons stacked at 360; badge centred below the buttons at 360; overlap card full width below `lg`.
- Eyebrow filled rather than outlined (brief silent on fill); colour subject to H2.
- Orphaned words in bracketed placeholder copy (copy not decided, badcamp#9).

## Screenshots and evidence (`S-artifacts/`)

| File | What |
|---|---|
| `S-light-360-main.png`, `S-light-768-main.png`, `S-light-1280-main.png` | `<main>` element, light (360 taken first, after the overflow check) |
| `S-dark-360-main.png`, `S-dark-768-main.png`, `S-dark-1280-main.png` | `<main>` element, dark |
| `S-{light,dark}-{360,768,1280}-full.png` | full page including chrome, for context |
| `S-sidebyside-1280-reference-vs-live.png` | mockup main area (left) next to live 1280 scaled to the same width (right) |
| `S-diff-1280-reference-vs-live.png` | ImageMagick `compare` output (48% AE at fuzz 20%; informative only, see above) |
| `S-light-metrics.json`, `S-dark-metrics.json` | per-width boxes, computed type and colour for every section, per-text-element contrast (fg/bg resolved through `elementsFromPoint`, because section backgrounds are absolute `z-[-1]` layers) |

All captures at device scale factor 1, viewport height 900, after `document.fonts.ready` plus 400ms.

## Adapter and pipeline findings (what affected the visual result or S's work)

1. **A layout-only reference can't be pixel-diffed.** The core S role makes pixel diffs mandatory and binding per section, but this profile's mockup sets layout only and the brand swaps every colour, so the delta is mostly palette (48%). The adapter or profile should say how S compares a layout-only reference: per-section geometry (boxes, gaps, column counts, aspect ratios) plus a side-by-side, or a greyscale/edge diff. It should also say how to crop out-of-scope chrome from the reference. The core default viewports (375) also disagree with the NFR and brief (360).
2. **The badge defect passed every earlier tier.** F's own screenshots show the stadium shape at 768 and 1280; A, T and U don't measure shape. Add to the adapter's new-component guidance: inside a flex row, a child with `aspect-ratio` is stretched by the parent's default `align-items: stretch`; check the rendered box of any fixed-shape element at every breakpoint.
3. **Mercury's radius scale never renders the brand radius on containers.** `rounded-xl` = `--radius` + 4px; `card-icon`'s `medium` uses `md:rounded-2xl` (Tailwind's fixed 1rem); `group` and `panel` default to `xl`. Setting `--radius` to the brand value (L1) doesn't make cards 0.75rem. The adapter's L1 token notes should say which utilities equal `--radius` (`rounded-lg`) and which are offsets.
4. **Known gaps in 1.0.5, to add to the adapter's list:** no outline button usable on a coloured zone (`*-inverted` are fills, so F picked a charcoal fill); no line-break control in `heading`; the `section` header slot puts no gap between a heading and its text; no divider component for notes inside a `group`.
5. **Mercury's `container` is full-bleed minus 16px at every width.** A mockup with a centred max-width column needs a site-level decision (H1). The adapter should say so up front, because it touches the chrome.
6. **Scheme toggling worked as documented** (`config:set` + `drush cr`, restore, `config:get`); the shared-state rule ("only one agent toggles") was observed.

## Scheme restore

Dark: `ddev drush config:set mercury.settings scheme dark -y` + `ddev drush cr`. Restored: `ddev drush config:set mercury.settings scheme light -y` + `ddev drush cr`. Then `ddev drush config:get mercury.settings scheme` printed `'mercury.settings:scheme': light`, and the served `<html>` has no `dark` class. Scratch scripts are in the session scratchpad, not the repo.

---

## Re-audit (after F's rework round 3 and O's decisions on H1 and H2)

Base `52978de` plus F's uncommitted round 3 (see `F-handoff.md`, "Rework round 3"). O wrote the H1 and H2 decisions into `docs/frontend/frontend-pipeline-profile.md`. Same method as the first audit: headless Playwright (cached `chrome-headless-shell` 1243), 360 first with the overflow check, then 768 and 1280, light and dark. Each section was judged by geometry measured in the browser, plus per-element text contrast (`elementsFromPoint` backgrounds). A was reviewing code in parallel and did not touch config; S was the only agent toggling the scheme.

### Verdict: PASS

All six REWORK items are fixed, and both holds were applied as O decided. There are no new defects, every text contrast passes in both schemes, and nothing overflows.

### Item status

| Item | Status | Evidence (light; dark identical unless noted) |
|---|---|---|
| R1 badge circle | **FIXED** | 224×224 / 237×237 / 288×288 at 360 / 768 / 1280. At 768 and 1280 its centre lines up with the copy block's centre (0px offset); at 360 it sits centred below the buttons |
| R2 outline button | **FIXED** | "See the six perks": transparent fill, white text, 1px white border, pill; 5.05:1 on `#0076a8`. The solid Donate is now clearly the primary CTA, as in the mockup |
| R3 headline break | **FIXED** | 1280: "Give $100 or more." / "Get the [Bundle name]." (2 lines, break between the sentences). 768: break after "more." then a natural wrap (3 lines). 360: natural wrap (3 lines), acceptable on mobile |
| R4 hero rhythm | **FIXED** | Buttons to the overlapping card: 48px at 1280 (was 16), 40px at 768 (was 12). Badge to card: 61 / 82px. Overlap unchanged at 32 / 40 / 48px |
| R5 heading to intro | **FIXED** | 16px between "What you get" and the intro at every width (was 0) |
| R6a closing zone | **FIXED** | Section backgrounds: band, muted, muted, muted (`#eee` light, `#3d3d3d` dark). The page stays one tone down to the footer, with 72 / 84 / 96px below the panel |
| R6b notes | **FIXED** | Divider above the notes: 1px white at 30%. Notes 12 / 14 / 16px against the 16 / 18 / 20px sentence; 5.05:1 |
| R6c container radius | **FIXED** | Overlap card, perk cards and closing panel all 12px (0.75rem, the profile value) at every width; buttons and eyebrow 32px |
| H1 content width | **RESOLVED (O: `section` width 90%)** | 1280: hero and perk grid run 78 to 1202 (6.1% gutters; mockup about 7.5%); inset panels 172 to 1108, still 75%; band and muted backgrounds stay full-bleed. 360 and 768 keep 16px gutters (the width prop applies from `lg`). Container untouched (badcamp#10) |
| H2 eyebrow orange | **RESOLVED (O: `#c23d14`)** | Eyebrow background `rgb(194, 61, 20)` = `#c23d14`; white 14px on it 5.29:1 (was 4.53:1) |

### Section re-check against the mockup

| Section | Result |
|---|---|
| Hero | PASS: pill eyebrow; two-line headline at desktop with the highlighted bundle name (3.53:1, large text); sentence; solid plus outline buttons side by side from `sm`, stacked at 360; circle badge on the right, vertically centred, at 768 and 1280, and below the buttons at 360; balanced top and bottom spacing |
| Overlap card | PASS: overlaps the band by 32 to 48px, inset 75% at `lg`, white `#fff` with a `#ccc` border, 12px radius and shadow (dark `#242424`, `#646464` border) |
| What you get | PASS: heading, 16px gap, intro; 1 / 2 / 3 columns; 3×2 at 1280; icon tile, PERK n eyebrow (5.94:1 light, 8.97:1 dark), title, text |
| Closing panel | PASS: blue panel inset 75% on the muted page; heading, sentence, Donate (7.59:1 light, 10.84:1 dark), divider, two smaller notes |
| Page-wide | PASS: content left edge shared by the hero and grid (78 at 1280); inset panels share 172; Lexend for display, Inter for body; only profile colours render (plus the new `#c23d14`); radii match the profile |

### WCAG re-check

| Item | Result |
|---|---|
| Overflow | none: 360/360, 768/768, 1280/1280, both schemes; 0 elements in `<main>` past an edge |
| Text contrast | 0 failures in 35 text elements × 3 widths × 2 schemes. Lowest: highlight 3.53:1 (large, needs 3), then white on `#0076a8` 5.05:1 (including the 12px notes at 360), eyebrow 5.29:1 |
| Focus visibility | All 3 main-content links (including the new outline button) match `:focus-visible` after `focus({focusVisible:true})` and a 500ms settle, at 360 and 1280. The ring is a solid 3px `box-shadow` in the token colour, and the border takes the same colour: light `#242424` on the `#0076a8` band = 3.07:1, dark `#eee` = 4.35:1. Both pass 3:1. The light-scheme margin is still thin (T's note stands). Computed-style check only; F's and T's pixel-diff method covered the same three elements |
| Touch targets | 162×47, 200×49, 113×47 (360); 174×53, 217×55, 120×53 (1280) |

### Notes (not blocking)

- Orphaned words in placeholder copy: "perk.]" no longer sits alone at 1280; "goes" still wraps alone in the overlap heading at 360. The copy isn't final (badcamp#9), so re-check it with real copy.
- At 360 the notes are 12px. That passes contrast and matches the mockup's small-note intent; real copy may want 14px.

### Screenshots (`S-artifacts/round2/`)

`S-{light,dark}-{360,768,1280}-main.png` (the `<main>` element), `S-{light,dark}-{360,768,1280}-full.png` (whole page), `S-{light,dark}-metrics.json` (boxes, type, colours, gaps, headline line texts, section backgrounds, per-element contrast).

### Scheme restore

Dark: `ddev drush config:set mercury.settings scheme dark -y` + `ddev drush cr` (served `<html class="dark">` confirmed). Restored: `config:set … light -y` + `drush cr`. Then `ddev drush config:get mercury.settings scheme` printed `'mercury.settings:scheme': light`, and the served `<html>` has no `dark` class.

---

## Re-audit 2 (radius moved to the correct layer, `heading` W11 change)

Base `52978de` plus F's uncommitted work: stock `--radius: 0.5rem` (light and `.dark`), containers on Mercury's `xl` (= `--radius` + 4px), `card-icon` `medium` set to `xl`, and the `heading` highlight/break code changed (W11). Same headless method as before. Every value in the round-3 metrics was compared against the round-2 PASS, field by field (boxes, type, colours, gaps, headline line texts, section backgrounds, per-element contrast), in both schemes at 360, 768 and 1280.

### Verdict: PASS

| Check | Result |
|---|---|
| Cards, panels, containers 12px | **PASS**: overlap panel, all six perk cards and the closing panel are 12px at every width, light and dark |
| Buttons and eyebrow still pills | **PASS**: Donate $100, See the six perks, closing Donate and the eyebrow are all 32px (`--radius-pill` 2rem unchanged); the badge is still a circle (224 / 237 / 288px) |
| Headline highlight and break unchanged | **PASS**: served markup is identical to round 2 (`…or more.<span class="lg:block"></span> Get the <span class="text-accent">[Bundle name]</span>.`); lines at 1280 are "Give $100 or more." / "Get the [Bundle name]."; same wraps at 768 and 360; highlight 3.53:1 (large) |
| No regression from round 2 | **PASS**: the only changed value in 1,000+ measured fields per scheme is the perk icon tile's corner, 10px → 6px (it uses `rounded-md` = `--radius` − 2px, which follows the stock token). The tile isn't a container under the profile, still reads as a rounded square, and the mockup's tile corner is small; ACCEPTED as a side effect. A owns its site-wide reach (other `rounded-sm` / `rounded-md` consumers) |
| Contrast | 0 failures, all text, all widths, both schemes (unchanged values) |
| Overflow | none at 360 / 768 / 1280, both schemes |
| Focus | all 3 main-content links match `:focus-visible` with the solid 3px token ring at 360 and 1280: light `#242424` (3.07:1 on the band), dark `#eee` (4.35:1) |

Screenshots: `S-artifacts/round3/S-{light,dark}-{360,768,1280}-{main,full}.png`, metrics `S-artifacts/round3/S-{light,dark}-metrics.json`.

Scheme restore: dark set with `config:set` + `drush cr` (served `<html class="dark">` confirmed), then restored with `config:set … light -y` + `drush cr`. `ddev drush config:get mercury.settings scheme` prints `'mercury.settings:scheme': light`, and the served `<html>` has no `dark` class.
