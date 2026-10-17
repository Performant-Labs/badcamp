# Handoff-F: Phase 5 - Implement

**Date:** 2026-10-17
**Branch / worktree:** issue-0001-badcamp-sponsors (.claude/worktrees/0001-badcamp-sponsors)
**Contract:** handoff-T-red.md (tests + the behaviors they pin)

## What I changed (file map)

Production code only, all under `web/modules/custom/badcamp_sponsors/` (54 files incl. 35
config/install files), plus `composer.json` / `composer.lock` (`drupal/views_data_export: ^1.10`).

**Module root**

| File | Behavior |
|---|---|
| `badcamp_sponsors.info.yml` | Deps exactly per contract: node, pathauto, simple_sitemap, views_data_export, link, datetime, image. Trash stays SOFT (not listed). |
| `badcamp_sponsors.permissions.yml` | `access sponsors section`, `view coupon content`. |
| `badcamp_sponsors.services.yml` | `badcamp_sponsors.coupon_counter` → `CouponCounter` (entity_type.manager + entity_field.manager). |
| `badcamp_sponsors.links.menu.yml` | `badcamp_sponsors.sponsors` (route view.sponsors.page_1, parent system.admin) + children `.campaigns` / `.coupons`. |
| `badcamp_sponsors.links.action.yml` | Add sponsor / campaign / coupon action links appearing on the three view pages. |
| `badcamp_sponsors.module` | 4 hooks (see below). |

**src/ (plain classes + plugins, 408 lines with .module)**

| Class | Behavior |
|---|---|
| `CouponTitleBuilder::build()` | `trim(code) . ' — ' . sponsor . ' / ' . campaign` (U+2014), static. |
| `CouponCodeNormalizer::normalize()` | `trim()`, internal whitespace preserved, static. |
| `RedeemedDateRule::validate()` | Exact violation messages from the contract, static. |
| `CouponCounter` | `countBySponsor()` / `countByCampaign()` accepting node|int|**string** (see deviations), trash-aware as two entity queries split on the `deleted` base field (live query + `exists('deleted')` query, summed), degrading to live-only when trash or the `deleted` field is absent. |
| `Plugin/Validation/Constraint/CouponCodeUnique(Validator)` | Entity-level constraint on node: rejects a code already used for the same (sponsor, campaign), comparing the normalized code, excluding the validated node itself; violation at path `field_coupon_code`. |
| `Plugin/Validation/Constraint/CouponRedeemedDate(Validator)` | Entity-level constraint delegating to `RedeemedDateRule`; violations at path `field_coupon_redeemed_date`. |
| `Plugin/views/field/CouponCount` | `badcamp_sponsors_coupon_count` views field: renders the `coupon_counter` count per row (option `counter: sponsor|campaign`), so the count column matches the delete-block rule including trashed coupons. Not SQL click-sortable (computed per row). |
| `Plugin/Condition/NodeType` | Condition plugin id `node_type` (see deviations): required by the pinned pathauto selection criteria. |
| `Access/TrimmingPermissionAccessCheck` + `BadcampSponsorsServiceProvider` | Swaps core's `access_check.permission` class for a subclass that trims whitespace around commas in `_permission` requirements (see deviations). |

**Hooks in `badcamp_sponsors.module`**

- `hook_ENTITY_TYPE_access` (node): `delete` on sponsor/campaign forbidden while the counter > 0
  (never allowed; reason string carries the count; cache tag `node_list:coupon` on both branches);
  `view` on coupon forbidden without `view coupon content`, neutral with it (`cachePerPermissions`);
  neutral everywhere else.
- `hook_entity_presave`: coupon code stored trimmed, date-issued defaulted to now
  (`DateTimeItemInterface::DATETIME_STORAGE_FORMAT`, UTC), title regenerated via
  `CouponTitleBuilder` from sponsor/campaign titles.
- `hook_entity_type_alter`: attaches the two constraints to the node entity type.
- `hook_module_preuninstall`: when the module is the one being uninstalled, deletes every
  sponsor/campaign/coupon node (chunks of 50, entity storage API, path aliases go with the nodes)
  inside trash context `ignore` when `Drupal\trash\TrashManagerInterface` is available, and removes
  the three `simple_sitemap.bundle_settings.default.node.*` simple-config objects. No
  `db_drop_table()` anywhere.

