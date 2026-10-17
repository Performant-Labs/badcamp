# Handoff-T-green: Phase 6 - Verify GREEN + Tier 2 (badcamp_sponsors)

**Date:** 2026-10-17
**Branch:** issue-0001-badcamp-sponsors (worktree .claude/worktrees/0001-badcamp-sponsors)
**Issue:** #1
**Handoff-F reviewed:** docs/handoffs/0001-badcamp-sponsors/handoff-F.md
**Handoff-T-red:** docs/handoffs/0001-badcamp-sponsors/handoff-T-red.md

## Verdict: GREEN

```
bash scripts/run-tests.sh          (authoritative command, from the run worktree)
OK (40 tests, 392 assertions)      EXIT CODE: 0
```

Run three times in this phase on the evolving end state (initial post-repair run, post-mutation
revert run, final run after a comment-only phpcs fix): **40 tests, 392 assertions, 0 failures,
0 errors, 0 skips** every time. F's claim that all 29 remaining Kernel errors were test-side is
**confirmed** — after the six repairs below (and nothing else), every test passes. F's self-check
(`Tests: 40, Assertions: 28, Errors: 29`) and my GREEN are consistent: the 29 errors were all the
same `SchemaObjectExistsException`, and F's method-level diagnostics (366 assertions across the
29) plus the 11 already-green tests account for the 392.

No production bugs found. No test left red. **Not committed** (per role).

## Test repairs (each verified against cited source BEFORE editing; F's word taken for nothing)

Every repair is environment-ordering or assertion-shape; **no contract fact was weakened** (table
in "No-weakening proof" below).

1. **Dropped the redundant `$this->installSchema('node', 'node_access')` after
   `module_installer()->install(['badcamp_sponsors'])`** in 8 Kernel tests (InstallTest,
   PathautoTest, CouponCrudTest, CouponValidationTest, CouponAccessTest, SectionAccessTest,
   DeleteBlockingTest, UninstallTest).
   *Source proof:* `ModuleInstaller::install()` expands the list with dependencies
   (ModuleInstaller.php:152-186) and `doInstall()` runs `$this->installSchema($module)` for each
   (line 348 → 763-768, unconditional `createTable()`); `node_schema()` defines `node_access`
   (node.install:15); `badcamp_sponsors` depends on `node`. So the table already exists when the
   test-side call runs, and `KernelTestBase::installSchema()` has no existence check
   (core/tests/.../KernelTestBase.php:781) → the 29 `SchemaObjectExistsException`s.
   *Approach choice:* dropping the line (vs moving `node` into `static $modules`) keeps the
   strongest meaning — `node` still arrives solely through the module's own dependency resolution
   by the module installer, i.e. the exact `drush en` equivalence the RED contract pinned, and
   `InstallTest::testDependenciesAreEnabled` keeps proving the module's `.info.yml` deps (with
   `node` pre-enabled in `$modules` that test would become trivially green). Applied consistently
   to all 8; **WorkflowExclusionTest untouched** (its `node` is pre-enabled in `$modules` and its
   `installSchema` call runs before the module install, so it is both valid and needed there).
2. **`$this->installConfig(['user'])` → `['user', 'system']`** in the six node-saving tests
   (PathautoTest, CouponCrudTest, CouponValidationTest, CouponAccessTest, DeleteBlockingTest,
   UninstallTest).
   *Source proof:* first node save → pathauto → token info → `SystemTokensHooks::tokenInfo()`
   calls `DateFormatter::format($request_time, 'medium')` (SystemTokensHooks.php:69-72); with no
   `system` config installed both `medium` and the line-98 fallback resolve to null →
   `getPattern() on null` fatal (DateFormatter.php:88-99). `system`'s config/install ships
   `core.date_format.*` (verified on disk). InstallTest/SectionAccessTest save no nodes and did
   not need it.
3. **UninstallTest: added `$this->installSchema('user', 'users_data')`.**
   *Source proof:* uninstalling fires `UserHooks::modulesUninstalled()` → `user.data`→
   `UserData::delete($modules)` → `DELETE FROM users_data` (UserHooks.php:315-319,
   UserData.php:96-99); the table comes from `user_schema()` (user.install:12) and is not created
   for modules pre-enabled in `$modules` — only the test can create it. UninstallTest is the only
   test that uninstalls anything.
4. **DeleteBlockingTest::hardDelete(): `get('trash')` → `get('trash.manager')`.**
   *Source proof:* `web/modules/contrib/trash/trash.services.yml:6-8` — services are
   `trash.manager` and the alias `Drupal\trash\TrashManagerInterface`; **no `trash` id exists**.
   No assertion changed; the soft-delete-before-hard-delete behavior is identical.
5. **InstallTest::testContentOverviewIntegration: filters read from the `default` display**
   (was `page_1`).
   *Source proof:* core's shipped `web/core/modules/node/config/optional/views.view.content.yml`
   defines `filters:` (incl. the `type` filter, `plugin_id: bundle`, `value: {}`) inside the
   **default** display's `display_options` (default at line 15, filters at 348+); `page_1`
   (line 654+) carries no `filters` key — views merges default-display options into pages only at
   runtime, and the test reads raw config. The same three assertions now run where the config
   actually lives: type filter exists / `plugin_id === 'bundle'` / `value === []` (unrestricted,
   so every node type incl. sponsor/campaign/coupon is offered). Nothing relaxed.
6. **PathautoTest::getAliases(): `array_values(array_map(...))`.**
   *Source proof:* `loadByProperties()` keys entities by alias id (≥ 1) and `array_map` preserves
   keys, so the result was `[1 => '/sponsor/acme-corp']`; `assertSame(['/sponsor/acme-corp'], …)`
   demands key 0 and could never pass against a real alias. With `array_values` the unchanged
   `assertSame` still asserts **exactly one** alias equal to the exact string — same contract
   fact (AC 8), brittle-key dependency removed. `testCouponsGetNoAlias`'s `assertSame([], …)` is
   unaffected. UninstallTest's same-named helper was **deliberately left alone**: its only caller
   uses `assertNotEmpty` (key-insensitive).

