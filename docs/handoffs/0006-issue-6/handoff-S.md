# Handoff-S: Phase 9 — Spec audit (0006-issue-6)

**Date:** 2026-10-20
**Branch:** `issue-0006-issue-6` (worktree `.claude/worktrees/0006-issue-6`)
**Issue:** #6 — Refuse restoring a trashed coupon that would duplicate a live coupon's code
**Diff audited:** base `0a1e109` (= origin/main) → head `a812890`
**Handoff-A reviewed:** `docs/handoffs/0006-issue-6/handoff-A.md`
**Handoff-A-dup reviewed:** `docs/handoffs/0006-issue-6/handoff-A-dup.md`
**Handoff-F reviewed:** `docs/handoffs/0006-issue-6/handoff-F.md`
**Handoff-T-red reviewed:** `docs/handoffs/0006-issue-6/handoff-T-red.md`
**Handoff-T-green reviewed:** `docs/handoffs/0006-issue-6/handoff-T-green.md`

## Preconditions

- **A precondition:** A returned `PASS` (up-front plan, `handoff-A.md`) and A-dup returned `PASS`
  (anti-duplication gate, `handoff-A-dup.md`). No BLOCK. ✅
- **T precondition:** `handoff-T-green.md` reports zero blocking issues; Tier 1 + Tier 2 clean. ✅
- **Browser / visual-diff preconditions:** N/A — `uiSurface: false`; this is a node-access
  behaviour with no rendered surface. ✅ (skipped by declaration, not silently)
- **Diff dual-review gate:** `dual-review-diff.md` = PASS (second-opinion rigor). ✅

## Independent verification run by S

I did not rely on T/F's reported numbers; I re-ran the suite and the invariants myself from the
worktree:

| Check | Command | Result |
|---|---|---|
| Kernel suite | `./scripts/run-tests.sh --testsuite kernel` | **OK (56 tests, 671 assertions)** |
| Unit suite | `./scripts/run-tests.sh --testsuite unit` | **OK (10 tests, 15 assertions)** |
| phpcs | `vendor/bin/phpcs --standard=Drupal,DrupalPractice <5 touched PHP files>` | exit 0, **no findings** |
| Single-query grep | `grep -rn "field_coupon_code\.value" src .module` | **exactly 1 hit** — `src/CouponDuplicateFinder.php:45` |
| No core/contrib edits | `git diff --name-only 0a1e109 a812890 \| grep web/core\|web/modules/contrib` | none |

## Acceptance criteria — AC by AC

