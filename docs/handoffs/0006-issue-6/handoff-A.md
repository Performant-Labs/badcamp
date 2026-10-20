# Handoff-A: Phase 3 — Up-front plan review (0006-issue-6)

**Date:** 2026-10-20
**Branch:** `issue-0006-issue-6`
**Brief reviewed:** `docs/handoffs/0006-issue-6/brief.md`
**Reuse map:** `docs/handoffs/0006-issue-6/survey.md`
**Wireframe:** N/A (uiSurface false)
**Verdict:** PASS

## Summary

The plan is sound and the Reuse map is honest. The finder is the correct single
home for the one `field_coupon_code.value` query (AC 2 is verifiable by grep), and the
restore branch correctly extends the existing `badcamp_sponsors_node_access()` hook rather
than opening a parallel path. I verified against the trash 3.1.1 source that a query with no
explicit `deleted` condition is filtered to live entities by `TrashEntityHooks::entityQueryAlter()`
under the default `active` context, so the "live-only via no explicit `deleted`" design is
correct. I also verified the OR-combine claim in core: `AccessResult::orIf()` gives a `forbidden`
result precedence over an `allowed` one, so this module's forbid wins over trash's allow, while a
`neutral` leaves trash's own result in charge. Two open points are decided below (locator for the
validator→finder call; inline reason, no new reason-builder class). There are two `warn`
amendments and three `nit` amendments that O should fold into the brief before T-red; none
invalidates the architecture.

## Verified: the mechanism is sound

1. **Extend-vs-new — the finder is the right extraction point, and AC 2 holds.**
   The only query comparing `field_coupon_code.value` in the module today is
   `CouponCodeUniqueConstraintValidator.php:38` (grep over `src/` + `.module` shows a single
   `.condition('field_coupon_code.value', …)` hit; all other `field_coupon_code` references are
   field get/set, e.g. `badcamp_sponsors.module:80-81`). Extracting that query into
   `CouponDuplicateFinder` moves the single hit, preserving the "exactly one query" invariant.
   `CouponCounter.php:100` is a *different* query (reference-field, not code), so no collision.

2. **Restore branch extends the hook, not a parallel path.** The new `coupon` + `restore` branch
   slots into `badcamp_sponsors_node_access()` (`badcamp_sponsors.module:39-64`) as a third `if`
   before the final `return AccessResult::neutral()` (line 63). It reuses the exact forbid +
   reason + `node_list:coupon` shape the delete branch already uses (module:55-60), and mirrors
   its "only forbids, never grants" contract.

3. **Live-only via "no explicit `deleted` condition" is correct (verified in trash 3.1.1).**
   `TrashEntityHooks::entityQueryAlter()` (`web/modules/contrib/trash/src/Hook/TrashEntityHooks.php:77-119`):
   it skips the query when trash is disabled or the type is not trash-enabled (line 78), skips
   queries that already carry a `deleted` condition (`hasDeletedCondition()`, line 89, matching
   `deleted` / `deleted.*` at lines 129-142), and otherwise adds `notExists('deleted')` in the
   default context (line 118) — `exists('deleted')` only when context is `inactive` (line 115).
   `TrashManager::$trashContext` defaults to `'active'` (`TrashManager.php:31`), and
   `shouldAlterQueries()` is TRUE except in `ignore` (`TrashManager.php:118-121`). So a finder
   query with no `deleted` condition returns live coupons only. This is the exact pattern
   `CouponCounter::countCoupons(..., FALSE)` already relies on (`CouponCounter.php:99-110`, no
   `deleted` condition on the live half, documented at lines 20-24, 90-97).