## No-weakening proof (old → new, per changed assertion)

| Change | Old | New | Why the same contract fact |
|---|---|---|---|
| (e) display read | `['page_1']['display_options']['filters']` | `['default']['display_options']['filters']` | Core stores the type filter on the default display only; page_1 inherits at runtime. Assertions unchanged (exists / `bundle` / `[]`). Verified against core YAML. |
| (f) alias keys | `array_map(...)` (keys = alias ids) | `array_values(array_map(...))` | `assertSame(['/sponsor/acme-corp'], …)` unchanged: still exactly-one-alias, exact value. Key-0 was unsatisfiable, not a contract fact. |
| (a) schema line | `installSchema('node','node_access')` after install | (line removed) | No assertion references `node_access`; the table still exists, now created by the real installer path (stronger: exercises `drush en` exactly). |
| (b)(c)(d) | — | env prerequisites / real service id | Zero assertion changes; they unblocked the already-pinned behaviors. |

## Tier 1 results (run independently by T)

| Check | Command | Expected | Actual | |
|---|---|---|---|---|
| Full suite | `bash scripts/run-tests.sh` | 40 green | `OK (40 tests, 392 assertions)`, exit 0 | PASS |
| Coding standard | `ddev exec --dir <worktree> vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom/badcamp_sponsors` | 0/0 | 0 errors, 0 warnings (after fixing the capitalization of a comment T itself had added) | PASS |
| Dependencies resolve | `composer.lock` in worktree | views_data_export + csv_serialization pinned | `drupal/views_data_export 1.10.0`, `drupal/csv_serialization 4.0.1`; composer.json `^1.10` | PASS |

## Tier 2 results

### Mutation spot-checks (one at a time; each reverted byte-exact via `git checkout -- <file>`; `git diff` on production paths empty afterwards; suite green again at the end)

| # | Mutation (production, temporary) | Test file re-run | Result |
|---|---|---|---|
| 1 | `hook_entity_access` delete-block return `forbidden()` → `neutral()` (badcamp_sponsors.module:52) | DeleteBlockingTest | **FAILS as intended** — `testDeleteIsBlockedWhileReferenced` (line 161) + `testDeleteIsAllowedOnceUnreferenced` (line 195); 2 failures |
| 2 | Title builder separator `' — '` → `' - '` (CouponTitleBuilder.php:29) | CouponTitleBuilderTest | **FAILS as intended** — 3/3 failures, exact `—` vs `-` diffs |
| 3 | `hook_module_preuninstall` trash-context `ignore` wrapper dropped (plain `$purge()`) | UninstallTest | **FAILS as intended** — `testUninstallRemovesEverything`: "Failed asserting that 4 is identical to 0" (soft-deleted rows survive) |
| 4 | Counter made live-only (trashed-sum removed, CouponCounter.php:77-79) | DeleteBlockingTest | **FAILS as intended** — "Failed asserting that 1 is identical to 2" at `testCouponCounterIsTrashAware` (line 138) + trashed-campaign delete unblocked (line 164) |

4/4 bite → the suite genuinely pins behavior, not implementation presence.

### Cross-checks of F's claims

- **composer.lock:** `drupal/views_data_export 1.10.0` + `drupal/csv_serialization 4.0.1` present; `drupal/views_data_export: ^1.10` in composer.json. **Confirmed.**
- **phpcs:** F claimed 0/0; my first run found 1 error — in a comment **T had just added** (DeleteBlockingTest.php:98 lowercase start). Fixed; now 0/0. F's claim held for F's code.
- **F's GREEN/deviation claims:** F's self-check numbers (40/28/29) reproduced in shape before repair; post-repair 40/392/0 matches F's diagnostics prediction. F's six test-bug claims verified against core/contrib source, all accurate (details above).

### Structural checks

- **Coverage per AC:** AC 1 InstallTest; AC 2 CouponCrudTest + InstallTest::testContentOverviewIntegration; AC 3 CouponValidationTest; AC 4 DeleteBlockingTest; AC 5 CouponAccessTest; AC 6/7 SectionAccessTest (+ export displays in InstallTest); AC 8 PathautoTest; AC 10 UninstallTest; workflow-exclusion + privacy bundle settings + auto-title + hidden widget covered per brief. AC 9 = post-merge walkthrough, PENDING by design (journal). No coverage hole introduced by the repairs.
- **Test quality:** repairs added no tests and deleted no tests; suite remains proportionate (40 methods). Each test still names a behavior, sits at the cheapest sufficient tier, asserts behavior not implementation (mutations 1-4 prove the suite bites).
- **Type safety:** `declare(strict_types=1)` throughout; no new type juggling introduced. Noted: `CouponCounter` accepts `NodeInterface|int|string` (F deviation 1) — required because the pinned call site passes `$sponsor_a->id()`, and Drupal node ids are numeric strings; values are cast to int internally. Not a weakening; no assertion touches the signature.
- **Error paths / data integrity / security / contract:** green tests back them (validation violations incl. trim case; uninstall purity incl. trashed rows + re-enable; access denials; exact permission strings on routes).
- **Migration safety:** N/A — no schema migrations exist (config-only module); reversibility covered by UninstallTest::testReEnablingWorks.
- **Playwright/e2e:** N/A for this project (Drupal; no e2e workspace). No browser tests per issue (worktree limitation) — the real pages remain AC 9's post-merge walkthrough.

## Acceptance criteria status

