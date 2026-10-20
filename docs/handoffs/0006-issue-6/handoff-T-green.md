# Handoff-T-green: Phase 6 — Verify GREEN + Tier 2 (0006-issue-6)

**Date:** 2026-10-20
**Branch / worktree:** `issue-0006-issue-6` / `.claude/worktrees/0006-issue-6`
**Issue:** #6
**Handoff-F reviewed:** `docs/handoffs/0006-issue-6/handoff-F.md`
**Handoff-T-red:** `docs/handoffs/0006-issue-6/handoff-T-red.md`

## GREEN confirmation

Re-ran the suite independently from the worktree (DDEV present, `scripts/run-tests.sh`):

```
./scripts/run-tests.sh --testsuite kernel
```
→ `OK (56 tests, 671 assertions)` (Time ~02:49).

```
./scripts/run-tests.sh --testsuite unit
```
→ `OK (10 tests, 15 assertions)`.

Every Phase-4 test now passes: the 4 finder tests (`CouponDuplicateFinderTest`) and the 5
restore-access tests (`CouponRestoreAccessTest`) are all green, alongside the pre-existing
suite. No errors, failures, warnings or risky tests (the run is `failOnRisky` / `failOnWarning` /
`beStrictAboutTestsThatDoNotTestAnything`).

### Spot-check: tests pin behaviour, not implementation

- `testRestoreRefusedWhenLiveDuplicateExists` — asserts `$trashed->access('restore', $account, TRUE)->isForbidden()`
  with a non-empty reason containing `SAVE20`. If the restore branch were removed the hook returns
  `neutral`, trash's `allowed` stands, and `isForbidden()` is `FALSE` → `assertTrue` fails with the
  exact RED message from Phase 4 ("Restoring a coupon whose code duplicates a live coupon is
  forbidden. Failed asserting that false is true."). It also carries a **positive control** (the same
  account restores a non-duplicate coupon), so it would *also* catch an over-broad forbid. ✓
- `testFindLiveDuplicatesReturnsMatchingLiveCouponsAscending` — calls
  `\Drupal::service('badcamp_sponsors.coupon_duplicate_finder')->findLiveDuplicates(...)` and asserts
  the exact ascending integer ID list. If the service or method did not exist it fails with
  `ServiceNotFoundException` (the Phase-4 RED); if it returned the wrong set it fails `assertSame`. ✓

No test passes regardless of the feature. The two guard tests that passed before F
(`testRestoreAllowedWhenTripleDiffersOrFieldMissing`, `testUnrelatedOperationsUnaffected`) are
boundary pins, not vacuous: they fail if the implementation over-forbids (AC 5) or breaks unrelated
operations (AC 8).

## Cross-check: F's verification vs my re-run

| F's claim (handoff-F.md) | My independent re-run | Match |
|---|---|---|
| `--testsuite kernel --filter badcamp_sponsors` → OK (56 tests, 671 assertions) | OK (56 tests, 671 assertions) | ✓ |
| single-query grep → exactly 1 hit (`CouponDuplicateFinder.php:45`) | 1 hit, same file:line | ✓ |
| phpcs `--standard=Drupal,DrupalPractice` exit 0 | exit 0 (see Tier 1) | ✓ |

Also ran what F did **not** report: the **full** kernel suite without the `badcamp_sponsors` filter
(56/671 OK — nothing outside the module regressed) and the **unit** suite (10/15 OK). Both green. No
discrepancy.

## Tier 1 results

| Check | Command | Result | Verdict |
|---|---|---|---|
| Kernel suite | `./scripts/run-tests.sh --testsuite kernel` | OK (56 tests, 671 assertions) | PASS |
| Unit suite | `./scripts/run-tests.sh --testsuite unit` | OK (10 tests, 15 assertions) | PASS |
| phpcs (all 5 touched PHP files) | `ddev exec --dir … vendor/bin/phpcs --standard=Drupal,DrupalPractice <module + finder + validator + 2 test files>` | exit 0, no findings | PASS |

The `HTML output directory web/sites/simpletest/browser_output is not a writable directory` and
`Mutagen is not enabled` lines are pre-existing environment noise (Functional-only output dir, no
Mutagen sync configured); they do not affect Kernel/Unit results.

## Tier 2 results (structural)

- **Coverage (each AC backed by a test or invariant)** — see the AC table below. All 9 ACs covered.
- **Test quality** — each of the 9 new tests names a behaviour in its assertion message, fails in
  isolation for the right reason (Phase-4 RED recorded), sits at the correct tier (**Kernel**: the
  finder's live-only guarantee only means anything against the real DB where trash's
  `entity_query_alter` is active — a unit test with a mocked query would prove nothing), and asserts
  behaviour (results, access outcomes, cache metadata), never the class name or the query shape.
  No redundant/duplicated test; nothing to delete or merge. The two guard tests are proportionate
  boundary pins, not duplication.
