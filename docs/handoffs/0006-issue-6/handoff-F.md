# Handoff-F: Phase 5 — Implement

**Date:** 2026-10-20
**Branch / worktree:** `issue-0006-issue-6` / `.claude/worktrees/0006-issue-6`
**Contract:** `handoff-T-red.md` (tests + the behaviours they pin)

## What I changed

Four files, the minimal production change that satisfies the RED contract. No test
file touched, no `web/core`/`web/modules/contrib` edits, no over-build.

### 1. `src/CouponDuplicateFinder.php` (new)

`Drupal\badcamp_sponsors\CouponDuplicateFinder`, `final class`, constructor-injected
`EntityTypeManagerInterface` (readonly promotion). One method
`findLiveDuplicates(string $code, int|string $sponsor_id, int|string $campaign_id, ?int $exclude_id = NULL): array`
that:
- normalizes the code via `CouponCodeNormalizer::normalize()`;
- runs **the one** entity query (`type=coupon`, `field_coupon_code.value` = normalized,
  `field_coupon_sponsor.target_id` = `(int)$sponsor_id`,
  `field_coupon_campaign.target_id` = `(int)$campaign_id`, `accessCheck(FALSE)`, `nid <>`
  exclude only when `$exclude_id !== NULL`);
- **no explicit `deleted` condition** — trash's `entity_query_alter` adds
  `notExists('deleted')` in the default context, so the result is live-only (the same
  pattern `CouponCounter::countCoupons(..., FALSE)` already relies on);
- returns `array_map('intval', …)` + `sort(..., SORT_NUMERIC)` — ascending integer node IDs.

This moves the single `field_coupon_code.value` query out of the validator, so the
"exactly one query" invariant holds (verified below).

### 2. `badcamp_sponsors.services.yml`

Registered `badcamp_sponsors.coupon_duplicate_finder` →
`Drupal\badcamp_sponsors\CouponDuplicateFinder`, argument `'@entity_type.manager'`.

### 3. `src/Plugin/Validation/Constraint/CouponCodeUniqueConstraintValidator.php`

Kept the empty/missing-field guard; replaced the inline query with a delegation to
`\Drupal::service('badcamp_sponsors.coupon_duplicate_finder')->findLiveDuplicates(...)`
with `!$value->isNew() ? (int) $value->id() : NULL` as the exclude id. Raises the
violation when the result `!== []`. Behaviour-preserving (same live-only semantics, same
self-exclusion on update; the `range(0,1)` short-circuit is replaced by an emptiness
check on the full list — functionally identical, per A nit 4).

### 4. `badcamp_sponsors.module`

Added the `coupon` + `restore` branch to `badcamp_sponsors_node_access()` as a new `if`
before the final `return AccessResult::neutral()`; the existing `view` and `delete`
branches are untouched. The branch:
- returns `neutral` (tagged `node_list:coupon` + `cachePerPermissions()`) when the account
  lacks `restore node entities`, or when code/sponsor/campaign is missing;
- otherwise asks the finder for live duplicates (excluding `(int)$entity->id()`) and, when
  non-empty, returns `forbidden()` with a non-empty reason containing the normalized code
  (`sprintf('Cannot restore coupon: the code "%s" is already used for this sponsor and campaign.', $code)`),
  tagged `node_list:coupon` + `cachePerPermissions()`.
- Updated the hook's docblock to document the new third rule (only-forbids, never-grants).

## Self-check

Ran locally (informational only; the plugin owns the verdict):

```
cd .claude/worktrees/0006-issue-6 && ./scripts/run-tests.sh --testsuite kernel --filter badcamp_sponsors
```

Result: **OK (56 tests, 671 assertions)** — all green, including the 7 previously-RED tests.

## Tests I think are wrong

None.

## Verification commands and results

1. **Suite** — `./scripts/run-tests.sh --testsuite kernel --filter badcamp_sponsors`:
   `OK (56 tests, 671 assertions)` (Time: 02:56.877).
2. **Single-query invariant** — `grep -rn "field_coupon_code.value" web/modules/custom/badcamp_sponsors/src web/modules/custom/badcamp_sponsors/badcamp_sponsors.module`:
   exactly one hit — `src/CouponDuplicateFinder.php:45` (`->condition('field_coupon_code.value', $normalized)`).
3. **phpcs** — `ddev exec --dir /var/www/html/.claude/worktrees/0006-issue-6 'vendor/bin/phpcs --standard=Drupal,DrupalPractice <3 files>'`:
   exit code 0, no findings.

## Ready for T(green)

Implemented against the RED contract. The plugin should now run the suite: GREEN means the
loop advances; RED means I rework.