| AC | Status | Backed by |
|---|---|---|
| 1 Install creates everything, deps enabled | PASS | InstallTest (7 methods) |
| 2 Types in /admin/content, CRUD | PASS | CouponCrudTest, InstallTest::testContentOverviewIntegration |
| 3 Validation (refs, unique code w/ trim) | PASS | CouponValidationTest |
| 4 Delete blocked while referenced (incl. trashed) | PASS | DeleteBlockingTest (mutations 1 & 4 bite) |
| 5 Coupon view 403 without permission | PASS | CouponAccessTest |
| 6 Section pages + permissions | PASS | SectionAccessTest |
| 7 CSV exports per list | PASS | SectionAccessTest::testCsvRoutesSharePermissions + InstallTest export displays |
| 8 Aliases (sponsor/campaign yes, coupon no) | PASS | PathautoTest |
| 9 Post-merge walkthrough | PENDING by design | journal (not faked) |
| 10 Uninstall purity + re-enable | PASS | UninstallTest (mutation 3 bites) |

## Blocking issues

None.

## Advisory notes (non-blocking)

- `web/robots.txt` shows as modified in the worktree — pre-existing scaffold drift, flagged
  do-not-commit since T-red; not part of this run.
- UninstallTest carries a `getAliases()` helper with the same pre-repair key shape as
  PathautoTest's; left untouched because its only assertion (`assertNotEmpty`) is key-insensitive.
  If a future assertion there needs exact keys, apply the same `array_values` treatment.
- F deviation 5 (coupon-count views column as a field plugin, not SQL aggregation; not
  click-sortable) and deviation 3 (TrimmingPermissionAccessCheck swap) are accepted trade-offs
  already recorded in handoff-F; SectionAccessTest pins the permission strings and
  both-permission allow that motivated them.

## Next

No UI surface in this run (brief: uiSurface `false`) — **U is N/A; ready for S.**

## a-dup round 2 repairs

**Date:** 2026-10-17. **Context:** F folded A-dup findings 1 & 2 (handoff-F "Fold (a-dup round
2)"); production-only, leaving exactly two stale T pins failing. Tests-only repairs below; no
production edits; nothing committed.

### Repairs (old → new, both verified against on-disk config + core before editing)

1. `SectionAccessTest.php` (pin at line 69 → 71 after a 2-line comment addition):
   `'access sponsors section, view coupon content'` → `'access sponsors section,view coupon content'`.
   On-disk fact: `views.view.coupons.yml:561` ships the unspaced form (default display, inherited
   by page_1 + data_export_1, so the CSV route too). **No weakening:** the pin asserts the same
   contract fact — the route's `_permission` requirement lists BOTH permissions — and core
   `PermissionAccessCheck.php:37` (`explode(',', …)` → `'AND'`) makes the unspaced literal the
   only correct spelling of it (a spaced string would leave a leading space on the second perm).
   The behavioral proof is untouched: `testSectionRouteAccess` still shows section-manager denied
   / full-manager allowed on `view.coupons.page_1`.
2. `InstallTest.php:167`: `getPluginId() === 'node_type'` → `getPluginId() === 'entity_bundle:node'`.
   On-disk fact: both `pathauto.pattern.sponsor.yml:19` and `pathauto.pattern.campaign.yml:19`
   ship `id: entity_bundle:node`. **No weakening:** surrounding assertions (pattern present,
   pattern string, type, bundle membership) intact; core's live mechanism is
   `#[Condition(id: "entity_bundle", deriver: EntityBundleDeriver::class)]` → derived id
   `entity_bundle:node`, and core's node module ships no `node_type` condition.

### RED baseline (pre-repair, matches F's evidence)

`Tests: 40, Assertions: 388, Failures: 2` — exactly the two stale pins
(`InstallTest::testPathautoPatternsAreCreated` at :171; `SectionAccessTest::testSectionRoutes`
spaced-vs-unspaced diff). Exit 1.

### GREEN (post-repair)

`bash scripts/run-tests.sh`, two consecutive runs, both `OK (40 tests, 392 assertions)`, exit 0
(388 → 392: the two tests now complete their assertion sets).

### Bite-checks (temporary config mutations via bash; byte-exact revert proven by sha256)

