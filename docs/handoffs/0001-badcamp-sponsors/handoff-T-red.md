# Handoff-T-red: Phase 4 - Author the failing suite for badcamp_sponsors

**Date:** 2026-10-16
**Branch:** issue-0001-badcamp-sponsors
**Run slug:** 0001-badcamp-sponsors
**Brief / wireframe reviewed:** docs/handoffs/0001-badcamp-sponsors/brief.md (incl. "Plan amendments
after A's review", binding) · docs/handoffs/0001-badcamp-sponsors/survey.md ·
docs/handoffs/0001-badcamp-sponsors/handoff-A.md (findings 1-5) · GitHub issue
Performant-Labs/badcamp#1 ("Design decisions (settled)" binding)

## A precondition

Confirmed: A returned PASS on the plan (handoff-A.md); brief + survey carry the five amendments.

## Tests authored

All under `web/modules/custom/badcamp_sponsors/tests/src/`. Test-first: the module does not exist —
no `.info.yml`, no `src/`, nothing. 12 files, **40 test methods** (10 Unit, 30 Kernel).

### Unit (pure logic; no Drupal bootstrap) — 10 tests

| File | Purpose |
|---|---|
| `Unit/CouponTitleBuilderTest.php` | Title shape `<code> — <sponsor> / <campaign>` (em dash U+2014), code trimmed inside the builder, names verbatim. |
| `Unit/CouponCodeNormalizerTest.php` | Code trim-before-compare rule: leading/trailing whitespace (incl. tabs/newlines) trimmed, internal whitespace preserved, empty-string edge. |
| `Unit/RedeemedDateRuleTest.php` | Redeemed-date rule as pure logic: required when redeemed (missing = 1 violation with exact message), must be empty when not redeemed (present = 1 violation), consistent combinations clean. |

### Kernel (real Drupal + DB through DDEV) — 30 tests

Each installs the module the way `drush en` does: `$this->container->get('module_installer')->install(['badcamp_sponsors'])`.

| File | Purpose (AC) |
|---|---|
| `Kernel/InstallTest.php` | AC 1 + config surface: deps enabled (node, views_data_export, csv_serialization, pathauto, simple_sitemap, link, datetime, image); 3 content types; 8 field configs (type/required/ER target bundles); 3 views with `page_1` at the right paths + admin theme + exactly one `data_export` display at `<path>/export.csv`; exactly 2 pathauto patterns with the right strings/bundle conditions; `sponsorship_manager` role with the exact 13-permission set; menu links (`badcamp_sponsors.sponsors` + children); sitemap exclusion `simple_sitemap.bundle_settings.default.node.<bundle>` with `index: false`; `core.base_field_override.node.coupon.promote` ships; no metatag field on coupon; content-overview integration (core content view exists, type filter not bundle-restricted, types in bundle list). |
| `Kernel/CouponCrudTest.php` | AC 2 + auto-title: create/edit/delete all three types via entity API; coupon title regenerated on save (`SAVE20 — Acme Corp / Fall Blast`) and code stored trimmed; coupon not promoted; date-issued defaults to now (RFC3339 shape); edit regenerates title; title widget hidden in the coupon form display. |
| `Kernel/CouponValidationTest.php` | AC 3: missing sponsor/campaign/code rejected via `$node->validate()`; sponsor ER restricted to sponsor bundles; duplicate code per (sponsor, campaign) rejected incl. the trim case; same code allowed for a different sponsor or campaign; redeemed-date constraint (2 bad / 2 good combos). All API-level, not form-level. |
| `Kernel/DeleteBlockingTest.php` | AC 4: `badcamp_sponsors.coupon_counter` is trash-aware (2 = 1 live + 1 trashed; accepts entity or nid); `delete` access forbidden while referenced (incl. a coupon put in trash via plain `delete()`), allowed once unreferenced (with access-handler cache reset between phases); the hook never grants (permission-less user stays denied). Runs with trash enabled for node. |
| `Kernel/CouponAccessTest.php` | AC 5: `view` on a coupon is a forbidden access result without `view coupon content` (403-equivalent), allowed with it; sponsors/campaigns stay viewable by ordinary users (no leak). |
| `Kernel/SectionAccessTest.php` | AC 6 + 7 (access side): routes `view.sponsors.page_1` `/sponsors`, `view.campaigns.page_1` `/sponsors/campaigns`, `view.coupons.page_1` `/sponsors/coupons`; `_admin_route` on; permission requirements exactly `access sponsors section` (×2) and `access sponsors section, view coupon content` (coupons); CSV routes exist at `<path>/export.csv` and carry the same `_permission` as their page; `checkNamedRoute` TRUE for section users / FALSE for plain; shipped `sponsorship_manager` role grants all three. |
| `Kernel/PathautoTest.php` | AC 8: sponsor → `/sponsor/acme-corp`, campaign → `/campaign/fall-blast`, coupon → no alias (and no `/coupon*` alias from any pattern). |
| `Kernel/UninstallTest.php` | AC 10: after `uninstall(['badcamp_sponsors'])` — zero nodes of the three types via raw `node_field_data` count (sees the trashed coupon's surviving row), ~30 named config objects gone + no `badcamp_sponsors.*` prefix left, no `node__field_coupon_*`/`node__field_sponsor*`/revision tables (`findTables`), no `/sponsor/…`-`/campaign/…` aliases and no aliases to deleted nids, `views_data_export` + `csv_serialization` still enabled; re-enabling works. Seeds a trashed coupon + alias and asserts they existed first (anti-vacuous). |
| `Kernel/WorkflowExclusionTest.php` | The three types are never added to an editorial workflow: reproduces `basic_editorial` governing `page` only, installs the module, asserts bundles unchanged, no module-shipped workflow config. |

Placement rationale: Unit only for the three pure rules the issue names; everything else is
config/entity/access behavior only a booted Drupal can prove (cheapest sufficient tier). No
Functional/BrowserTestBase tests (issue-mandated; worktree limitation). Tabs/menu structure and
live CSV bytes are deliberately NOT kernel-asserted — walkthrough territory per the brief.

## RED confirmation

Command (from the session root, the run's worktree):

```
bash scripts/run-tests.sh
```

Result — PHPUnit summary line and exit code:

```
Tests: 40, Assertions: 0, Errors: 40, Warnings: 6.
EXIT CODE: 2   (ddev exec phpunit exit status 2)
```

Nothing green, nothing skipped, no syntax errors (all files `php -l` clean first). Expected
failure reason per group, verified in the output:

- **Unit (errors 1-10):** `Error: Class "Drupal\badcamp_sponsors\CouponTitleBuilder|CouponCodeNormalizer|RedeemedDateRule" not found` — raised at each test's first assertion line. Canonical RED for a not-yet-written feature class: the failure happens exactly where the feature is exercised.
- **Kernel (errors 11-40):** `Drupal\Core\Extension\MissingDependencyException: Unable to install modules badcamp_sponsors due to missing modules badcamp_sponsors.` — thrown by `ModuleInstaller.php:156` from the `module_installer()->install(['badcamp_sponsors'])` call in each test's `setUp()`. This is the documented red contract: the `drush en` equivalent fails because the module does not exist.
- The 6 PHP warnings (`Undefined array key "badcamp_sponsors"` at `ModuleInstaller.php:144/147`) are the installer inspecting the missing extension right before it throws — side effects of the expected error, not test defects.

One RED-shape iteration worth recording: the first draft had a shared
`BadcampSponsorsKernelTestBase`, which died as a PHPUnit loader error
(`Class ... not found`) because Drupal's test autoloader only registers a module's test
namespaces once the extension (`.info.yml`) is discoverable. The base was dropped; every Kernel
test is now self-contained (extends `KernelTestBase`), which is what makes the per-test
`MissingDependencyException` RED visible. F's `.info.yml` will restore autoloading for any future
shared test helpers.

## The contract for F (implement to THESE names — the tests are the spec)

### Module + info

- Module `badcamp_sponsors` at `web/modules/custom/badcamp_sponsors/`.
- `badcamp_sponsors.info.yml` `dependencies`: **node, pathauto, simple_sitemap, views_data_export,
  link, datetime, image** (the last three are ADDITIONS to the brief's list: the shipped
  `field.storage.*` config cannot install without the field-type provider modules —
  `InstallTest::testDependenciesAreEnabled` asserts all seven). **trash stays a soft dependency**
  (never in `.info.yml`; branch on the `trash` service / `deleted` base field).

### Content types + fields (asserted in InstallTest/CouponCrudTest)

- `sponsor`, `campaign`, `coupon`.
- sponsor: `field_sponsor_website` (link, optional), `field_sponsor_logo` (image, optional).
- coupon: `field_coupon_code` (string, required), `field_coupon_sponsor` (ER → node, target
  bundles exactly `['sponsor']`, required), `field_coupon_campaign` (ER → `['campaign']`,
  required), `field_coupon_date_issued` (datetime, required, default now),
  `field_coupon_redeemed` (boolean, optional), `field_coupon_redeemed_date` (datetime, optional).

### Plain classes (static, Unit-tested, exact signatures)

- `Drupal\badcamp_sponsors\CouponTitleBuilder::build(string $code, string $sponsor_name, string $campaign_name): string`
  → `trim($code) . ' — ' . $sponsor_name . ' / ' . $campaign_name` (U+2014 em dash).
- `Drupal\badcamp_sponsors\CouponCodeNormalizer::normalize(string $code): string`
  → `trim($code)`; internal whitespace preserved.
- `Drupal\badcamp_sponsors\RedeemedDateRule::validate(bool $redeemed, ?string $redeemed_date): array`
  → `[]` when consistent; exactly
  `['The "Redeemed date" must be set when "Redeemed" is checked.']` /
  `['The "Redeemed date" must be empty when "Redeemed" is unchecked.']` otherwise.
- The presave hook must store the NORMALIZED code and build the title from the trimmed code,
  sponsor title and campaign title (`CouponCrudTest` asserts the stored value and the title).
  Coupon titles regenerate on edit; sponsor/campaign titles stay editor-owned.

### Permissions (exact machine names)

- `access sponsors section` — the section permission.
- `view coupon content` — the coupon-view permission.
- View page displays: `access` type `perm` with `perm` option exactly:
  - sponsors + campaigns pages: `access sponsors section`
  - coupons page: `access sponsors section, view coupon content` (comma = core `_permission`
    AND; the views `perm` option is written verbatim into the route requirement).

### Role (exact permission set, InstallTest asserts equality)

`user.role.sponsorship_manager`, not admin, with exactly: `access content`,
`access content overview`, `access sponsors section`, `view coupon content`,
`create|edit any|delete any {sponsor,campaign,coupon} content` (12 bundle perms).

### Service (DeleteBlockingTest)

- Service id **`badcamp_sponsors.coupon_counter`**, methods
  `countBySponsor(NodeInterface|int $sponsor): int` and `countByCampaign(NodeInterface|int $campaign): int`,
  trash-aware per A's finding 5: two storage queries split on the `deleted` base field
  (live + `exists('deleted')`), summed; no raw SQL joins.

### Hooks (A findings 1 + 4)

- `hook_ENTITY_TYPE_access` for node (a `badcamp_sponsors_node_access()` function or the
  Hook-attribute equivalent, hook string `node_access`) returning `AccessResultInterface`:
  - `delete` on sponsor/campaign: forbidden while the counter > 0, neutral otherwise;
    **never allowed** (permission holders must keep getting their allow from node module's own
    hook — `DeleteBlockingTest` proves a permission-less user stays denied).
  - `view` on coupon: forbidden without `view coupon content`, **neutral** with it (core grants
    view of published nodes; do not return allowed).
  - Silent (neutral/NULL) for every other bundle/operation (sponsors/campaigns stay public —
    `CouponAccessTest::testOtherTypesStayPublic`).
  - Inherent platform behavior kept: `bypass node access` users bypass the block (accepted).
- `hook_module_preuninstall($module, bool $is_syncing)`: when `$module === 'badcamp_sponsors'`,
  inside `\Drupal::service('trash')->executeInTrashContext('ignore', ...)` (or
  `setTrashContext('ignore')`), batch-delete every sponsor/campaign/coupon node INCLUDING
  trashed ones and their path aliases, before config removal. No `db_drop_table()` anywhere.
  (Invoked on all enabled modules at `ModuleInstaller.php:568`; your own module receives it.)

### Config entity names (exact)

- Views `views.view.sponsors|campaigns|coupons`, page displays **`page_1`** at `/sponsors`,
  `/sponsors/campaigns`, `/sponsors/coupons`, `use_admin_theme: true`, one `data_export`
  display per view at `<path>/export.csv` (display id is free; the path is not).
- `pathauto.pattern.sponsor` (`/sponsor/[node:title]`, type `canonical_entities:node`, node_type
  condition sponsor) and `pathauto.pattern.campaign` (`/campaign/[node:title]`). **No coupon
  pattern and no catch-all** (PathautoTest asserts the pattern list is exactly these two).
- `simple_sitemap.bundle_settings.default.node.sponsor|campaign|coupon` with `index: false`
  (variant segment `default` per A finding 2).
- `core.base_field_override.node.coupon.promote` (default 0; behaviorally asserted via
  `isPromoted() === FALSE`).
- Menu links `badcamp_sponsors.sponsors` (route `view.sponsors.page_1`) with children
  `badcamp_sponsors.campaigns`, `badcamp_sponsors.coupons` (`parent: badcamp_sponsors.sponsors`,
  routes `view.<id>.page_1`).
- Every shipped config entity carries the enforced `module: [badcamp_sponsors]` dependency
  (UninstallTest asserts it all disappears on `pmu`).

## What F must NOT change

- **The test files are the contract.** Do not edit, weaken, re-word, or delete anything under
  `web/modules/custom/badcamp_sponsors/tests/`. If a test looks wrong, route it back to T.
- Do not rename permissions, service ids, config names, view ids, `page_1` display ids, field
  names, plain-class names or the title/message strings above — the tests assert them literally.
- Do not add `hook_schema()`, DB foreign keys, Functional tests, or a coupon pathauto pattern.
- Do not make the access hook return `allowed` (neutral/forbidden only), and do not special-case
  uid 1 in the hook (core's bypass already covers it).
- Non-test files I touched: none (only the gitignored `web/sites/simpletest/browser_output/`
  directory the phpunit config expects). Note `web/robots.txt` shows as modified in the worktree —
  that drift predates T (composer scaffold inside the worktree); it is not part of this run's
  changes and should not be committed with the tests.

## Ready for F

Confirmed: RED is valid. Every test fails for the documented module-absent reason; the suite
covers every automatable AC (1-8, 10) plus the issue's extra requirements (auto title, hidden
title widget, redeemed-date rule, privacy bundle settings, workflow exclusion, trash-aware
counting, uninstall purity). F may implement against these tests. AC 9 (post-merge walkthrough)
is journal-pending by design.

Tier-2/verification handoff for T-green (Phase 6): re-run `bash scripts/run-tests.sh` (expect
GREEN), spot-check that tests still fail when behavior is removed, cross-check F's reported
commands, and run `ddev exec vendor/bin/phpcs --standard=Drupal,DrupalPractice` on the module.