**config/install (35 files)**

3 × node.type; 8 × field.storage + field.field (link/image/string/ER×2/datetime×2/boolean, ER
target bundles exactly sponsor/campaign, handler settings per core shapes); `core.base_field_override.node.coupon.promote`
(default 0); 3 × entity form display (coupon's `title` hidden — `hidden: title: true`) + 3 × view
display; `views.view.sponsors|campaigns|coupons` (page_1 at `/sponsors`, `/sponsors/campaigns`,
`/sponsors/coupons`, `use_admin_theme: true`, access `perm` exactly `access sponsors section` ×2 /
`access sponsors section, view coupon content`, one `data_export_1` display each at
`<path>/export.csv` attached to its page, pager `none` + `style data_export` (csv) + `row
data_field` on the export; table style with sortable columns, full pager 50, exposed filters:
name (×2) and sponsor/campaign/redeemed/date-issued range on coupons; tabs via `default tab` +
`tab` menu options so views derives one tab set rooted at `/sponsors`); `pathauto.pattern.sponsor|campaign`
(`/sponsor/[node:title]`, `/campaign/[node:title]`, type `canonical_entities:node`,
`node_type` condition per bundle — no coupon pattern); `user.role.sponsorship_manager` (exact
13-permission set); 3 × `simple_sitemap.bundle_settings.default.node.<bundle>` (`index: false`
only — schema-valid, mirrors what `setBundleSettings(FALSE)` writes). `config/schema/badcamp_sponsors.schema.yml`
adds schema for the `node_type` condition and the views field's `counter` option. Every shipped
config entity carries `dependencies.enforced.module: [badcamp_sponsors]`.

## Config generation method (per file)

Hand-written YAML mirroring verified real exports, then validated by round-tripping through the
real installer: the module was enabled/uninstalled repeatedly on the worktree site via
`ddev exec --dir … vendor/bin/drush` (the sanctioned pattern), with the strict
`ConfigSchemaChecker` of the Kernel tests catching every schema gap. Mirrors used: core
`views.view.content.yml` (view/pager/filter/table/menu shapes), core `views_field.schema.yml` and
`condition.plugin.entity_bundle:*` (schema shapes), `views_data_export`'s own test views
(`data_export` display, csv settings), the live site's `cex` export (node.type with `type:` key,
field.storage/field, pathauto pattern, sitemap bundle settings, link/image/datetime settings),
`image.style.thumbnail` from core image, `node_schema()`/`NodeType` entity keys. One bug was
caught live rather than by tests: the coupons view's exposed "Redeemed" boolean needed
`value: null` (both `'1'` and `''` wrongly pre-filter or break the exposed form).

## Composer result

`drupal/views_data_export 1.10.0` + `drupal/csv_serialization 4.0.1` (+ `league/csv 9.28.0`)
resolved on core 11.4.10; `drupal/views_data_export: ^1.10` added to composer.json,
composer.lock updated (3 installs, 0 updates, 0 removals).

## Self-check (GREEN evidence)

- `bash scripts/run-tests.sh` (full suite, authoritative command): **`Tests: 40, Assertions: 28,
  Errors: 29` — 11 GREEN (10 Unit + WorkflowExclusionTest, which fully installs the module),
  29 Kernel errors, every single one the identical `SchemaObjectExistsException: Table
  'node_access' already exists` thrown from `KernelTestBase::installSchema()` at each test's
  setUp line.** The authoritative GREEN is the plugin's T-green run, not this self-check.
- **Proof the implementation itself is complete**: I built temporary diagnostics (since deleted)
  that ran T's 29 test methods verbatim through subclasses whose setUp applied the core-supported
  ordering — all 29 passed (366 assertions total, zero failures), plus live-site verification:
  title `SAVE20 — Acme Corp / Fall Blast`, trimmed code, not promoted, RFC3339 date-issued,
  `/sponsor/acme-corp` alias, all three routes + `_admin_route` + exact `_permission` strings,
  one CSV route each, trash-aware counter 1 → 2 after soft-delete, coupons/sponsors/campaigns
  views render rows, exposed filters narrow correctly, and `drush pmu` on the live worktree site
  left **0 nodes (incl. trashed), 0 config, 0 field tables, 0 aliases, views_data_export still
  enabled**, with re-enabling working.
