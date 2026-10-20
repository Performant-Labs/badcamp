# Handoff-T-red: Phase 4 — Author tests (RED) for #6 refuse restoring a trashed coupon that would duplicate a live coupon's code

**Date:** 2026-10-20
**Branch:** `issue-0006-issue-6`
**Brief / wireframe reviewed:** `docs/handoffs/0006-issue-6/brief.md`, `survey.md`, `handoff-A.md` (all read in the worktree)

## A precondition

A returned **PASS** on the plan (Phase 3, `handoff-A.md`), with 0 blocking findings. Both open
points resolved (locator for the validator→finder call; inline reason, no reason-builder class), and
the two `warn` amendments (neutral branch cacheability; `accessCheck(FALSE)`) folded into the brief.
Proceeding to author tests.

## Tests authored (2 new Kernel files, no existing test edited)

Both files mirror `DeleteBlockingTest::setUp()`: `$modules = ['system', 'user', 'trash']`,
`trash.settings` enabled for `node` before `badcamp_sponsors` install, and user 1 created first so
every assertion account is a regular user.

### `CouponDuplicateFinderTest.php` — AC 1 (finder service), all RED

| Test | Pins | RED? |
|------|------|------|
| `testFindLiveDuplicatesReturnsMatchingLiveCouponsAscending` | `findLiveDuplicates()` returns the ascending **integer** node IDs of live coupons with the same *normalized* (trimmed) code/sponsor/campaign, and excludes non-matching triples. | RED |
| `testFindLiveDuplicatesExcludesTrashedCoupons` | A soft-deleted coupon is never returned (live-only). | RED |
| `testFindLiveDuplicatesHonoursExcludeId` | The optional `$exclude_id` is honoured in both directions. | RED |
| `testFindLiveDuplicatesAcceptsNumericStringIds` | `$sponsor_id`/`$campaign_id` may arrive as numeric strings. | RED |

Tier: **Kernel** — the finder is an entity-query service against real node/coupon storage, so it needs
the real database (trash's query alter must be live in the environment for the live-only assertion to
mean anything). Unit-tier would mock the query and prove nothing.

RED reason: the service `badcamp_sponsors.coupon_duplicate_finder` does not exist yet →
`ServiceNotFoundException` on first use.

### `CouponRestoreAccessTest.php` — AC 4–8 (restore access), mixed RED / regression guards

| Test | Pins | RED? |
|------|------|------|
| `testRestoreRefusedWhenLiveDuplicateExists` | A trashed coupon whose triple matches a live coupon → `access('restore', $account, TRUE)` is **forbidden**, reason non-empty and contains the code; **positive control** proves the same account restores a non-duplicate coupon (refusal isolated to the finder, not a missing permission). | RED |
| `testRestoreAllowedWhenTripleDiffersOrFieldMissing` | Four cases stay **allowed** for a restore-permitted account: different code, different sponsor, different campaign, missing code. | guard (passes before) |
| `testSecondTrashedCouponRefusedAfterFirstRestored` | Two trashed same-triple coupons: both restorable with no live match (a coupon is never its own duplicate); after the first is restored the second is **refused**. | RED (second half) |
| `testForbiddenResultCarriesCacheability` | The forbidden result's `getCacheTags()` includes `node_list:coupon` and `getCacheContexts()` includes `user.permissions` (asserted on the returned `AccessResult`, not inferred). | RED |
| `testUnrelatedOperationsUnaffected` | (a) re-issuing a code held by a trashed coupon saves with no `field_coupon_code` violation; (b) coupon `view`/`update`/`delete` stay allowed for a permitted account; (c) sponsor/campaign `delete` stays governed by the counter. | guard (passes before) |

Mechanism notes: trash is exercised through `$node->delete()` (soft-delete in the default active
context) and the trashed coupon is reloaded via
`trash.manager->executeInTrashContext('ignore', fn() => $storage->load($nid))` so the object carries
the `deleted` base field that trash's `entity_access` hook reads. The dynamic `restore node entities`
permission is granted through `drupalCreateUser(['access content', 'restore node entities'])`.
`Trash::restoreEntity()` restores the first coupon in AC 6; the node access handler cache is reset
before the second refusal is re-checked.

## RED confirmation

Command (run from the worktree, so `run-tests.sh` resolves it as the checkout and DDEV targets
`/var/www/html/.claude/worktrees/0006-issue-6`):

```
cd /home/andre-angelantoni/Projects/badcamp/.claude/worktrees/0006-issue-6 \
  && ./scripts/run-tests.sh --testsuite kernel
```

Result: **Tests: 56, Assertions: 660, Errors: 4, Failures: 3** — every error/failure is one of my new
RED tests, and each fails for the right reason. The other 49 tests (existing 47 Kernel + my 2
regression guards) pass.

Key failing output:

- Finder (AC 1) — service missing, not a typo/setup error:
  ```
  Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException:
  You have requested a non-existent service "badcamp_sponsors.coupon_duplicate_finder".
  ```
- AC 4 — currently allowed when it must be forbidden:
  ```
  Restoring a coupon whose code duplicates a live coupon is forbidden.
  Failed asserting that false is true.
  ```
- AC 6 (second half) — currently allowed when it must be forbidden:
  ```
  After the first coupon is restored, restoring the second is refused.
  Failed asserting that false is true.
  ```
- AC 7 — currently allowed (so the forbidden result's cacheability cannot be inspected):
  ```
  The duplicate restore is forbidden, so its cacheability can be inspected.
  Failed asserting that false is true.
  ```

A targeted run of just the two new files reproduces the same 4 errors + 3 failures (Tests: 9,
Assertions: 78, Errors: 4, Failures: 3).

`phpcs --standard=Drupal,DrupalPractice` is clean on both new files (two docblock
short-description issues and one unused variable were found and fixed during authoring).

## Ready for F

Confirmed **RED is valid**. F may implement against these tests. The RED is the feature contract:
- the finder service must exist and answer `findLiveDuplicates()` live-only/ascending/exclude-aware;
- `badcamp_sponsors_node_access()` must return `forbidden` (reason containing the code,
  `node_list:coupon` tag, `user.permissions` context) for a restore-permitted account on a trashed
  coupon whose triple matches a live coupon, and `neutral` otherwise.
