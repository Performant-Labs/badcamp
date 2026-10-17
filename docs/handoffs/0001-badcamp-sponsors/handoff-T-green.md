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