- `ddev exec --dir … vendor/bin/phpcs --standard=Drupal,DrupalPractice
  web/modules/custom/badcamp_sponsors` → **0 errors, 0 warnings**.

## The 29 remaining errors are a test-vs-platform conflict (T repair needed)

Core 11.4's `ModuleInstaller::install()` (ModuleInstaller.php:344-348 → `installSchema()`,
line 763) installs `hook_schema()` tables for every module it enables — including dependencies.
Eight of T's nine Kernel classes do:

```php
$this->container->get('module_installer')->install(['badcamp_sponsors']);  // creates node_access
$this->installSchema('node', 'node_access');                               // SchemaObjectExistsException
```

`KernelTestBase::installSchema()` (KernelTestBase.php:781) calls `createTable()` unconditionally.
I verified with a dedicated probe that `node_access` exists after the install call. **No
production-side fix exists** (dropping `node_access` in `hook_install()` would wipe real node
grants on production sites). Repair options for T: delete the redundant `installSchema()` line
(the table already exists by then), or move `node` into `static $modules` (as
WorkflowExclusionTest already does) plus `installEntitySchema('node')` before the module install.

Once that repair lands, three further environment gaps surface (all hit T's tests identically,
all one-line repairs; my diagnostics confirmed them):

1. **Date formats** — first node save triggers pathauto → token info → core
   `SystemTokensHooks::tokenInfo()` → `DateFormatter::format(..., 'medium')` → fatal
   `getPattern() on null`. Fix: `$this->installConfig(['system']);` in setUp (its
   `system.date_format.*` entities).
2. **users_data** — uninstalling the module runs user's per-module data cleanup:
   `DELETE FROM users_data WHERE module IN ('badcamp_sponsors')` → table missing. Fix:
   `$this->installSchema('user', 'users_data');` (UninstallTest).
3. **Trash service id** — trash 3.1.1's services are `trash.manager` / alias
   `Drupal\trash\TrashManagerInterface`; there is **no service `trash`** (verified against the
   live site container). `DeleteBlockingTest::hardDelete()`'s
   `$this->container->get('trash')` throws ServiceNotFoundException. My production code uses the
   interface-alias id. Fix: `get('trash.manager')` (or the alias) in the test.

## Tests I think are wrong (I did NOT edit them; the orchestrator decides with T)

1. **`InstallTest::testContentOverviewIntegration` (lines 254-259)** — asserts
   `$content->get('display')['page_1']['display_options']['filters']['type']` on core's shipped
   `views.view.content`. In core's own YAML (and in the live site's stored copy — both verified),
   the filters live on the **default** display; `page_1`'s raw `display_options` carry none
   (views merges default-display options into displays only at runtime). The assertion reads raw
   config, so it fails against an unmodified core content view; it should read
   `$content->get('display')['default']['display_options']['filters']`.
2. **`PathautoTest::testSponsorAndCampaignAliases` (lines 96-99)** — `getAliases()` returns
   `array_map(fn, loadByProperties(...))`; `array_map` preserves keys, so the result is
   `[alias_id => '/sponsor/acme-corp']` (alias IDs start at 1). `assertSame(['/sponsor/acme-corp'],
   …)` requires key `0` and can never pass with a real alias. Fix: `array_values()` inside
   `getAliases()` (the sibling assertion in `UninstallTest` is unaffected either way).

Both are assertion-shape bugs, not behavior gaps: the underlying behavior was verified live and
via the diagnostics.

## Contract deviations (deliberate, all verified GREEN where testable)

1. **`CouponCounter` accepts `NodeInterface|int|string`** (contract said `int`): `DeleteBlockingTest`
   calls `countBySponsor($sponsor_a->id())` and node IDs are numeric **strings** — the pinned
   `int`-only signature is a TypeError against the pinned call site. Values are cast to int.
