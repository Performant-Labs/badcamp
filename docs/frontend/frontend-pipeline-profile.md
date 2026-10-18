# Project Profile — badcamp (Website Front-End Pipeline)

> ⚠️ **WEBSITE FRONT-END PIPELINE — PROJECT PROFILE (instance config).** The per-site values
> the pipeline needs. Pair it with the adapter (the platform "how") and the core (the
> platform-agnostic engine): `~/Projects/playbook/pipelines/website-frontend/`.

## Identity
- **Project / slug:** `badcamp` (BADCamp donor-perks pages, epic Performant-Labs/badcamp#7)
- **Adapter:** `drupal-canvas-mercury`
- **Local site URL:** `https://badcamp.ddev.site:8493`
- **Theme machine name:** `mercury`
- **Mercury mode:** contrib, `drupal/mercury` 1.0.5 at `web/themes/contrib/mercury`. Not converted yet; see the adapter's *Component model* for when to convert.
- **Converted-from Mercury version:** n/a until converted

## Paths
- **Web root:** `web/`
- **Token files:** `web/theme.css`, `web/fonts.css` (web-root overrides; absent means Mercury's defaults)
- **Active theme root:** `web/themes/contrib/mercury`, becoming `web/themes/custom/mercury` once converted
- **Component roots (reuse source):** `<theme root>/components/`
- **Page recipes:** `recipes/badcamp_pages/` (Canvas pages as default content, one file per page)
- **Handoff / docs dir:** `docs/handoffs/<run-slug>/`
- **Runbook:** epic Performant-Labs/badcamp#7

## Brand (decided 2026-10-18, badcamp#10)
- **Source:** badcamp.org's live theme, `https://www.badcamp.org/themes/custom/bay_area_drupal/css/bay-area-drupal-styles.css`
- **Colours:**
  - primary `#0076a8` (600 `#005d85`);
  - secondary `#d44418` (600 `#c23d14`, for small text on orange);
  - highlight `#fdd362`;
  - text `#3d3d3d`; footer `#242424`.
- **Neutrals (badcamp.org scale, use only these):** `#fff` (000), `#eee` (100), `#ccc` (200), `#929292` (300), `#757575` (350), `#646464` (400), `#3d3d3d` (500, text), `#242424` (600, footer).
- **Dark scheme:** badcamp.org has none, and the site ships `scheme: light`. Mercury's `.dark` block must still be complete, so it is **derived** from the neutral scale (background `#242424`, card `#3d3d3d`, border `#646464`, foreground `#eee`) with the same brand accents. Treat it as provisional, not a brand decision.
- **Badges:** the brand sets no badge shape. The pill is opt-in per instance, not site-wide.
- **Fonts:** Lexend (display), Inter (body), Aleo (serif). Self-hosted, no font CDN.
- **Radii:** buttons 2rem, containers 0.75rem.
- **Layout reference:** `docs/handoffs/trial-0936-mercury-landing/mockup.png`. It sets the layout only; colours and fonts come from the brand above.

## Verification config
- **Commands:** run through DDEV: `ddev drush cr`, `ddev drush content:export …`, `ddev drush recipe …`.
- **axe base URL:** the local site URL above (`AXE_BASE_URL`).
- **Chrome route** (for sizing an L1 token change): `/`, the home page, which renders the header, navbar and footer.
- **Stateful-surface inventory:** none yet. The landing page has no stateful surface; anchor links only.
- **Breakpoint overrides:** none (Tailwind defaults; navbar at `md` / 48rem).
- **CSS change log:** `docs/css-change-log.md`, per `languages/css/css-change-workflow.md`. Every CSS change gets a trace and an `[L1]`…`[L5]` entry, using the adapter's L1–L5 mapping.
- **Stylelint:** pinned at 16.x (the version the adapter's ceilings were calibrated on) as a dev dependency in the root `package.json`, run with `npm run lint:css`.
- **Stylelint config:** `.stylelintrc.json` at the repo root, with the adapter's two rule sets.
- **Headless only:** no visible browser windows. Playwright runs headless; screenshots go to files in the handoff dir.

## Posture
- **Repo posture:** push + PR. Exception: the playbook#936 adapter trial runs on branch `trial-0936-mercury-landing` and is not pushed unless the operator asks.