| AC | Criterion | Verdict | Evidence |
|---|---|---|---|
| 1 | `badcamp_sponsors.coupon_duplicate_finder` service (`Drupal\badcamp_sponsors\CouponDuplicateFinder`) with a method returning ascending int node IDs of live coupons matching normalized code + sponsor + campaign, excluding an optional node ID, never trashed, accepting numeric-string IDs | **PASS** | Service registered `badcamp_sponsors.services.yml:7-10` (class `Drupal\badcamp_sponsors\CouponDuplicateFinder`). Class `src/CouponDuplicateFinder.php:17` (final), method `findLiveDuplicates(string $code, int\|string $sponsor_id, int\|string $campaign_id, ?int $exclude_id = NULL): array` at `:40-54`. Normalizes via `CouponCodeNormalizer::normalize()` (`:41`, `trim()` only — no case folding, confirming "normalize-then-exact"). Query conditions `:43-47` (type=coupon, code, `(int)`-cast sponsor/campaign, `accessCheck(FALSE)`); `nid <>` exclude only when `$exclude_id !== NULL` (`:48-50`); no `deleted` condition → live-only via trash's `entity_query_alter`. Returns `array_map('intval', …)` + `sort(..., SORT_NUMERIC)` (`:51-52`). Tests: `testFindLiveDuplicatesReturnsMatchingLiveCouponsAscending`, `testFindLiveDuplicatesExcludesTrashedCoupons`, `testFindLiveDuplicatesHonoursExcludeId`, `testFindLiveDuplicatesAcceptsNumericStringIds` (`CouponDuplicateFinderTest.php:108/134/153/174`) — all green. |
| 2 | `CouponCodeUniqueConstraintValidator` uses that service; exactly one query comparing `field_coupon_code.value` | **PASS** | Validator delegates at `CouponCodeUniqueConstraintValidator.php:35-36` (`\Drupal::service('badcamp_sponsors.coupon_duplicate_finder')->findLiveDuplicates(...)`); the inline query it previously held was deleted (diff confirms full removal + replacement). Grep over `src/` + `.module` returns **exactly one** `.condition('field_coupon_code.value', …)` hit — `CouponDuplicateFinder.php:45`. All other `field_coupon_code` references are get/set/normalize/`atPath`, not queries. |
| 3 | Existing behaviour unchanged: no existing test file edited (except to add cases), whole existing suite passes | **PASS** | `git diff --name-status 0a1e109 a812890 -- tests/` shows only **A** (added) for the two new files — no existing test file modified. Whole existing suite still green: S re-ran kernel (56/671) and unit (10/15) independently; 56 − 9 new = 47 pre-existing Kernel tests + 10 unit all pass. No `web/core`/`web/modules/contrib` edits. |
| 4 | `$coupon->access('restore', $account)` forbidden for a trashed coupon whose triple matches a live coupon, for a restore-permitted account; reason non-empty and contains the code | **PASS** | `badcamp_sponsors.module:68-87` (new `coupon` + `restore` branch). For a permitted account with a non-empty duplicate set it returns `AccessResult::forbidden()->setReason(sprintf('Cannot restore coupon: the code "%s" is already used …', $code))->addCacheTags(['node_list:coupon'])->cachePerPermissions()` (`:81-84`). Reason is non-empty and embeds the normalized code (`:82`). Test `testRestoreRefusedWhenLiveDuplicateExists` (`CouponRestoreAccessTest.php:125-156`) asserts `isForbidden()`, non-empty reason, `assertStringContainsString('SAVE20', …)`, plus a **positive control** (same account restores a non-duplicate). Green. |
| 5 | A trashed coupon whose code/sponsor/campaign differs from every live coupon is not refused (allowed for restore-permitted account). Cover: different code, different sponsor, different campaign | **PASS** | Restore branch returns `neutral` (never `forbidden`) when the finder returns `[]` (`:86`), so trash's own `allowed` stands. Test `testRestoreAllowedWhenTripleDiffersOrFieldMissing` (`CouponRestoreAccessTest.php:165-189`) asserts `isAllowed()` for three cases — different code, different sponsor, different campaign — plus a fourth missing-code boundary (hook returns `neutral` at `:75-76`). Green. |
| 6 | Two trashed same-triple coupons both restorable with no live match; after the first restored, the second refused; never its own duplicate | **PASS** | Live-only finder means a trashed coupon is never its own duplicate (excluded by trash's `notExists('deleted')`); both restorable with no live match (neutral → trash allows). After `Trash::restoreEntity($first)` the first becomes the live coupon, so the second's finder call returns a non-empty set → `forbidden`. Test `testSecondTrashedCouponRefusedAfterFirstRestored` (`CouponRestoreAccessTest.php:196-230`), which also resets the access-handler cache before the second check (`:222-224`). Green. |
| 7 | Forbidden result carries `node_list:coupon` tag and permissions cache context | **PASS** | `badcamp_sponsors.module:81-84` adds `->addCacheTags(['node_list:coupon'])->cachePerPermissions()` (`cachePerPermissions()` → `user.permissions` context). The `neutral` returns also carry the same tag + context (`:70, :76, :86`), so a cached "restore allowed" is invalidated when a live duplicate is later created. Test `testForbiddenResultCarriesCacheability` (`CouponRestoreAccessTest.php:235-260`) asserts `getCacheTags()` contains `node_list:coupon` and `getCacheContexts()` contains `user.permissions` — asserted on the returned `AccessResult`, not inferred. Green. |
| 8 | Creating a live coupon with the same triple as a trashed one still allowed; `view`/`update`/`delete` on coupons and all ops on sponsors/campaigns unaffected | **PASS** | Re-issue stays allowed because the validator delegates to the live-only finder (no `deleted` condition). Restore branch is scoped to exactly `coupon` + `restore` (`:68`); `view` (`:47-52`) and delete (`:54-66`) branches untouched (diff shows only the docblock paragraph + the new branch). Test `testUnrelatedOperationsUnaffected` (`CouponRestoreAccessTest.php:265-329`) asserts: (a) re-issue raises no `field_coupon_code` violation and saves (`:287-292`); (b) coupon view/update/delete stay allowed (`:302-313`); (c) sponsor/campaign delete stays forbidden-by-counter (`:321-328`). Green. |
| 9 | Test-first: new Kernel tests covering criteria 1 & 4–8 fail before and pass after; phpcs clean on every touched file | **PASS** | Two new Kernel files in the correct dir. RED recorded in `handoff-T-red.md` (56 tests, 4 errors + 3 failures, all the new feature tests failing for the right reason — `ServiceNotFoundException` for the finder; "is forbidden" false for AC 4/6/7). S re-ran GREEN: kernel 56/671 + unit 10/15. phpcs (Drupal + DrupalPractice) exit 0 on all 5 touched PHP files (S re-ran). **Nuance:** AC 9's "covering criteria 1 and 4 to 8 fail before" is not literally true for AC 5 and AC 8 — those are *unchanged-behaviour* criteria, so their tests are regression **guards** that pass before and after (they pin that the change does not over-forbid or break unrelated ops). This is the correct test-first pattern and was already resolved in the brief dual-review (B-1 "unchanged untestable" → concrete AC 8 assertions). The *feature* tests (AC 1, 4, 6, 7) are the ones that RED→GREEN. |

## Decisions-already-made / Out-of-scope compliance

| Constraint (do not reopen) | Respected? | Evidence |
|---|---|---|
| Re-issuing after soft delete stays allowed | ✅ | Live-only finder; `testUnrelatedOperationsUnaffected` (a) asserts no code violation on re-issue |
| Restore-side only; refusal is a node access result on `restore`, no trash-module change | ✅ | New `coupon` + `restore` branch in `badcamp_sponsors_node_access()` only; no trash-module file touched |
| "Same code" = normalize (trim) then exact, no case folding | ✅ | `CouponCodeNormalizer::normalize()` = `trim()` only; grep found no `strtolower`/`strtoupper` anywhere |
| Hook never grants access, only forbids | ✅ | Restore branch returns only `forbidden` or `neutral`; never `allowed` |
| No change to the delete block | ✅ | Diff shows delete branch (`:54-66`) byte-identical; only docblock + new restore branch added |
| No UI beyond trash's own refusal | ✅ | No form/render/UI code added |
| No composer/dependency changes | ✅ | `git diff --name-only … \| grep composer` → none; only `.module`, `.services.yml`, new class, validator, 2 test files, `.gitignore` |
| Restores that bypass entity access (Drush bulk, storage API) out of scope | ✅ | No such path added; documented as a limitation in brief Non-goals |

## Scope check

F delivered exactly the phase scope: 4 production files (finder class, services registration, validator
delegation, module restore branch) + 2 new Kernel test files. No over-build, no under-delivery, no
unrelated refactor. The one out-of-scope file — `.gitignore` 3 lines ignoring dual-review scratch
sidecars — is benign pipeline hygiene (already flagged by A-dup warn #1, non-blocking).

## Quality audit summary

| Area | Result | Notes |
|---|---|---|
| Spec compliance (9 ACs) | PASS | see table |
| Data integrity | PASS | finder live-only (no `deleted` condition), ascending int IDs, exclude-aware, `accessCheck(FALSE)` |
| Cacheability | PASS | forbidden + neutral both carry `node_list:coupon` + `user.permissions`; asserted, not inferred |
| Naming / terminology | PASS | `findLiveDuplicates`, `coupon_duplicate_finder`, "normalized code" match the issue's vocabulary |
| Test quality | PASS | Each new test names one behaviour, asserts behaviour (results/access outcomes/cache metadata) not implementation; correct tier (Kernel — live-only only means anything against the real DB where trash's `entity_query_alter` is active); proportionate (9 tests for a feature story); two guard tests are boundary pins, not duplication. No assertion-free/tautological/mock-shaped/snapshot-everything smell found. |
| API / error handling | PASS | missing code/sponsor/campaign → `neutral` (trash's own result stands); validator keeps empty/missing-field guard before delegating |
| Security | PASS | hook only forbids, never grants; `bypass node access` short-circuit unchanged (documented platform behaviour); finder `accessCheck(FALSE)` is correct (grant-layer must not filter uniqueness) |
| Performance | PASS | single query, no N+1; `range(0,1)` short-circuit dropped in favour of a full-list emptiness check — functionally identical, A nit 4 accepted |

## Findings

No blocking findings. Two non-blocking notes (both already on the record, confirmed here):

1. **Redundant `exclude_id` in the restore branch** (`badcamp_sponsors.module:79`). The entity being
   restored is trashed and already excluded by trash's `notExists('deleted')`, so the exclude is a
   no-op. Harmless belt-and-suspenders, consistent with the validator's update path. (A-dup nit #2.)
2. **AC 5's missing-field coverage is representative** (missing code only), not exhaustive for
   missing-sponsor/missing-campaign. Proportionate: the hook's guard is a single symmetric `||`
   short-circuit to `neutral`, so one case pins the branch. (t-red/t-green hedge, confirmed.)

## Verdict

**PASS** — all 9 acceptance criteria are met and independently re-verified; the issue's
do-not-reopen decisions and out-of-scope list are respected; the single-query invariant,
live-only semantics, cache metadata, and the whole existing suite are all confirmed by S's own
re-runs. Ready for O to commit.