4. **Node access OR-combine: our `forbidden` overrides trash's `allowed`; `neutral` leaves trash
   intact (core citations).**
   - `EntityAccessControlHandler::access()` collects `hook_entity_access` (trash) and
     `hook_ENTITY_TYPE_access` (`badcamp_sponsors_node_access`) results and ORs them:
     `web/core/lib/Drupal/Core/Entity/EntityAccessControlHandler.php:99-104`, via
     `processAccessHookResults()` (lines 131-143) using `AccessResult::orIf()`.
   - `AccessResult::orIf()` gives forbidden precedence: `if ($this->isForbidden() || $other->isForbidden()) { $result = static::forbidden(); … }`
     (`web/core/lib/Drupal/Core/Access/AccessResult.php:308-320`). So `forbidden` OR `allowed`
     ⇒ `forbidden`; `neutral` OR `allowed` ⇒ `allowed`; `neutral` OR `forbidden` ⇒ `forbidden`.
   - Trash's restore result: for a deleted entity with `restore node entities` it returns
     `allowed()` (`TrashEntityAccess.php:126-146`, permission string built at
     `TrashPermissions.php:44`); without the permission it returns `forbidden`
     (`TrashEntityAccess.php:128-132`). So our forbid wins for a permitted account, and our
     neutral (no permission, or no duplicate) leaves trash's allowed/forbidden in charge.
   - For the `restore` operation the node-grant layer contributes nothing: `NodeGrantDatabaseStorage::access()`
     returns `AccessResult::neutral()` for any operation not in `view/update/delete`
     (`web/core/modules/node/src/NodeGrantDatabaseStorage.php:57-60`). And the handler skips
     `checkAccess` entirely once the hook result is already forbidden
     (`EntityAccessControlHandler.php:108-110`).
   - `bypass node access` still short-circuits to `allowed()` before any hook runs
     (`web/core/modules/node/src/NodeAccessControlHandler.php:95-98`) — the same documented
     platform behaviour the delete branch inherits (module docblock, lines 26-31).

5. **Cache metadata is correct.**
   - `node_list:coupon` is the per-bundle list tag: `EntityType::getBundleListCacheTags()`
     returns `[$id . '_list:' . $bundle]` (`web/core/lib/Drupal/Core/Entity/EntityType.php:912-913`),
     merged into `getListCacheTagsToInvalidate()` (`web/core/lib/Drupal/Core/Entity/EntityBase.php:484-493`)
     and invalidated on save and delete (`EntityBase.php:558-572` and `583-594`) — so it covers
     create/update/soft-delete/restore of coupons. This is the same tag the delete branch already
     uses (module:58, 60).
   - `cachePerPermissions()` adds the `user.permissions` context
     (`web/core/lib/Drupal/Core/Access/AccessResult.php:270-273`), required because the restore
     branch's decision reads `$account->hasPermission('restore node entities')`.
   - AC 7's assertion (forbidden result's `getCacheTags()` includes `node_list:coupon` and
     `getCacheContexts()` includes `user.permissions`) will hold: the returned
     `$coupon->access('restore', $account, TRUE)` result is the OR of trash's result (which adds
     `user.permissions`, `TrashEntityAccess.php:113`) and ours (which adds `node_list:coupon` +
     `user.permissions`), and `orIf()` merges cacheability (`AccessResult.php:340-343`).

6. **Edge cases.**
   - *Two trashed same-triple (AC 6):* the finder is live-only, so a trashed coupon is never its
     own duplicate in the restore branch (no exclude needed there — the entity being restored is
     trashed, hence excluded by `notExists('deleted')`). The `exclude_id` is for the *validator's*
     update path only (current `nid <>` at validator:41-43).
   - *Missing-field boundary:* the validator's empty guard (`validator:26-33`) stays in the
     validator before it calls the finder; the hook's restore branch reads code/sponsor/campaign
     and returns `neutral` when any is missing — so trash's own `allowed`/`forbidden` stands.
   - *Validator delegation preserves behaviour:* same conditions, same `accessCheck(FALSE)`, same
     live-only semantics, same self-exclusion on update (`nid <>`). The one behavioural difference
     is the validator now fetches the full duplicate ID list instead of `range(0,1)`
     (validator:44) — functionally identical for its `!== []` check (see nit 2).

## Decisions on the two open points

- **Open point 1 (validator→finder call): use the locator** —
  `\Drupal::service('badcamp_sponsors.coupon_duplicate_finder')`.
  Reason: it matches the module's established style — the validator already resolves
  `\Drupal::entityTypeManager()` directly (`validator:35`), and the `.module` hook already
  resolves the counter via `\Drupal::service('badcamp_sponsors.coupon_counter')` (module:51). The
  validator is currently convention-discovered (it is *not* registered in `services.yml`), so
  constructor injection would require promoting it to a tagged `validation.constraint_validator`
  service — a larger, unrelated refactor with no functional gain for a finder that has one trivial
  dependency and is always present when the module is enabled (no `hasService()` guard needed,
  unlike the trash soft-dependency in `CouponCounter.php:124`). Keep the diff minimal and the
  single-query surface small.