1. **Perm string** — `views.view.coupons.yml:561` temporarily `'access sponsors section,view
   coupon content'` → `'access sponsors section'`. SectionAccessTest: **2 failures, exit 1**:
   `testSectionRoutes` at :70 (the repaired pin: `-'access sponsors section,view coupon content'
   /+'access sponsors section'`) AND `testSectionRouteAccess` at :133 ("The coupons page also
   requires the coupon-view permission") — the contract bites at both the pin and the behavior.
   Reverted; sha256 `9f2e9850…` matches pre-mutation hash.
2. **Pathauto selection id** — `pathauto.pattern.sponsor.yml:19`, two variants, InstallTest red
   on both (9 errors, exit 2):
   - `entity_bundle:node` → `node_type`: `PluginNotFoundException: The "node_type" plugin does
     not exist. Valid plugin IDs … are: request_path, current_theme, response_status, user_role,
     entity_bundle:node` — core's own discovery list confirms the repair's factual basis.
   - `entity_bundle:node` → `request_path` (resolvable id, wrong shape):
     `SchemaIncompleteException: …selection_criteria.9f0a5ec2….bundles missing schema`.
   Any drift of that id — resolvable or not — hard-fails InstallTest at install; the :167 pin
   remains the runtime-level guard for cleanly-installing shapes. Reverted; sha256 `2144819d…`
   matches pre-mutation hash.

### Tier 1 after repairs

- Suite after bite-reverts: `OK (40 tests, 392 assertions)`, exit 0.
- phpcs (`vendor/bin/phpcs --standard=Drupal,DrupalPractice` on
  `web/modules/custom/badcamp_sponsors`, via ddev against the worktree): **0 errors, 0 warnings**,
  exit 0.
- `git status`: config/ at exactly F's fold state after reverts (no bite residue); the only new
  diffs are the two test pins + this handoff/journal. Pre-existing `web/robots.txt` drift
  untouched, still do-not-commit.

**Final suite line: `OK (40 tests, 392 assertions)` — exit 0.**

## spec-audit rework: edit-form Coupon ID

**Date:** 2026-10-17. **Context:** S round 1 REWORK item 1 (handoff-S.md:156-176) — the issue's
field spec requires the Coupon ID "shown in lists and on the edit form once saved; never
editable"; lists were covered, the edit form was not. T authors the missing coverage first
(RED); tests only, no production edits, nothing committed.

### New test

`web/modules/custom/badcamp_sponsors/tests/src/Kernel/CouponCrudTest.php::testCouponIdShownReadOnlyOnEditForm`
— placed in CouponCrudTest because that is where the suite's coupon form-display contract
already lives (`testCouponTitleWidgetIsHidden`, config pinned via `EntityFormDisplay::load`);
extends the class docblock to cover "coupon form-display contracts". It seeds a saved coupon
(sponsor + campaign helpers reused), builds the real edit form via
`entity.form_builder->getForm($coupon, 'default')` (entity-form level, Kernel-safe, no browser),
then asserts:

1. `$form['badcamp_sponsors_coupon_id']` exists;
2. its `#type` is `'item'` — a Form API read-only element (`#input` FALSE): rendered markup,
   never an editable widget writing to the node;
3. its `#title` renders as `Coupon ID`;
4. its `#markup` contains the saved nid;
5. the `node.coupon.default` form display carries `badcamp_sponsors_coupon_id` as a **shown**
   component (not in `hidden`) — the config contract behind the built form, pinned the same way
   the suite pins other form-display facts.

### Contract pinned for F (the mechanism to implement)

A `badcamp_sponsors_coupon_id` **extra field** (pseudo-field) on the `node.coupon` form
display, declared in `hook_entity_extra_field_info()`, placed as a component in
`core.entity_form_display.node.coupon.default.yml` (`region: content`, not hidden), and
populated for **saved** coupons only in `hook_form_NODE_FORM_alter`
(`badcamp_sponsors_form_node_coupon_form_alter`) as:

```php
$form['badcamp_sponsors_coupon_id'] = [
  '#type' => 'item',
  '#title' => t('Coupon ID'),
  '#markup' => $node->id(),
];
```

An `item` is `#input` FALSE, so "never editable" holds by construction (no widget exists for
nid); skip the element (or render it empty) when `$node->isNew()` — "once saved". Weight is
implementation latitude (S allowed form top or footer; the code field starts at weight 1, so 0
sits naturally above it).

### RED evidence

- Single file: `bash scripts/run-tests.sh web/modules/custom/badcamp_sponsors/tests/src/Kernel/CouponCrudTest.php`
  → `Tests: 3, Assertions: 24, Failures: 1`, exit 1:
  `CouponCrudTest::testCouponIdShownReadOnlyOnEditForm — The Coupon ID element is present on the
  built coupon edit form. Failed asserting that an array has the key
  'badcamp_sponsors_coupon_id'. (CouponCrudTest.php:161)` — fails for exactly the right reason
  (the ID component/element is absent), not an import/setup error; the form build itself
  succeeds (24 assertions ran before the failure) and the file's other 2 tests stay green.
- Full suite: `bash scripts/run-tests.sh` → `Tests: 41, Assertions: 396, Failures: 1`, exit 1 —
  exactly the new test; no collateral damage (40 pre-existing all pass).

**RED is valid; F may implement against it.**

### Rework GREEN (2026-10-17)

- Nits fixed: the 2 phpcs doc-comment errors F flagged in `CouponCrudTest.php` (class + method short descriptions, lines ~16/~142) are now single lines ending in a period; descriptions kept accurate, no behavior or assertion changes.
- phpcs (`Drupal,DrupalPractice` on `web/modules/custom/badcamp_sponsors`, tests included): 21/21 files clean — 0 errors, 0 warnings, exit 0.
- Full suite: `bash scripts/run-tests.sh` → `OK (41 tests, 402 assertions)`, exit 0.

## post-audit rework: coupon alias vs catch-all pattern

**Date:** 2026-10-17. **Context:** operator-sanctioned rework after a live-site finish check exposed
a spec violation the Kernel env could not see. The Drupal CMS starter site ships `menu_path`, a
pathauto pattern with **no selection criteria** at weight 10
(`/[node:menu-link:parents:join-path]/[node:title]`), so it matches **every** node bundle —
including coupons. **Live evidence:** a saved coupon on the running site got the alias
`/save20-acme-corp-fall-blast`, violating the issue's settled decision *"Coupon: no alias
(confirmed). Coupons are private and stay at /node/<nid>"*. T authors the RED first; tests only;
no production edits; nothing committed.

### Why the original suite could not see it

The Kernel tests install only what `badcamp_sponsors` ships: the two bundle-specific patterns
(`sponsor`, `campaign`, weight 0). No pattern matches a coupon, and
`PathautoState::getValue()` (PathautoState.php:53-57) computes **SKIP** when no pattern matches —
so `testCouponsGetNoAlias` passed vacuously: nothing existed to alias the coupon. The live site's
catch-all is exactly the input the fixture was missing.

### New test

`PathautoTest::testCatchAllPatternDoesNotAliasCoupons` — reproduces the live shape inside the
test: `PathautoPattern::create` with `type: canonical_entities:node`, `pattern: /[node:title]`
(simpler token string than `menu_path`, same matching surface — zero selection criteria), `weight:
10`, `status: TRUE` (the generator filters on enabled patterns, PathautoGenerator.php:328). Then:

1. **Sponsor still specifically aliased** — `['/sponsor/acme-corp']`: the bundle-specific pattern
   (weight 0) must beat the catch-all (weight 10). Mechanism: `getPatternByEntityType()` sorts by
   weight (:329) and `getPatternByEntity()` returns the **first** pattern that applies
   (:347-363) — this assertion passed pre-fix and guards the fix from "solving" the problem by
   breaking sponsor/campaign aliases.
2. **Coupon gets NO alias at all** — `[]` against `/node/<nid>`. **This is the RED assertion**
   today: the catch-all matches the coupon and aliases it.
3. **Mechanism pinned (F's exact approach)** — after saving the coupon, the node's path field
   `pathauto` property equals `PathautoState::SKIP` (in-memory) **and** the state is persisted in
   keyvalue `pathauto_state.node` (the durable store `PathautoState::persist()` writes from
   `PathautoItem::postSave()`, PathautoItem.php:36 — what makes later editor saves and pathauto
   bulk updates skip too, since `updateEntityAlias` runs for `update` ops with the same check).

Assertion 3 lives in the same method deliberately: **without** a matching pattern, a fresh coupon's
`pathauto` computes SKIP anyway (no pattern matches → SKIP, PathautoState.php:56), so a standalone
mechanism test would be green pre-fix — an invalid RED. Only under the catch-all does the state
compute CREATE, making the SKIP pin genuinely red today and meaningfully green after the fix.

### RED evidence

- Single file: `bash scripts/run-tests.sh web/modules/custom/badcamp_sponsors/tests/src/Kernel/PathautoTest.php`
  → `Tests: 3, Assertions: 17, Failures: 1`, exit 1:

  ```
  1) Drupal\Tests\badcamp_sponsors\Kernel\PathautoTest::testCatchAllPatternDoesNotAliasCoupons
  Coupons get no alias even when a catch-all pattern matches them.
  Failed asserting that two arrays are identical.
  --- Expected
  +++ Actual
  @@ @@
  -Array &0 []
  +Array &0 [
  +    0 => '/save20-acme-corp-fall-blast',
  +]
  ```

  Fails at PathautoTest.php:178 — the **behavior** assertion, reproducing the live-site alias
  byte-for-byte (`/save20-acme-corp-fall-blast`), not an import/setup error; the sponsor assertion
  (specific beats catch-all) ran green before it; the file's other 2 tests stay green.
- Full suite: `bash scripts/run-tests.sh` → `Tests: 42, Assertions: 407, Failures: 1`, exit 1 —
  exactly the new test; **all 41 prior tests green**, no collateral damage.
- phpcs (via ddev against the worktree, `Drupal,DrupalPractice` on the changed file): 0 errors,
  0 warnings, exit 0.

**RED is valid; F may implement against it.**

### Pinned contract for F (the mechanism to implement)

In the existing `badcamp_sponsors_entity_presave()` hook, for coupon-bundle nodes, set:

```php
$entity->path->pathauto = PathautoState::SKIP;
```

(with `use Drupal\pathauto\PathautoState;` in the `.module` file). This is the canonical pathauto
seam, verified against this checkout's contrib source:

- `FieldItemList::__set()` (core FieldItemList.php:127-132) auto-creates the item on an empty
  list, and for new entities `PathautoFieldItemList::computeValue()` has already seeded `list[0]`
  (with CREATE) — the assignment lands on a real item either way.
- Presave runs before `pathauto_entity_insert`/`pathauto_entity_update`
  (pathauto.module:57/65 → `PathautoEntityHooks`), so when
  `PathautoGenerator::updateEntityAlias()` reaches **PathautoGenerator.php:393** —
  `if ($entity->path->pathauto != PathautoState::CREATE && empty($options['force']))` — it
  returns NULL and no alias is created, whatever pattern matches.
- `PathautoItem::postSave()` then `persist()`s SKIP into keyvalue `pathauto_state.node`, so the
  state survives the request: later editor saves hit the same :393 check, and bulk alias
  updates/deletes honour the stored state. In this pathauto build `SKIP = 0`, `CREATE = 1`.

Nothing else may change: sponsor/campaign aliasing (assertion 1) and the module's two shipped
patterns stay exactly as they are.

**RED state: suite `Tests: 42, Assertions: 407, Failures: 1` (exit 1) — awaiting F.**


---

## walkthrough fix: coupons view access (post-merge fix loop, AC 9 defect) — 2026-10-17

**Context:** the walkthrough found `/sponsors/coupons` rendering EMPTY (200, no view element) for
every non-admin: the display's access string `'access sponsors section,view coupon content'` is a
single-permission lookup for views' Permission plugin (`user/src/Plugin/views/access/Permission.php:89`)
while only the ROUTE layer AND-splits commas (`:96` sets `_permission` verbatim; core's
`PermissionAccessCheck` splits). Route passed for managers → 200; display check failed for everyone
→ no view element. The suite missed it because no test RENDERED the view as a non-admin. This is
the RED suite for F's fix (operator-sanctioned fix loop; journal entry "AC 9 walkthrough").

### Tests authored — `web/modules/custom/badcamp_sponsors/tests/src/Kernel/CouponsViewRenderAccessTest.php`

All Kernel (the failing layer is a Drupal render/access path; a Unit tier cannot reach it), one
shared setUp seeding sponsor + campaign + coupon `WTSAVE20`, mirroring the module's established
bootstrap (`$modules = ['system','user']`, `module_installer` install, router rebuild, user 1
created first so every assertion account is regular):

| Test | Pins | Tier |
| --- | --- | --- |
| `testManagerSeesCouponCodesOnCouponsPage` | The shipped defect: display gate `$view->access('page_1', $manager)` must be TRUE and the PAGE build (page-controller path) must render `views-element-container` + `WTSAVE20` for a real user carrying the shipped `sponsorship_manager` role, via `account_switcher` | Kernel — **RED** |
| `testCouponsDisplaysUseSectionAndCouponAccessPlugin` | Both coupons displays (`page_1` + `data_export_1`, found by plugin type) resolve to access plugin id `badcamp_sponsors_section_and_coupon` | Kernel — **RED** |
| `testSectionOnlyUserIsDeniedOnCouponsPage` | Denial AND-leg 1: display gate FALSE, no view element, no code leak, `checkNamedRoute('view.coupons.page_1')` FALSE | Kernel — green guard (must STAY green) |
| `testCouponOnlyUserIsDeniedOnCouponsPage` | Denial AND-leg 2 (inverse permissions): same three denials | Kernel — green guard |
| `testAnonymousIsDeniedOnCouponsPage` | Anonymous (`AnonymousUserSession`): same denials | Kernel — green guard |
| `testCouponsRouteRequiresUnspacedPermissionPair` | ROUTE requirement is byte-exact `'access sponsors section,view coupon content'` — the fix's `alterRouteDefinition` contract preserving 403s | Kernel — green pin (must STAY green; deliberately restates SectionAccessTest's route assertion at the seam F's plugin re-derives) |
| `testSponsorsPageStillRendersForSectionOnlyUser` | Over-fix guard: single-perm sponsors view still renders its element + seeded sponsor for a section-only user | Kernel — green guard |

The render helper `renderViewPageAs()` is the page controller's exact build:
`router.route_provider->getRouteByName('view.<id>.page_1')` → `Page::buildBasicRenderable()` →
`'#type' => 'view'`, `'#embed' => FALSE` → `renderer->renderRoot()` — so the element pre-render
gate (`views/src/Element/View.php:64` `$view->access()`) that failed in production runs, and inside
it `executeDisplay('page_1')` (`Element/View.php:81`). Plain `executeDisplay` alone does NOT
consult display access (ViewExecutable.php:1699-1702) — rendering through the element is what
makes this the test class that would have caught the shipped bug.

### Existing assertions audited (task: update stale display-level perm assertions)

Grepped `perm` across `InstallTest` / `SectionAccessTest`: every match targets the ROUTE
(`getRouteByName(...)->getRequirement('_permission')`) or the role permission list — **no
display-level access assertions exist to update**, and the fix keeps all of those byte-identical
(`alterRouteDefinition` re-sets the same unspaced string). All 42 pre-existing tests untouched and
green. The display-level plugin contract (stale by absence — part of why the bug shipped) is newly
pinned in `testCouponsDisplaysUseSectionAndCouponAccessPlugin`.

### RED evidence

`bash scripts/run-tests.sh` (full suite, session root) → `Tests: 49, Assertions: 466, Failures: 2`,
exit 1:

```
1) Drupal\Tests\badcamp_sponsors\Kernel\CouponsViewRenderAccessTest::testManagerSeesCouponCodesOnCouponsPage
The coupons display grants access to a sponsorship manager.
Failed asserting that false is true.                              (CouponsViewRenderAccessTest.php:150)

2) ...::testCouponsDisplaysUseSectionAndCouponAccessPlugin
The coupons page_1 display uses the section-AND-coupon access plugin.
Failed asserting that two strings are identical.
-'badcamp_sponsors_section_and_coupon'
+'perm'                                                          (CouponsViewRenderAccessTest.php:181)
```

Both fail on the **feature's own assertions** (the AND-grant for the manager; the plugin id), not
setup/import errors. The passing sponsors-view guard proves the render harness renders real view
HTML for a non-admin, so the manager test's failure is the access bug alone — valid RED. phpcs
(`Drupal,DrupalPractice` via ddev) on the new file: 0 errors, 0 warnings.

### Pinned contract for F

- **Plugin id:** `badcamp_sponsors_section_and_coupon`.
- **Class location:** `web/modules/custom/badcamp_sponsors/src/Plugin/views/access/` (attribute
  `#[ViewsAccess(id: ...)]`, extends `AccessPluginBase`), discovered automatically from the module.
- **`access(AccountInterface $account)`:** `AccessResult::allowedIf($account->hasPermission('access sponsors section') && $account->hasPermission('view coupon content'))` — the AND of the two permissions, `->cachePerPermissions()` (cache contexts `user.permissions`, matching core Permission.php:154-156).
- **`alterRouteDefinition(Route $route)`:** `$route->setRequirement('_permission', 'access sponsors section,view coupon content')` — byte-exact UNSPACED string, so the route layer keeps its AND-split 403 behavior (`testCouponsRouteRequiresUnspacedPermissionPair` + SectionAccessTest pin this).
- **Config:** the coupons view's displays must resolve to the plugin — effective access plugin id
  `badcamp_sponsors_section_and_coupon` for BOTH `page_1` and `data_export_1` (display-level
  `access.type` switch on the shipped config; sponsors/campaigns views stay on `perm`).
- **Nothing else may change:** all three denial tests, the route-requirement pin, the
  sponsors-view guard, and the 42 pre-existing tests must stay green.

**RED state: suite `Tests: 49, Assertions: 466, Failures: 2` (exit 1) — awaiting F.**

## walkthrough fix round 2: workflow exclusion defense — 2026-10-17

**Context:** second live-found defect (operator-sanctioned fix loop; journal "AC 9 walkthrough").
The starter site's `auto_enable_moderation` ECA template ("Enable moderation for new content types")
adds ANY new content type to the `basic_editorial` workflow, so `drush en badcamp_sponsors` on the
running site ended with sponsor/campaign/coupon moderated (`isModeratedEntity(node 20) === true`):
every programmatic/form save born DRAFT (status forced 0) and canonical routes 404 via the site's
`unpublished_404` ECA. Violates the settled "None of the three types uses the editorial workflow"
and AC 1's "installs cleanly on this site as it is". WorkflowExclusionTest cannot see it (no ECA in
the Kernel env — it installs against a CLEAN workflow and only proves the module adds nothing
itself). This is the RED suite for F's install-time defense.

### The reproduction problem, and its faithful solution

Two plausible simulations are structurally impossible in this core:

- A workflow cannot be SAVED referencing bundles that do not exist —
  `EntityType::getBundleConfigDependency()` throws `LogicException: Missing bundle entity`
  (EntityType.php:964, via ContentModeration::calculateDependencies():227). The live ECA never hits
  this: it fires AFTER type creation.
- The module's OWN default config (`node.type.sponsor` etc.) cannot pre-exist its installation —
  `PreExistingConfigException` (ConfigInstaller.php:594 via ModuleInstaller.php:209). So the types
  cannot be hand-created before install.

The live mechanism itself is the answer: a **test helper module**
`tests/modules/badcamp_sponsors_eca_sim/` whose `hook_node_type_insert()` adds every newly created
node type to every content_moderation workflow governing node bundles. Enabled before
`badcamp_sponsors`, it contaminates the workflows INSIDE the module's own config import — node
types are created at ModuleInstaller.php:448, `hook_install()` is invoked at :451, so the
contamination is guaranteed present at the defense point. That is byte-for-byte the live sequence
(ECA reacting to type creation within the import), not an approximation of its end state.

### Tests authored — `web/modules/custom/badcamp_sponsors/tests/src/Kernel/InstallDefendsWorkflowExclusionTest.php`

Both Kernel (the contract is install-time config-entity surgery + entity-save behavior; nothing
here is Unit-tier). Shared setUp: node/workflows/content_moderation + the sim module in `$modules`,
user+node+content_moderation_state schemas (moderated saves write CMS rows — all 19 core moderation
Kernel tests install it), `installConfig(['user','system'])` (token chain on pathauto saves),
bundles page+article, and TWO CLEAN workflows: `basic_editorial` → [page], `coupon_review` →
[article].

| Test | Pins | Tier |
| --- | --- | --- |
| `testInstallRemovesModuleBundlesFromEveryNodeWorkflow` | After install, `basic_editorial` governs exactly `['page']` and `coupon_review` exactly `['article']` (set-compared after sort — the other governed bundles are untouched), and NO workflow in storage governs sponsor/campaign/coupon | Kernel — **RED** |
| `testNodesSavedAsPublishedStayPublished` | The moderation hijack symptom is gone: one node per bundle saved with `status => 1` reloads published (`isPublished()` true, status value 1) and `moderation_information->isModeratedEntity()` false | Kernel — **RED** |

Self-validation (guards against a vacuous GREEN if the sim module ever silently breaks):
`assertEcaSimulationFires()` creates a throwaway `sim_probe` type, asserts BOTH workflows pick it
up (the ECA behavior), then deletes it — core's `hook_entity_bundle_delete`
(ContentModerationHooks:321) removes it again, leaving the fixtures clean. The failing actual
arrays in the RED run are themselves direct evidence the sim fired.

### RED evidence

`bash scripts/run-tests.sh` (full suite, session root) → `Tests: 51, Assertions: 499, Failures: 2`,
exit 1 — the 49 pre-existing tests (round-1 fix in tree) all green, WorkflowExclusionTest
untouched and green (different angle: clean site, module adds nothing). Single-file run:
`Tests: 2, Assertions: 30, Failures: 2`:

```
1) ...InstallDefendsWorkflowExclusionTest::testInstallRemovesModuleBundlesFromEveryNodeWorkflow
basic_editorial still governs page and only page after install.
Failed asserting that two arrays are identical.
--- Expected / +++ Actual
 Array &0 [
-    0 => 'page',
+    0 => 'campaign',  1 => 'coupon',  2 => 'page',  3 => 'sponsor',
 ]                                                               (InstallDefendsWorkflowExclusionTest.php:132)

2) ...InstallDefendsWorkflowExclusionTest::testNodesSavedAsPublishedStayPublished
A sponsor saved with status 1 stays published.
Failed asserting that false is true.                            (InstallDefendsWorkflowExclusionTest.php:206)
```

Both fail on the feature's own assertions — the bundles remain in the workflow after install, and
a published save is born draft (computed `moderation_state` → initial `draft`
[ModerationStateFieldItemList:61 → ContentModeration::getInitialState:301] → `setUnpublished()`
[ModerationHandler.php:54]) — not on setup/import errors. phpcs (`Drupal,DrupalPractice` via ddev)
on the test file + both sim-module files: 0 errors, 0 warnings.

### Pinned contract for F

- **Where:** `badcamp_sponsors_install()` in a new `badcamp_sponsors.install` — `hook_install()`
  is the contract (runs at module enable AFTER the config import that triggers the ECA,
  ModuleInstaller.php:448→:451). A `hook_modules_installed` variant is explicitly NOT wanted.
- **What:** remove `sponsor`, `campaign`, `coupon` from EVERY workflow entity whose type plugin
  covers node bundles — not just `basic_editorial`, not just workflows whose plugin is
  content_moderation by name (the second contaminated workflow is asserted). The plugin API is
  `$workflow->getTypePlugin()->removeEntityTypeAndBundle('node', $bundle)` (ContentModeration.php:149).
- **Save only when something actually changed** (a save fires `workflow_update` →
  `clearCachedBundles()` [ContentModerationHooks:356], which is also what makes the post-install
  node saves in test 2 see clean bundle info — do not bypass it with raw config writes).
- **Scope:** other governed bundles must survive untouched (`['page']` / `['article']` exact
  sets); deleting the bundles' membership must not touch workflow states/transitions.
- **Nothing else may change:** all 49 pre-existing tests must stay green, WorkflowExclusionTest
  verbatim.

**RED state: suite `Tests: 51, Assertions: 499, Failures: 2` (exit 1) — awaiting F.**

## walkthrough fix round 3: coupon-count column — 2026-10-17

**Context:** third live-found defect (operator-sanctioned fix loop; journal "AC 9 walkthrough").
The sponsors and campaigns views' "Coupons" count column rendered EMPTY: both views reference the
field as (table `node_field_data`, field `badcamp_sponsors_coupon_count`), and a config-referenced
views handler is resolved through **views data**, not the plugin discovery —
`ViewsHandlerManager::getHandler()` (`views/src/Plugin/ViewsHandlerManager.php:92`) reads
`$data[$field]['field']` from `hook_views_data` and, finding no entry, falls through to
`createInstance('broken', ...)` (`:140`). The module ships no views data. A Broken FIELD handler
still participates in the render (its `query()`/`ensureMyTable()` are no-ops) but its `render()` is
the inherited `FieldPluginBase::render()` (`FieldPluginBase.php:1181`): `getValue()` on a handler
with no real field → NULL → `''`. So the table keeps the column, the header, and an empty cell —
which is exactly what the live page showed. The plugin itself and its counter service were never
broken (delete-blocking uses the service; DeleteBlockingTest green throughout); the suite missed it
because no test rendered either view.

### Tests authored — `web/modules/custom/badcamp_sponsors/tests/src/Kernel/CouponCountColumnTest.php`

All Kernel (the failing layers are handler resolution + a Drupal page render; nothing is Unit-tier).
Shared setUp mirrors the module's trash-aware bootstrap (`DeleteBlockingTest`'s
`$modules = ['system','user','trash']`, trash.settings `enabled_entity_types: [node: []]` BEFORE
the module install so the `deleted` base field gets its column), then seeds TWO sponsors (one
referenced, one unreferenced), ONE campaign, and TWO coupons referencing the SAME sponsor AND
campaign — one live, one soft-deleted via `$coupon->delete()` (trash's default active context) —
so the expected count is **2** (trash-aware, the delete-block number), not the live-only 1:

| Test | Pins | Tier |
| --- | --- | --- |
| `testSponsorsRowShowsCouponCountIncludingTrashed` | Page-controller render (`Page::buildBasicRenderable` → `#type view` element → renderer, as the shipped `sponsorship_manager` role) of `/sponsors` contains a `views-field-coupon-count` data cell with exactly `2` | Kernel — **RED** |
| `testCampaignsRowShowsCouponCountIncludingTrashed` | Same render of `/sponsors/campaigns`: count cell `2` (the campaigns display's `counter: campaign` option → `countByCampaign`) | Kernel — **RED** |
| `testCouponCountHandlerResolvesToCouponCountPlugin` | The resolution seam: after `initHandlers()`, `$view->field['coupon_count']` on BOTH views is the module's `CouponCount` plugin and NOT `Drupal\views\Plugin\views\field\Broken` | Kernel — **RED** |
| `testSponsorWithZeroCouponsRendersZeroCount` | The unreferenced sponsor's count cell renders `0` (config `empty_zero: false`), not an empty cell | Kernel — **RED** |

Harness guards against a vacuous/invalid RED: `assertCountColumnPresent()` first asserts the
`views-field-coupon-count` markup exists — the cell class derives from the configured field id
through `Html::cleanCssIdentifier` (`ViewsThemeHooks.php:676`) + `views-view-table.html.twig`, NOT
from the handler, so the column markup renders even while the handler is Broken. It passed in the
RED run while the content was missing, proving the failures are the Broken handler, not a harness
miss. `couponCountCells()` extracts cell texts with a class-anchored regex + `strip_tags`/trim, so
theme-wrapper drift cannot false-fail the assertions.

Proportionality: the CSV-export displays reference the same handler through the same
`ViewsHandlerManager` seam the plugin-resolution test pins, so no separate export render is
duplicated; the two renders cover both `counter` modes (`sponsor`, `campaign`).

### RED evidence

Single file `bash scripts/run-tests.sh web/.../CouponCountColumnTest.php` → `Tests: 4, Assertions:
36, Failures: 4`:

```
1) ...CouponCountColumnTest::testSponsorsRowShowsCouponCountIncludingTrashed
The sponsors view counts the live and the trashed coupon for the referencing sponsor.
Failed asserting that an array contains '2'.                    (CouponCountColumnTest.php:224)

2) ...CouponCountColumnTest::testCampaignsRowShowsCouponCountIncludingTrashed
The campaigns view counts the live and the trashed coupon for the referencing campaign.
Failed asserting that an array contains '2'.                    (CouponCountColumnTest.php:237)

3) ...CouponCountColumnTest::testCouponCountHandlerResolvesToCouponCountPlugin
The sponsors view resolves the coupon_count handler instead of falling back to Broken.
Failed asserting that an instance of class Drupal\views\Plugin\views\field\Broken is not an
instance of class Drupal\views\Plugin\views\field\Broken.       (CouponCountColumnTest.php:259)

4) ...CouponCountColumnTest::testSponsorWithZeroCouponsRendersZeroCount
A sponsor with no coupons shows a 0 count, not an empty cell.
Failed asserting that an array contains '0'.                    (CouponCountColumnTest.php:278)
```

Failure 3 IS the root cause restated as an assertion (the handler is Broken); failures 1/2/4 fail on
the feature's own content assertions after the column-presence guard passed. Full suite
`bash scripts/run-tests.sh` → `Tests: 55, Assertions: 553, Failures: 4` — the 4 are exactly the new
file; all 51 pre-existing tests green (incl. DeleteBlockingTest: the counter service was never the
defect). Read-only cross-check on the running site:
`ddev drush php-eval '... Views::getView("sponsors") ... initHandlers() ... get_class(...)'` →
`Drupal\views\Plugin\views\field\Broken` — the test environment reproduces the live defect exactly.
phpcs (`Drupal,DrupalPractice` via ddev) on the new file: 0 errors, 0 warnings.

### Pinned contract for F

- **File:** `web/modules/custom/badcamp_sponsors/badcamp_sponsors.views.inc` — auto-loaded when
  views collects data because `views_hook_info()` puts `views_data` in the 'views' group
  (`views.module:162-170`, so the module handler loads `$module.views.inc`). No `.info.yml` or
  `.module` registration is needed; do NOT move this into `hook_views_data` in the `.module`.
- **Function:** `badcamp_sponsors_views_data()` adding
  `$data['node_field_data']['badcamp_sponsors_coupon_count']` with:
  - `title` `'Coupons'` (+ a `help` string);
  - `'field' => ['id' => 'badcamp_sponsors_coupon_count']` — the id under which the
    attribute-discovered plugin (`#[ViewsField("badcamp_sponsors_coupon_count")]`) is created.
- **Nothing else:** no `sort`/`filter`/`argument` entries — the plugin computes per row (`query()`
  is a no-op) and is not click-sortable; no other tables, no `hook_views_data_alter`.
- **Nothing else may change:** all 51 pre-existing tests stay green; the views config, the plugin
  and the counter service stay byte-identical.

**RED state: suite `Tests: 55, Assertions: 553, Failures: 4` (exit 1) — awaiting F.**