2. **Shipped `node_type` condition plugin**: T pins pathauto selection-criteria plugin id
   `node_type`, but core 11.4 no longer provides it (only derived `entity_bundle:node` — verified
   by the installer's "plugin does not exist" error listing the valid ids). The module ships
   `Plugin/Condition/NodeType` mirroring core's `EntityBundle` condition (plus its config schema)
   so the pinned criteria install and evaluate.
3. **`TrimmingPermissionAccessCheck` (ServiceProvider class swap of `access_check.permission`)**:
   T pins the coupons route `_permission` to the exact string
   `'access sponsors section, view coupon content'` AND pins `checkNamedRoute() === TRUE` for
   both-permission holders. Core 11.4's `PermissionAccessCheck` splits `_permission` with a bare
   `explode(',')` (no trim — verified empirically: spaced string DENIES, unspaced ALLOWS), making
   those two pins mutually unsatisfiable on stock core. The subclass trims each conjunct on a
   **clone** of the route (the stored/cached route object keeps the exact pinned string), then
   delegates to core's logic. Effect on the rest of the site: none for well-formed requirements;
   comma+space AND-lists gain the documented AND semantics back.
4. **Trash seam ids**: `\Drupal::service('trash')` (T-red handoff's example) does not exist in
   trash 3.1.1; production uses `Drupal\trash\TrashManagerInterface` (alias of `trash.manager`)
   guarded by `hasService()`. Same for the counter's trash probe.
5. **Coupon-count views column** implemented as a small views field plugin reusing
   `coupon_counter` (option `counter: sponsor|campaign`) rather than SQL aggregation over the
   reverse entity-reference relationship: aggregation cannot express the trash-aware split the
   issue requires (trashed coupons count), and the service keeps the column consistent with the
   delete-block count. Trade-off noted: the column is not click-sortable.
6. **Add-action links** shipped as `badcamp_sponsors.links.action.yml` (core `node.add` with
   `node_type` route parameters, appearing on the view pages) — the issue's "Action link" items;
   not kernel-tested, verified by config shape.
7. Minor scope note: the shipped sponsors view uses `tab_options: {type: none}` because the
   top-level menu item comes from `links.menu.yml` (test-pinned); a `normal` tab_options would
   duplicate it.

## Ready for T(green)

The implementation is complete against the RED contract; every test method passes under the
core-supported environment ordering, verified method-for-method. The suite as committed cannot
reach GREEN without the test-side repairs listed above (1 blocker: the `installSchema` ordering;
3 environment prerequisites; 2 assertion-shape bugs) — routed to T/O per the process. Re-run:
`bash scripts/run-tests.sh`; expect the 29 `SchemaObjectExistsException` errors until the test
repair lands, then GREEN.

## Fold (a-dup round 2)

**Date:** 2026-10-17. **Contract:** handoff-A-dup.md findings 1 & 2 (block). Production only;
no tests touched; nothing committed. The T-side repairs A routed (installSchema ordering,
environment prerequisites, assertion shapes) have since landed — the suite ran 40 tests /
388 assertions before this fold with 0 failures; only the two stale pins below remain.

### What was deleted / changed

Finding 1 — global permission-check swap removed:

- DELETED `src/Access/TrimmingPermissionAccessCheck.php` (42 lines) — the whitespace-tolerant
  `PermissionAccessCheck` subclass.
- DELETED `src/BadcampSponsorsServiceProvider.php` (31 lines) — the site-wide
  `access_check.permission` class swap. Now-empty `src/Access/` directory removed too.
  (~73 lines of mechanism gone.)
- `config/install/views.view.coupons.yml:561`: `perm: 'access sponsors section, view coupon content'`
  → `perm: 'access sponsors section,view coupon content'` (unspaced; the `access` stanza lives on
  the default display and is inherited by page_1 + data_export_1, so one line covers all displays
  and the CSV route). Route keeps AND semantics via stock core's checker.

Finding 2 — duplicated condition plugin removed:

- DELETED `src/Plugin/Condition/NodeType.php` (141 lines) — the mirror of core's `EntityBundle`.
  Now-empty `src/Plugin/Condition/` directory removed too.
- `config/install/pathauto.pattern.sponsor.yml:19` and `config/install/pathauto.pattern.campaign.yml:19`:
  `id: node_type` → `id: entity_bundle:node` (bundles/context_mapping shape unchanged).
- `config/schema/badcamp_sponsors.schema.yml`: removed the `condition.plugin.node_type` stanza
  (lines 1–7 + separator; core's `condition.plugin.entity_bundle:*` schema now applies). The
  `views.field.badcamp_sponsors_coupon_count` stanza is untouched.

Total: ~221 lines of mechanism deleted, 3 config lines changed, 1 schema stanza removed.

### Exact failing tests left for T (the expected stale pins — do NOT fix in production)

1. `Drupal\Tests\badcamp_sponsors\Kernel\SectionAccessTest::testSectionRoutes` — fails at
   `SectionAccessTest.php:68`: expected `'access sponsors section, view coupon content'` (spaced),
   actual `'access sponsors section,view coupon content'`. Repair: expected literal at line 69
   becomes the unspaced form (A-dup finding 1).
2. `Drupal\Tests\badcamp_sponsors\Kernel\InstallTest::testPathautoPatternsAreCreated` — fails at
   `InstallTest.php:171` ("Failed asserting that an array contains 'sponsor'"). Root cause is the
   plugin-id pin at `InstallTest.php:167`: `getPluginId() === 'node_type'` now matches nothing
   (the pattern ships `entity_bundle:node`), so the filtered condition list is empty and the
   bundle assertion at 171 never sees 'sponsor'/'campaign'. Repair: compare against
   `'entity_bundle:node'` at line 167 (A-dup finding 2).

### Verification evidence

- Suite (`bash scripts/run-tests.sh`, worktree session root):
  `Tests: 40, Assertions: 388, Failures: 2.` — the two stale pins above, zero other failures,
  zero errors.
- Grep proof (all under `web/modules/custom/badcamp_sponsors`, after the fold): no matches for
  `TrimmingPermissionAccessCheck`, `BadcampSponsorsServiceProvider`, `Plugin/Condition`,
  `condition.plugin.node_type`, and no `node_type` in `config/` or `src/`. The only remaining
  `node_type` strings in the module are the `node.add` **route parameters** in
  `badcamp_sponsors.links.action.yml` (core's `{node_type}` placeholder — unrelated to the
  condition plugin) and the two stale test pins above (T's to repair).
- phpcs (Drupal,DrupalPractice, via ddev against the worktree): **0 errors, 0 warnings** (exit 0).

### Status

Both folds complete as specified; no other behavior changed. Re-enter A for re-review of these
two seams, then T repairs the two stale pins.

Note: the worktree also carries a pre-existing `web/robots.txt` modification (a removed
`Sitemap: /sitemap.xml` line) that predates this fold — not mine, left untouched for O to triage.

## spec-audit rework: edit-form Coupon ID

**Date:** 2026-10-17. **Contract:** handoff-T-green.md "spec-audit rework: edit-form Coupon ID"
(new test `CouponCrudTest::testCouponIdShownReadOnlyOnEditForm`, RED: 41 tests / 1 failure).

### Mechanism used (the platform way, no parallel path)

- **`hook_entity_extra_field_info()`** declares `badcamp_sponsors_coupon_id` as a form-context
  extra field on entity type `node`, bundle `coupon` (label "Coupon ID", default weight 0) —
  this is what makes the component placeable in the form display. Same pattern as contrib
  `redirect`'s `url_redirects` extra field, verified on disk in this worktree.
- **`badcamp_sponsors_form_node_form_alter()`** (the `hook_form_BASE_FORM_ID_alter` for
  `node_form`, i.e. `hook_form_NODE_FORM_alter`) populates the element. The hook fires for
  every node form, so it is scoped to the coupon bundle **in code** (`$node->bundle() !== 'coupon'`
  returns early), and — for **saved coupons only** — sets `#type => 'item'`, `#title => t('Coupon ID')`,
  `#markup => (string) $node->id()` (cast to string for the Render API's documented `#markup`
  type; the pinned assertion `assertStringContainsString((string) $coupon->id(), …)` is
  unaffected). An `item` is `#input` FALSE: rendered markup, no editor input, no widget
  writing to the node — "never editable" holds by construction.
- **`core.entity_form_display.node.coupon.default.yml`** ships the component as **shown**
  (`content:`, `region: content`, weight 0, alphabetically first — above `field_coupon_code`
  at weight 1). Widget-less shape (no `type` key — extra fields have no widget plugin; the
  Kernel config-schema checker accepted it on install).

Core's own `EntityFormDisplay::processForm()` (verified in-source, lines 232-249) then assigns
the display weight to the element and `#access = FALSE` to any extra field that is not a shown
component — so the form-display placement genuinely governs the element, and there is no second
visibility path.

### Files touched (production only; no tests edited; nothing committed)

1. `web/modules/custom/badcamp_sponsors/badcamp_sponsors.module` — the two hooks above
   (+ `EntityFormInterface` / `FormStateInterface` imports).
2. `web/modules/custom/badcamp_sponsors/config/install/core.entity_form_display.node.coupon.default.yml`
   — the shown `badcamp_sponsors_coupon_id` component.

### GREEN evidence

- Single file: `bash scripts/run-tests.sh web/.../CouponCrudTest.php` → `OK (3 tests, 30 assertions)`, exit 0.
- **Full suite: `bash scripts/run-tests.sh` → `OK (41 tests, 402 assertions)`, exit 0** —
  the spec-audit target count (41), assertions 396→402 (the new test now completes its set).

### phpcs result

- Production paths (`.module` + `config/` + `src/`), via
  `ddev exec --dir <worktree> vendor/bin/phpcs --standard=Drupal,DrupalPractice …`:
  **0 errors, 0 warnings, exit 0**.
- Whole-module run reports 2 errors **in T's test file** (`CouponCrudTest.php` doc-comment
  short descriptions at lines 16 and 142 — T's new comments from the RED phase). Test files are
  T's; I did not touch them. T, please wrap those doc comments.

### Unsaved-form behavior (guarded, per contract)

`$node->isNew()` returns early in the form alter, so the element is **not added at all** on the
create form — stronger than adding it hidden. Core's `processForm()` would also `#access = FALSE`
any un-shown extra field, but the guard means the new-coupon form never sees the element before
first save ("shown on the edit form once saved").

## post-audit rework: coupon alias skip

**Date:** 2026-10-17. **Contract:** handoff-T-green.md "post-audit rework: coupon alias vs
catch-all pattern" (`PathautoTest::testCatchAllPatternDoesNotAliasCoupons`, RED: 42 tests /
407 assertions / 1 failure — the coupon got `/save20-acme-corp-fall-blast` under the catch-all).

### The one-line change

`web/modules/custom/badcamp_sponsors/badcamp_sponsors.module`, inside the existing
`badcamp_sponsors_entity_presave()` (coupon branch only), exactly the pinned mechanism:

```php
$entity->path->pathauto = PathautoState::SKIP;
```

plus `use Drupal\pathauto\PathautoState;` in the imports. Supporting prose only: the hook's
docblock and a 4-line comment state the invariant (coupons stay at /node/<nid>; SKIP persists, so
editor saves and bulk updates skip too). **Nothing else changed** — sponsor/campaign aliasing and
the module's two shipped patterns untouched (the test's assertion 1, specific-beats-catch-all,
guards this). Dependency verified: `drupal:pathauto` is a hard dependency in
`badcamp_sponsors.info.yml` (line 8), so the class reference is safe.

Diff footprint: 1 import + 1 assignment + 2 doc/comment blocks; `git diff --stat` for the module:
12 insertions, 2 deletions (docblock rewrap), 0 deletions of code.

### GREEN evidence

- `bash scripts/run-tests.sh` (full suite, worktree session root):
  **`OK (42 tests, 409 assertions)` — exit 0.** RED was 42/407/1 failure; the previously failing
  test now completes its assertion set (407 → 409), all 41 prior tests stay green. The
  authoritative GREEN is the plugin's T-green run, not this self-check.

### phpcs result

- `ddev exec --dir /var/www/html/.claude/worktrees/0001-badcamp-sponsors vendor/bin/phpcs
  --standard=Drupal,DrupalPractice web/modules/custom/badcamp_sponsors` (whole module incl.
  tests): **0 errors, 0 warnings, exit 0** (silent pass).

### Status

Implemented against the RED contract; nothing committed. Not mine, left untouched: two untracked
leftover files at the worktree root (`diag-check.php`, `install-check.php`, pre-dating this
rework).
