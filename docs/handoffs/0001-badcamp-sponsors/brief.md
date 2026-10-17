# Brief: #1 badcamp_sponsors

Repo: Performant-Labs/badcamp. Issue: #1. Rigor: in-session. UI surface: no. Kind: feature.

## Problem

The site has no way to track conference Sponsors, Campaigns and the Coupons issued to sponsors
for campaigns. Editors need normal Drupal CRUD, the records must appear in `/admin/content`, a
Sponsors section at `/sponsors` (three tabbed views with exposed filters and CSV export), and
enabling one module must set everything up while uninstalling it removes everything.

## Evidence

The issue (Performant-Labs/badcamp#1) is the binding spec; its "Design decisions (settled)"
section governs. Key facts verified in-tree / on-site (details in `survey.md`):

- Drupal 11.4.10, first custom module in the repo (`web/modules/custom/` empty today).
- `views_data_export`/`csv_serialization` absent today; `composer.json` has no entry.
- `hook_module_preuninstall()` exists: `web/core/lib/Drupal/Core/Extension/module.api.php:280`
  ("clean up any information stored in the module's entities before its config is removed").
- Core's shipped content view to mirror: `web/core/modules/node/config/optional/views.view.content.yml`.
- Enabled and reusable on the site: pathauto, simple_sitemap, metatag, trash 3.1.1, views_ui.
- `basic_editorial` workflow governs `page` only; it must stay that way.
- Tests run through `scripts/run-tests.sh` (PHPUnit in DDEV). Kernel tests extend
  `Drupal\KernelTests\KernelTestBase`; Unit extend `Drupal\Tests\UnitTestCase`.

## Acceptance criteria (each observable)

1. `drush en badcamp_sponsors` creates the three content types, their fields, the three views
   (each with a CSV display), the two Pathauto patterns, the `sponsorship_manager` role and the
   menu links; `views_data_export` and `csv_serialization` end up enabled.
   → Kernel `InstallTest`: module installs via `$this->container->get('module_installer')`,
   config entities present, dependency modules enabled; composer require verified by F and in
   the PR body (`composer.lock` contains both packages).
2. All three types appear in `/admin/content` with normal CRUD.
   → Kernel `CouponCrudTest`/`InstallTest`: node types are in the type list and the core content
   view's type filter exposes them (config assertion); create/edit/delete via entity API works.
   The page itself is the post-merge walkthrough (pending, journal).
3. A coupon cannot be saved without a valid Sponsor and Campaign, or with a code already used
   for that Sponsor+Campaign (trimming applied).
   → Kernel `CouponValidationTest`: `$node->validate()` violations for missing refs (required ER
   fields) and duplicate code (custom constraint); trim path covered; API-level, not form-level.
4. Deleting a Sponsor or Campaign that has coupons (including trashed ones) is refused with the
   coupon count shown.
   → Kernel `DeleteBlockingTest`: node `delete` access denied while referenced (incl. a coupon
   put in Trash), allowed once unreferenced; count method returns the right number.
5. A user without the coupon permission gets 403 on a coupon's page.
   → Kernel `CouponAccessTest`: `hook_node_access` denies `view` on `coupon` without the
   permission, allows with it.
6. `/sponsors`, `/sponsors/campaigns`, `/sponsors/coupons` list records for a Sponsorship
   manager and 403 for a user without the section permission.
   → Kernel `SectionAccessTest`: view/route access checks for both users; `SponsorshipManagerTest`
   role permissions. Page rendering is the post-merge walkthrough (pending, journal).
7. Each list exports a CSV of its filtered rows.
   → Kernel: each view has a `views_data_export` display at `<page>/export.csv`, same
   permissions, CSV format; live CSV bytes are the post-merge walkthrough (pending, journal).
8. Sponsors get `/sponsor/<name>` aliases, campaigns `/campaign/<name>`, coupons none.
   → Kernel `PathautoTest`: alias generation through pathauto per pattern; coupon gets no alias.
9. Walked on the running DDEV site after merge (enable, add records, export CSVs, refused
   delete, uninstall). → **PENDING by design**: recorded in the journal as the post-merge
   walkthrough; not faked in this run.
10. `drush pmu badcamp_sponsors` leaves no nodes, config, field tables or aliases behind, and
    re-enabling works.
    → Kernel `UninstallTest`: after uninstall, zero module nodes (incl. trashed), no config,
    no `node__field_coupon_*`/`node__field_sponsor_*` tables, no aliases, `views_data_export`
    still enabled; re-enable succeeds.

Also required by the issue and tested: auto title (`<code> — <sponsor> / <campaign>`) and hidden
title widget (Kernel, on save + form-mode config); Redeemed-date rule (required when redeemed,
empty when not — constraint, Kernel + Unit for the pure rule); coupons not promoted, no metatag
field, excluded from simple_sitemap (bundle settings, Kernel); the three types are never added to
`basic_editorial` (Kernel); `sponsorship_manager` role permissions and admin menu links (Kernel,
config assertions).

## Plan amendments after A's review (handoff-A.md findings 1-5, all warn)

1. **Access seam:** implement `hook_ENTITY_TYPE_access()` for node (hook string `node_access`) /
   `hook_entity_access()`, returning `AccessResultInterface` — the legacy tri-state
   `hook_node_access()` + `NODE_ACCESS_*` constants no longer exist in core 11.4. Reference:
   `web/core/modules/node/src/Hook/NodeEntityHooks.php:119`. Inherent platform behavior: users
   with `bypass node access` (admins) bypass the delete block — accepted, it is the issue's
   chosen mechanism.
2. **Sitemap exclusion config shape:** `simple_sitemap.bundle_settings.default.node.<bundle>.yml`
   — simple_sitemap 4.x names include the variant segment (`EntityManager.php:229,310`); F
   confirms the variant key from the site's `simple_sitemap.sitemap.*` config.
3. **`.info.yml` dependencies:** `node`, `pathauto`, `simple_sitemap`, `views_data_export` (the
   module ships their config in `config/install`). **trash stays a soft dependency**: not in
   `.info.yml`; trash-aware code branches on the `trash` service / `deleted` base field existing,
   and degrades to live-only counting when trash is absent.
4. **Uninstall ordering (trash-aware):** `hook_module_preuninstall()` runs the node deletion
   inside trash-context `ignore` (`\Drupal::service('trash')->setTrashContext('ignore')` or its
   scoped wrapper, `TrashManager.php:126-161`) so `delete()` hard-deletes and the field tables
   really drop at config uninstall — never `db_drop_table()`.
5. **Trash-aware coupon counting:** two entity/storage queries split on the `deleted` base field
   — live + `exists('deleted')` — summed (trash only filters queries lacking an explicit
   `deleted` condition, `TrashEntityHooks.php:86-88`); no raw SQL joins.

## Files

- Production: `web/modules/custom/badcamp_sponsors/**` (info/links/services files, `src/`,
  `config/install/`), `composer.json`, `composer.lock`.
- Tests: `web/modules/custom/badcamp_sponsors/tests/src/Unit`, `.../tests/src/Kernel`.
- Reuse map (extend, do not duplicate): see `survey.md` — core node/views config shapes,
  pathauto, simple_sitemap, views_data_export, trash, node access API, validation constraints.

## Decisions already made (MO — from the issue, settled)

- Node content types, **not** `hook_schema()` tables; no DB foreign keys; relationships are ER
  fields enforced in code (constraint + access rules).
- IDs are node IDs; Coupon Code is sponsor-provided, unique per (Sponsor, Campaign), trimmed;
  module never generates codes.
- Delete blocking via node `delete` access (UI/bulk/Trash all respect it); trashed coupons count.
- Coupon privacy via `hook_node_access` permission; no alias, no promotion, no sitemap, no metatag.
- No editorial workflow for the three types.
- Singular aliases `/sponsor/[node:title]`, `/campaign/[node:title]` (never `/sponsors/…`).
- Uninstall deletes all module nodes (incl. trashed) before config removal
  (`hook_module_preuninstall`, batches); `views_data_export` stays enabled after uninstall.
- Section at `/sponsors` (+ `/campaigns`, `/coupons`), tabs, exposed filters, action links,
  admin theme, CSV via views_data_export `<path>/export.csv` respecting filters.
- Tests: Unit + Kernel only; **no Functional/browser tests** in this run (worktree limitation;
  walkthrough covers real pages post-merge).

## Out of scope

Front-end redemption/validation page; data import; DB-level FKs; anything else in the issue's
"Out of scope".

## Test plan

RED first (authored by T before any production code exists): every Kernel/Unit test above fails
because the module does not exist (`ExtensionNotFound`/missing config) — the module skeleton
itself is F's first deliverable. Then GREEN via F. All runs through `scripts/run-tests.sh` from
the worktree (first run does `composer install` inside it). Coding standard:
`ddev exec vendor/bin/phpcs --standard=Drupal,DrupalPractice` on the module.

## Risks

- **Hand-written views YAML is the highest-risk artifact** (issue's own note): tests cannot see a
  misconfigured column. Mitigation: mirror core's shipped view shapes, keep displays minimal,
  and cover config structure (display plugins, paths, permissions, fields) with Kernel
  assertions; the walkthrough checks the real pages after merge.
- Trash-aware coupon counting: trash 3.x soft-delete must be included when counting references.
- Uninstall ordering: nodes (incl. trashed) must go before config uninstall; batches for scale.
- Worktree/Functional-test trap: no browser tests; everything API-level.
- `views_data_export` ^1.10 on core 11.4 must be verified at `composer require` time (F does it;
  stop and report if the constraint cannot be satisfied).
