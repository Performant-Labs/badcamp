# Survey: 0006-issue-6 (issue #6)

Repo: Performant-Labs/badcamp (Drupal CMS 2 / Drupal 11, DDEV). Worktree:
`.claude/worktrees/0006-issue-6`, branch `issue-0006-issue-6`, base 0a1e109 (= origin/main).
Tests: `scripts/run-tests.sh` (PHPUnit in DDEV, against this checkout). Kernel tests only for this
story (Functional tests can't exercise a worktree).

## The story in one paragraph

Coupon codes must be unique per (sponsor, campaign). The check is
`CouponCodeUniqueConstraintValidator`, which deliberately queries only **live** coupons, so an editor
can re-issue a code after soft-deleting the coupon that held it. But **restoring** a coupon from trash
skips validation, so soft-delete X → re-issue X → restore the trashed X leaves two live coupons with
the same (code, sponsor, campaign). The fix is on the restore side: refuse a restore that would create
a live duplicate, as a node access result on the `restore` operation (what trash checks before it
offers or performs a restore, including bulk).

## What exists today (verified in-tree)

| Fact | Where |
|------|-------|
| Normalizer: `CouponCodeNormalizer::normalize()` = `trim()` (surrounding whitespace only, no case folding) | `src/CouponCodeNormalizer.php:26-28` |
| The uniqueness check: `CouponCodeUniqueConstraintValidator::validate()` runs an inline entity query on `type=coupon`, `field_coupon_code.value`, `field_coupon_sponsor.target_id`, `field_coupon_campaign.target_id`, excluding self via `nid <>` when not new | `src/Plugin/Validation/Constraint/CouponCodeUniqueConstraintValidator.php:35-49` |
| Constraint + message | `src/Plugin/Validation/Constraint/CouponCodeUniqueConstraint.php`; attached in `badcamp_sponsors.module:110` |
| Node access hook: `view` (coupon privacy) and `delete` (sponsor/campaign refuse when referenced) | `badcamp_sponsors.module:39-64` |
| Trash-aware counter (live + trashed split on `deleted` base field) | `src/CouponCounter.php`; `badcamp_sponsors.services.yml` (`badcamp_sponsors.coupon_counter`) |
| Delete-refusal reason builder (single construction site) + 403 subscriber | `src/DeleteRefusalReason.php`, `src/EventSubscriber/DeleteRefusalSubscriber.php` |
| Existing Kernel tests (fixtures, trash setup) | `tests/src/Kernel/DeleteBlockingTest.php`, `CouponAccessTest.php`, `CouponValidationTest.php`, others |

The `.module` file references `field_coupon_code` only in `entity_presave` (trim + title), not in any
query. The only query comparing `field_coupon_code.value` today is the validator's (line 38) — grep
over `src/` and `.module` confirms a single hit.

## How trash gates `restore` (citations from this checkout's `web/modules/contrib/trash`, 3.1.1)

- Dynamic restore permission is `restore {entity_type_id} entities` = **`restore node entities`**:
  `src/TrashPermissions.php` (`entityTypePermissions()`).
- `TrashEntityAccess::entityAccess()` (`src/Hook/TrashEntityAccess.php`, `#[Hook('entity_access')]`)
  handles `restore` for a **deleted** entity: after confirming trash support is enabled for the type,
  it checks `restore node entities`; with the permission it returns
  `AccessResult::allowed()->addCacheableDependency($entity)` (carrying `user.permissions` cache context
  and `trash.settings`), and without it returns `forbidden` ("The 'restore node entities' permission is
  required.").
- Because entity access results are OR-ed, this module's `forbidden` on `restore` (for a permitted
  account) is the operative refusal, and a `neutral` leaves trash's own `allowed`/`forbidden` in charge.

## Why "no explicit `deleted` condition" = live-only (verified in trash 3.1.1)

`TrashEntityHooks::entityQueryAlter()` (`src/Hook/TrashEntityHooks.php:74-118`,
`#[Hook('entity_query_alter')]`) is the mechanism: for a trash-enabled entity type it (a) **skips**
queries that already carry an explicit `deleted` condition (`hasDeletedCondition()`, lines 86-89,
123-140), and (b) in the default context adds `$query->notExists('deleted')` (lines 114-118), so a
query with no `deleted` condition returns only entities whose `deleted` base field is NULL — i.e.
live entities. `CouponCounter::countCoupons(..., FALSE)` already relies on this ("trash … filters
queries without an explicit 'deleted' condition itself"). The finder's query (no `deleted` condition)
therefore returns live coupons only, and a query that *wants* trashed rows must add `exists('deleted')`
explicitly (as `CouponCounter::countCoupons(..., TRUE)` does).

## The new seam

- **Finder service** `badcamp_sponsors.coupon_duplicate_finder` (`Drupal\badcamp_sponsors\CouponDuplicateFinder`):
  a method that, given a code, a sponsor ID, a campaign ID and an optional node ID to exclude, returns
  the node IDs (integers, ascending) of **live** coupons with the same normalized code, sponsor and
  campaign. IDs may arrive as numeric strings (cast to int). Trashed coupons are never returned: the
  query carries no explicit `deleted` condition, so trash's query alter filters soft-deleted rows (the
  exact live-only pattern `CouponCounter::countCoupons(..., FALSE)` uses).
- **Validator delegation** (AC 2): the validator calls the finder; the module keeps exactly one query
  that compares `field_coupon_code.value` (now in the finder).
- **Node access restore branch**: `badcamp_sponsors_node_access()` gains a `coupon` + `restore` branch.
  For an account holding `restore node entities`, when the finder returns a non-empty duplicate set it
  returns `AccessResult::forbidden()->setReason(<reason containing the code>)->addCacheTags(['node_list:coupon'])->cachePerPermissions()`.
  Otherwise `neutral`. The hook only forbids, never grants (same as the delete branch).

## Reuse & Analogous-Feature map

| Need | Closest existing object | Extend vs new |
|------|------------------------|---------------|
| Live-coupon "same triple" query | `CouponCodeUniqueConstraintValidator::validate()` inline query | **Extract** into the new finder — one query site (AC 2) |
| Live-only (trash-aware) semantics | `CouponCounter::countCoupons(..., FALSE)` (no explicit `deleted` condition) | **Reuse the pattern**, not the class |
| Code normalization | `CouponCodeNormalizer::normalize()` | **Reuse verbatim** |
| Node access forbid + reason + cache tag | `badcamp_sponsors_node_access()` delete branch (`module:49-60`) | **Extend** the same hook (new branch) |
| Reason single-construction-site pattern | `DeleteRefusalReason` | Optional; AC only requires non-empty + code, A weighs |
| Trash restore gate (already in place) | `TrashEntityAccess::entityAccess()` | **Reuse** — we only add the forbid; no trash change |
| Kernel fixtures + trash setup | `DeleteBlockingTest::setUp()` (`trash.settings` enabled_entity_types + `trash.manager`) | **Mirror** in new test files |

## Constraints (binding)

- Decisions already made in the issue (do not reopen): creating/saving a live coupon whose triple
  matches a trashed coupon stays allowed; the fix is restore-side; "same code" = after
  `CouponCodeNormalizer::normalize()` (trim, exact, no case folding); the hook only forbids; restores
  that bypass entity access are out of scope.
- No change to the delete block wording/behaviour, no case-insensitive comparison, no cleanup of
  existing duplicates, no UI beyond what trash already renders for a refused restore.
- Trash is a soft dependency: resolve its service/config defensively (the `deleted` base field and the
  query-alter behaviour only exist when trash is installed and enabled for node).
- No composer/dependency changes, no config schema changes, no new permissions (restore permission is
  trash's own).
- Drupal coding standard: `vendor/bin/phpcs --standard=Drupal,DrupalPractice` via `ddev exec`, clean on
  every touched file (AC 9).
