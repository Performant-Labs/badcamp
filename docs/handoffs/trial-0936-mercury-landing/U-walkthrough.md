# U walkthrough: trial-0936-mercury-landing

U (UI Walkthrough), Website Front-End Pipeline, adapter `drupal-canvas-mercury` (unmerged worktree copy). Branch `trial-0936-mercury-landing`, HEAD `52978de`. Site `https://badcamp.ddev.site:8493`, page `/perks-trial`. Headless Chromium only: Playwright 1.64.1 with the cached `chrome-headless-shell` (`chromium_headless_shell-1243`), because the Playwright MCP server was not available this session. No product code, config, content or git state changed. The only config touched was the temporary `mercury.settings scheme` toggle, restored (see Scheme restore).

**Verdict: PASS.** 0 REWORK items. Two advisories on page content (landing offset, unused preloads), one known gap (`/donate` 404), several site-chrome notes.

## Matrix (viewport x theme variant)

Each cell: real path (load `/`, then in-page navigation to `/perks-trial`) plus a separate direct load, every control exercised, console and network captured on every load. "Overflow" is `documentElement.scrollWidth == clientWidth` plus a scan for elements whose box passes the viewport edge.

| Cell | Home 200 | `/perks-trial` 200 (nav path / direct) | `<html class>` | Overflow | See the six perks | Donate $100 | Donate (closing) | Keyboard order | Navbar | Console / network on page loads |
|---|---|---|---|---|---|---|---|---|---|---|
| light 360 | yes | 200 / 200 | `js` | none (360/360) | PASS | PASS (gap) | PASS (gap) | PASS | toggle PASS (chrome) | clean, 2 preload warnings |
| light 768 | yes | 200 / 200 | `js` | none (768/768) | PASS | PASS (gap) | PASS (gap) | PASS | inline nav, no toggle | clean, 2 preload warnings |
| light 1280 | yes | 200 / 200 | `js` | none (1280/1280) | PASS | PASS (gap) | PASS (gap) | PASS | inline nav, no toggle | clean, 2 preload warnings |
| dark 360 | yes | 200 / 200 | `dark js` | none | PASS | PASS (gap) | PASS (gap) | PASS | toggle PASS (chrome) | clean, 2 preload warnings |
| dark 768 | yes | 200 / 200 | `dark js` | none | PASS | PASS (gap) | PASS (gap) | PASS | inline nav | clean, 2 preload warnings |
| dark 1280 | yes | 200 / 200 | `dark js` | none | PASS | PASS (gap) | PASS (gap) | PASS | inline nav | clean, 2 preload warnings |

"(gap)" = the control behaves correctly; its target `/donate` does not exist yet (known gap, below). Every cell produced the same results, so no control works in one variant and breaks in another. The dark cells were confirmed live (`<html class="dark js">` in the served HTML and in the browser).

## Controls

### "See the six perks" (hero, `href="#perks"`): PASS, 6/6 cells

- Click (and keyboard Enter on the focused link) sets `location.hash = #perks` and scrolls so the "What you get" `h2` top is at **0 px from the viewport top** in every cell (scrollY 980 at 360, 676 at 768, 705 at 1280). The page is taller than the viewport at all three widths, so the jump is a real scroll, not a no-op.
- **Sticky header: none to account for.** The header chain (`.navbar`, `.region-header`, `header[role=banner]`) is `position: relative` / `static`; nothing is `fixed` or `sticky`. So nothing overlaps the heading after the jump. Evidence: `U-light-*-after-see-perks.png` (1280 shows the heading and all six cards in view).
- Anchor target: `<span class="anchor" id="perks">` is inside the section header, before the `h2`; it is a non-focusable span (0 px wide). Focus does not move onto it; after the jump `document.activeElement` is `body`, but the **sequential focus navigation starting point moves to the target**: the next Tab lands on the closing "Donate" (the next focusable after `#perks`), not back at the top of the page. That is the correct behaviour for an in-page jump and holds in all 6 cells, by mouse and by keyboard.
- Direct load of `/perks-trial#perks`: lands at the same scroll position in all 6 cells (`U-*-direct-hash.png`).
- Advisory (not REWORK): the heading lands flush with the top edge (0 px). Harmless now because the header is not sticky; if badcamp#10 makes the header sticky, this link would hide the heading under it. A `scroll-margin-top` on the anchor target would be cheap insurance. The adapter has no guidance on this (see adapter findings).

