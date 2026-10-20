# Brief review — operator response (round 2)

Response to the round-1 BLOCK verdict on `brief.md`. Each finding is answered; the brief has been
amended accordingly.

## BLOCK findings

**B-1 (AC 8 "unchanged" untestable)** — Resolved. AC 8 in the brief now names the concrete
assertions: (a) creating a live coupon whose triple matches a trashed coupon saves with no
`field_coupon_code` violation; (b) a coupon's `view`/`update`/`delete` for a permitted account are
still allowed (the restore branch is scoped to exactly `coupon` + `restore`, so these keep the hook's
existing behaviour, and `view` still requires `view coupon content` — existing `CouponAccessTest`
stays green); (c) sponsor/campaign `delete` is still governed by the counter (`DeleteBlockingTest`
stays green). "Unchanged" is asserted per operation, not in the abstract.

**B-2 (restore-refused test needs a positive control)** — Resolved. AC 2 in the brief now states the
same `restore node entities` account is proven able to restore a *non*-duplicate coupon (AC 3's
allowed cases), so the forbidden result on the duplicate is isolated to the finder rather than to a
missing permission. The allowed cases in AC 3 serve as the positive control.

## Needs-verification findings

**NV-1 / NV-2 (live-only mechanism not shown)** — Resolved in `survey.md`: a new section ("Why 'no
explicit `deleted` condition' = live-only") cites trash 3.1.1 `TrashEntityHooks::entityQueryAlter()`
(`src/Hook/TrashEntityHooks.php:74-118`) — it skips queries with an explicit `deleted` condition and,
in the default context, adds `$query->notExists('deleted')`, so a query without that condition returns
live entities only. `CouponCounter::countCoupons(..., FALSE)` already relies on this; the finder
follows the same pattern.

**NV-3 (finder implementation not shown)** — Deferred by design: this is the brief gate, before any
diff exists. The finder's contract (normalize, single query, cast to int, sort ascending, exclude id,
live-only) is specified; the diff gate re-verifies the implementation.

**NV-4 (locator style open point)** — Acknowledged as open and deliberately so; the decision (locator
vs. tagged service) is to be recorded in `handoff-A.md` by the architecture reviewer, and the diff
gate re-checks for circularity.

**NV-5 (cacheability test must assert metadata)** — Resolved: AC 5 in the brief now says the test
asserts `getCacheTags()` contains `node_list:coupon` and `getCacheContexts()` contains
`user.permissions` on the `AccessResult` returned by `$coupon->access('restore', $account, true)`.

## WARN findings

**W-1 (non-access-checked restore paths remain unguarded)** — Acknowledged as an issue-level decision,
not a brief omission: the issue's own "Out of scope" note excludes Drush bulk restore and the storage
restore API. The brief's Non-goals now require the PR body to document that limitation explicitly.

**W-2 (missing-field boundary untested)** — Resolved: AC 3 in the brief now includes a fourth case —
a trashed coupon missing one of code/sponsor/campaign → `neutral` → allowed for a restore-permitted
account.

## NIT findings

**NIT-1 (class file path)** — Resolved: the brief's Direction now states the file
`web/modules/custom/badcamp_sponsors/src/CouponDuplicateFinder.php`.

**NIT-2 (RED recording)** — Resolved: AC 7 in the brief now says RED is recorded in `handoff-T-red.md`
with the failing run and confirmed by the plugin-owned RED verdict at the t-red → implement advance.