- **Single-query invariant (AC 2)** — `grep -rn "field_coupon_code.value" src .module` → exactly one
  hit, `CouponDuplicateFinder.php:45`. Every other `field_coupon_code` reference is a get/set/
  normalize/`atPath` call, not a query. The validator's inline query is gone; it delegates.
- **AC 3** — `git diff --name-only HEAD -- tests/` is empty: no existing test file was edited. Only two
  NEW untracked files. The whole existing suite still passes (56 − 9 = 47 pre-existing Kernel tests
  green; 10 unit tests green).
- **Data integrity (finder)** — verified in source: no explicit `deleted` condition (live-only rides
  trash's `notExists('deleted')`), `array_map('intval', …)` + `sort(..., SORT_NUMERIC)` (ascending
  integer IDs), `nid <>` exclude only when `$exclude_id !== NULL`, `(int)` casts on the stringable
  IDs, `accessCheck(FALSE)`. Each is pinned by a passing test (trashed-excluded, ascending, exclude
  both directions, numeric-string IDs).
- **Error handling / missing-field boundary** — the hook returns `neutral` (tagged + permission-context)
  when `restore node entities` is absent, or code/sponsor/campaign is missing, so trash's own
  `allowed`/`forbidden` stands. The validator keeps its empty/missing-field guard before delegating.
- **API contract / cacheability** — `forbidden` result carries `node_list:coupon` tag +
  `user.permissions` context (asserted, not inferred); neutral carries the same, so a cached
  "restore allowed" is invalidated when a live duplicate is later created.

## Acceptance criteria status

| AC | Criterion | Backing | Result |
|---|---|---|---|
| 1 | Finder returns live duplicates only, ascending int IDs, exclude-aware, numeric-string IDs | `CouponDuplicateFinderTest` (4 tests) | PASS |
| 2 | Validator delegates; exactly one `field_coupon_code.value` query | grep → 1 hit (`CouponDuplicateFinder.php:45`) | PASS |
| 3 | No existing test file edited; existing suite still passes | `git diff --name-only -- tests/` empty; full kernel + unit green | PASS |
| 4 | Restore refused when a live duplicate exists, with positive control | `testRestoreRefusedWhenLiveDuplicateExists` | PASS |
| 5 | Not refused when code/sponsor/campaign differs, or a field is missing | `testRestoreAllowedWhenTripleDiffersOrFieldMissing` | PASS |
| 6 | Two trashed same-triple coupons; second refused after first restored | `testSecondTrashedCouponRefusedAfterFirstRestored` | PASS |
| 7 | Forbidden result carries `node_list:coupon` tag + `user.permissions` context | `testForbiddenResultCarriesCacheability` | PASS |
| 8 | Unrelated operations unaffected (re-issue, view/update/delete, sponsor/campaign delete) | `testUnrelatedOperationsUnaffected` | PASS |
| 9 | Test-first + regression + phpcs | RED recorded (handoff-T-red); GREEN now; phpcs clean on all touched files | PASS |

## Blocking issues

None.

## Advisory notes

1. **Missing-field coverage is representative, not exhaustive (consistent with the t-red hedge).**
   AC 5's "a triple field is missing" is exercised via the *missing code* case only (`''`); the
   missing-sponsor and missing-campaign paths are not directly asserted. This is proportionate: the
   hook's guard is a single symmetric `if ($code === '' || $sponsor_id === NULL || $campaign_id === NULL)`
   short-circuit to `neutral`, so one representative case pins the branch. Recorded in the t-red
   hedge; no action required, noting it here for the S record.
2. **Redundant `exclude_id` in the restore branch.** The hook passes `(int) $entity->id()` as the
   finder's exclude id, but the entity being restored is trashed and already excluded by trash's
   `notExists('deleted')`. Harmless (a no-op exclude of an already-excluded row); A's review noted the
   exclude is for the validator's update path only. Not worth churn.
3. **`web/robots.txt` is modified in the worktree (2 deletions)** — this is the pre-existing scaffold
   drift flagged in the brief's Non-goals; I did not touch it, and it must not be staged with this
   story's files (O's call at commit time).

## Verdict

**GREEN confirmed.** All 9 acceptance criteria are backed by a passing test or a verified invariant;
Tier 1 and Tier 2 are clean. No blocking issues. `uiSurface: false` → **U is N/A, ready for the diff
dual-review gate (A-dup), then S.**
