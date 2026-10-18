# Brief — adapter trial: the landing page on Mercury (playbook#936)

**Purpose.** This is one real Website Front-End Pipeline cycle that builds a Drupal page with the new `drupal-canvas-mercury` adapter. It runs **before** playbook#937 merges. Its main output, besides the page, is **every place the adapter was wrong, ambiguous or missing something**. Those findings go back into the adapter.

**Run:** `trial-0936-mercury-landing`, on badcamp branch `trial-0936-mercury-landing`. Not pushed.

## Read first

- Core roles: `~/Projects/playbook/pipelines/website-frontend/core/` (`principles.md`, `nfr-checklist.md`, `verification-tiers.md`, and your role in `roles/`).
- **The adapter, still unmerged, so read it from the PR worktree:** `~/Projects/playbook/.claude/worktrees/0936-mercury-adapter/pipelines/website-frontend/adapters/drupal-canvas-mercury.md`.
- The profile: `docs/frontend/frontend-pipeline-profile.md` (brand tokens, paths, commands).
- The design: `docs/handoffs/trial-0936-mercury-landing/mockup.png`. It sets the **layout only**; colours and fonts come from the profile's brand (badcamp.org).

## Scope

A Canvas page titled **"Perks (trial)"**, copying the mockup's **main content area**, top to bottom:

1. **Hero:**
   - a small pill "eyebrow" ("OPTIONAL ATTENDEE DONATION");
   - a two-line headline, "Give $100 or more. Get the [Bundle name].", with the bundle name highlighted;
   - one sentence;
   - two buttons: a solid "Donate $100" to `/donate`, and an outline "See the six perks" that jumps to the perks section;
   - on the right, a large circular badge: "$100+" over "unlocks 6 perks".
2. **"Where your donation goes":** a white card that **overlaps** the bottom of the hero, with a heading and two or three sentences.
3. **"What you get":** a heading, a one-line intro, and a 3×2 grid of six perk cards. Each card has an icon tile, a "PERK n" eyebrow, a title and one or two sentences.
4. **Closing panel**, "Donate and claim your bundle": a heading, one sentence, a "Donate" button to `/donate`, and two small notes (the tax statement and the delivery note).

   **No amount pills.** Decided on badcamp#14: Zeffy's own picker replaces them.

Keep the mockup's [bracketed] placeholder copy as-is; the real copy isn't decided yet.

**Brand tokens are in scope:** web-root `theme.css` / `fonts.css` with the profile's colours and fonts, self-hosted.

**Out of scope:**
- the site header and footer (badcamp#10 owns the site chrome);
- real offer data (badcamp#9). The six cards are static Canvas content for this trial.

**Breakpoints:** mobile-first. At 360px it is single-column with no horizontal overflow; the grid is 2 columns at `md` and 3 at `lg`.

## Rules

- **Follow the adapter exactly.** Every layer decision names the adapter's layer. Reuse Mercury components first, and read each `.component.yml` before use.
- **If the mockup needs something Mercury has no component for:**
  - the adapter says to convert Mercury into the same-machine-name custom theme first, as its own step;
  - do the conversion exactly as the adapter describes, and keep it **separable** from the page work (list its files separately in your handoff);
  - if a step of the conversion fails or doesn't match the adapter, stop and record it. **Don't work around it silently.**
- **The page must be reproducible from git:** export it into `recipes/badcamp_pages/` (create the recipe) as the adapter describes.
- **Headless only:** no visible browser. Playwright headless; screenshots go to files under `docs/handoffs/trial-0936-mercury-landing/`.
- **Don't commit or push.** O commits after the gates pass.
- **Fonts:** get Lexend, Inter and Aleo from a trusted package (for example `@fontsource` on npm) or Mercury's bundled Inter. No third-party font CDN at runtime.