- **Open point 2 (reason builder): inline reason in the hook, no new class.**
  Reason: `DeleteRefusalReason` exists for a *two-consumer round-trip* need — the hook builds it
  (module:57) and `DeleteRefusalSubscriber` recognizes it via `matches()` to scope the 403 page
  by provenance (`DeleteRefusalSubscriber.php:90`, `DeleteRefusalReason::matches()` at
  `DeleteRefusalReason.php:59-74`). The restore reason has exactly one consumer (the hook) and
  AC 4 requires only non-empty + contains the code (wording deliberately not frozen). A
  single-construction-site class here would be over-abstraction. Use an inline
  `sprintf()`-style string, e.g. `Cannot restore coupon: the code "%s" is already used for this sponsor and campaign.`

## Findings

| # | Severity | Where | Finding | Amendment to fold into the brief |
|---|---|---|---|---|
| 1 | warn | brief Direction item 3 / survey §"The new seam" (the `else neutral` clause) | The restore branch's `neutral` return is specified bare ("otherwise neutral"), but the branch is permission-gated (`$account->hasPermission('restore node entities')`) and its outcome depends on the coupon list. The delete branch already tags its neutral (`module:60` → `AccessResult::neutral()->addCacheTags(['node_list:coupon'])`). A bare neutral (a) is not invalidated when a live duplicate is *later created*, so a cached "restore allowed" for a no-duplicate coupon goes stale; and (b) varies by permission but lacks the `user.permissions` context. | Specify the neutral as `AccessResult::neutral()->addCacheTags(['node_list:coupon'])->cachePerPermissions()` (mirror module:60 plus the permissions context the gated branch needs). Optionally extend AC 7 to assert the neutral result's tags/contexts too. |
| 2 | warn | brief Direction item 1 (finder query conditions listed) | The finder must set `->accessCheck(FALSE)`. It is present in the query being extracted (`validator:36`) and in the analogous counter (`CouponCounter.php:101`), but the brief's condition list omits it. Dropping it would make the uniqueness check subject to node grants (unpublished/hidden coupons could evade it) and make the restore check inconsistent with validation. | Add "with `accessCheck(FALSE)`" to the finder's query description. |
| 3 | nit | finder contract / reason string | Clarify who normalizes: the finder normalizes the code internally (defensive `CouponCodeNormalizer::normalize()`), and the hook embeds the *normalized* code in the reason. Trashed coupons are stored trimmed (`module:80-81`), so the stored value is already normalized, but the hook should still normalize defensively so the reason and the finder's comparison share "same code = trim" semantics. | One sentence in Direction item 1 + item 3. |
| 4 | nit | validator delegation | After delegation the validator checks `findLiveDuplicates(...) !== []`, retrieving all duplicate IDs where it previously short-circuited with `range(0,1)` (`validator:44`). Functionally identical; no change required unless F prefers to preserve the short-circuit (not needed for correctness). | Optional: note the validator may keep an emptiness check only. |
| 5 | nit | PR body | `bypass node access` still bypasses the restore block (`NodeAccessControlHandler.php:95-98`), consistent with the delete branch. Worth a one-line note in the PR body (the module docblock already states it), not a code change. | Add a line to the PR-body note in the Non-goals section. |

## Notes for O

No blocking findings. Fold the two `warn` amendments (findings 1 and 2) and, at your
discretion, the `nit` amendments (3-5) into the brief before T-red. The two open-point
decisions (locator for the validator→finder call; inline reason) are now resolved and should be
recorded in `decisions.md` so the diff gate re-checks against them.

## Patterns referenced

- `web/modules/custom/badcamp_sponsors/src/Plugin/Validation/Constraint/CouponCodeUniqueConstraintValidator.php:35-44` — the query to extract
- `web/modules/custom/badcamp_sponsors/src/CouponCounter.php:99-110` — the live-only (no `deleted` condition) query pattern
- `web/modules/custom/badcamp_sponsors/badcamp_sponsors.module:49-61` — the delete branch's forbid + reason + `node_list:coupon` shape
- `web/modules/custom/badcamp_sponsors/src/DeleteRefusalReason.php` + `src/EventSubscriber/DeleteRefusalSubscriber.php:90` — the two-consumer reason precedent
- `web/modules/contrib/trash/src/Hook/TrashEntityHooks.php:77-119`, `src/Hook/TrashEntityAccess.php:126-146`, `src/TrashManager.php:31` — trash restore + query-alter
- `web/core/lib/Drupal/Core/Entity/EntityAccessControlHandler.php:99-110`, `web/core/lib/Drupal/Core/Access/AccessResult.php:308-320`, `web/core/modules/node/src/NodeGrantDatabaseStorage.php:57-60` — OR-combine semantics
- `web/core/lib/Drupal/Core/Entity/EntityBase.php:484-493`, `EntityType.php:912-913` — `node_list:coupon` list-tag invalidation