### "Donate $100" (hero) and "Donate" (closing panel), both `href="/donate"`: PASS (behaviour), known gap (target)

| Control | Text | Target | Result (all 6 cells) |
|---|---|---|---|
| Hero | Donate $100 | `/donate` | click navigates to `https://badcamp.ddev.site:8493/donate`; **HTTP 404**, title "Page not found \| BADCamp"; browser Back returns to `/perks-trial` |
| Closing panel | Donate | `/donate` | same: 404, "Page not found", Back returns to `/perks-trial` |

The 404 is the expected, known gap (no `/donate` page exists yet; Zeffy embed is separate scope, badcamp#14). It is not a failure of this page: the links are correct per the brief. The 404 also produced the only console error seen (`Failed to load resource: ... 404`), and only in the `ctl-donate*` phases; it is attributable to the click, not to the page load.

### Keyboard-only traversal: PASS, 6/6 cells

Tab order through the page, identical in light and dark:

| Viewport | Order |
|---|---|
| 360 | Skip to main content, logo link (chrome), "Show menu" (chrome), **Donate $100**, **See the six perks**, **Donate** (closing), then focus leaves the document |
| 768, 1280 | Skip to main content, logo link, "Home" (chrome), **Donate $100**, **See the six perks**, **Donate**, then leaves the document |

- Main content is exactly the three links, in visual order (hero buttons left to right, then the closing panel). No focus traps, no unreachable controls, nothing focusable that is not a control (perk cards, badge and notes are correctly not tab stops).
- Every stop reports `:focus-visible` and is in the viewport when focused.
- Focus ring on the three main-content buttons, read 500 ms after `focus({focusVisible:true})` (the `duration-300` transition): `box-shadow` 3 px ring and `border-color` are `#242424` (36,36,36) in light and `#eee` (238,238,238) in dark, at 360 and 1280. This confirms the `--ring` fix in `52978de` is live in both schemes. (T owns the 3:1 contrast measurement; U only confirms the ring renders on keyboard focus in every variant.)
- The skip link targets `<a id="main-content" tabindex="-1">`, a valid focus target.

### Navbar toggle at 360 (site chrome, noted not graded): works

- 360: "Show menu" visible; click opens the overlay (Home link and "Hide menu" visible, body `overflow: hidden`, focus moves into the menu); "Hide menu" closes it and restores body overflow to `visible`. Same in dark.
- 768 and 1280: toggle hidden (`md:hidden`), inline "Home" link visible. Matches the 768 px breakpoint T already verified.
- Chrome notes (badcamp#10, not this page): the hamburger has no `aria-expanded` (null before and after opening); in dark, the stock Mercury logo is near-black on `#242424` (`U-dark-360-navbar-open.png`), and the header logo link has no accessible name (T already recorded `link-name`).

### Anything else clickable in `<main>`

Inventory of `main a, button, input, select, textarea, summary, [tabindex], [role=button], [onclick]` plus every element with `cursor: pointer`: only the three links above, plus the `a#main-content` skip target (`tabindex=-1`, not a control). No forms, buttons, accordions or reactive components exist on this page, consistent with the profile's "stateful-surface inventory: none". Home (`/`) has no link to `/perks-trial` (only `/home`), so the page is reachable only by URL; the "real path" was `/` loaded, then in-page navigation to `/perks-trial`.

## Console and network

Captured on every load: `/` load, navigation to `/perks-trial`, direct load, each Donate click, the keyboard run, 6 cells.

- **Uncaught exceptions (`pageerror`): 0.**
- **Failed requests (`requestfailed`): 0.**
- **HTTP 4xx/5xx: only `/donate` 404 (known gap).** Page loads of `/` and `/perks-trial`, `/theme.css`, `/fonts.css` and the six font files are all 200 in every cell.
- **Console errors: only the `/donate` 404 resource error above.**
- **Console warnings (every page load, every cell; surfaces about 3 s after load): 2 preload warnings.**
  `The resource .../themes/custom/mercury/fonts/Outfit/latin.woff2 was preloaded using link preload but not used within a few seconds` and the same for `.../fonts/Inter/InterVariable.woff2`. Cause: Mercury's `templates/includes/preload.twig` (fed `mercury_path` by `ThemeHooks::preprocessHtml`) hard-codes preloads of Mercury's own Outfit and Inter-variable files, but the web-root `fonts.css` override serves Lexend, Inter and Aleo from `/fonts/...`, so the preloaded files are never used. Effect: two wasted font downloads per page view plus a console warning (the zero-console-noise target is not met); no functional impact. Advisory for page content; it is a theme-level fix (edit `preload.twig` to the brand fonts, or remove it) and the adapter should cover it (below).

## Scheme restore

Dark was set with `ddev drush config:set mercury.settings scheme dark -y` + `ddev drush cr`. After the dark runs: `ddev drush config:set mercury.settings scheme light -y` + `ddev drush cr`, then `ddev drush config:get mercury.settings scheme` -> `'mercury.settings:scheme': light`; the served `<html>` has no `dark` class. `git status` shows only the new `U-artifacts/` directory (plus this file).

## Evidence

`U-artifacts/` (20 PNGs): per cell `U-<scheme>-<width>-full.png` (full page after the real-path navigation), `-after-see-perks.png`, `-direct-hash.png`; plus `U-light-360-navbar-open.png`, `U-dark-360-navbar-open.png`. Scripts and raw JSON were kept in the scratchpad, not the repo.

## Adapter findings (what misled or failed to cover U)

1. **The adapter has no U content at all.** It covers L1-L5, components and T2, but nothing for U: no walkthrough how-to, no list of what to exercise on a Mercury page. U had to infer everything from the role doc and the profile.
2. **The core U role and the generic contract assume the Playwright MCP.** In this session it did not connect (`CONNECTION_CLOSED`), so U fell back to plain Playwright scripts against a cached headless shell. The adapter's T2 bullet already documents "set `executablePath` / no browser download", but only for axe. It should state once, for T and U, how to find the cached `chrome-headless-shell` (`~/Library/Caches/ms-playwright/chromium_headless_shell-*/chrome-headless-shell-mac-arm64/chrome-headless-shell`) and that scripted Playwright is an accepted U backend when the MCP is down.
3. **Scheme switching is documented only inside the T2 bullet.** U needs the same `config:set` / `drush cr` / restore / `config:get` procedure for the dark matrix. Promote it to its own short "Switching schemes" section that T and U both reference. It was right and worked; it was just hard to find.
4. **No guidance on in-page anchors and a sticky header.** The U role says "accounting for any sticky header", but the adapter does not say whether Mercury's header is sticky (it is not: `.navbar` is `position: relative`), nor how Mercury's `anchor` SDC works (a non-focusable `<span class="anchor" id=...>` rendered above the heading, with no `scroll-margin`). A line stating both, and recommending `scroll-margin-top` for any project whose chrome becomes sticky, would have saved a probe.
5. **Font override leaves Mercury's `<link rel=preload>` pointing at the replaced fonts.** Adapter property 1 says `fonts.css` replaces Mercury's fonts "without a rebuild", and that is true for the `@font-face`, but `templates/includes/preload.twig` still preloads Mercury's Outfit and Inter-variable files, which now go unused and warn in the console on every page. The adapter should say that a font override also needs a `preload.twig` change (a theme edit, so it falls under the convert-to-custom-theme rule) or accept the warning.
6. **The adapter's focus-ring section says to check ring colour; U needs one line on the "wait for the transition" rule too.** The T2 bullet has the 300 ms rule, but it sits under axe. U hit the same thing: a computed-style read taken right after Tab sees a transparent ring. Cross-reference it from the U guidance.
7. **Profile "stateful-surface inventory: none" kept U's matrix small and was accurate.** No change needed; noted as a positive. One profile gap: no list of **walkthrough routes** (the profile's "Chrome route" is `/` only), so U had no stated entry path to `/perks-trial` and assumed "load `/`, then navigate"; `/` has no link to the page. A profile field "U entry path / how a user reaches the page" would remove the guess.
