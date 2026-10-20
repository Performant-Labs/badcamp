# Handoff-A-dup: Phase 7 — Anti-duplication gate (0006-issue-6)

**Date:** 2026-10-20
**Branch:** `issue-0006-issue-6`
**Diff base:** 0a1e109 (= origin/main)   **Diff head:** a812890
**Reuse map:** `docs/handoffs/0006-issue-6/survey.md`

## Summary

F **extracted** the one `field_coupon_code.value` query into `CouponDuplicateFinder`
and **extended** the existing `badcamp_sponsors_node_access()` hook — no parallel query and no
parallel access mechanism. The single-query grep over `src/` + `.module` returns exactly one hit
(the finder). Every up-front decision A recorded in `handoff-A.md` is honored: the validator reaches
the finder through the locator `\Drupal::service('badcamp_sponsors.coupon_duplicate_finder')`
(service is un-tagged — no promoted tagged service), the restore reason is inline (no new
reason-builder class), both the forbidden and the neutral restore returns carry
`addCacheTags(['node_list:coupon'])->cachePerPermissions()`, and the finder sets `accessCheck(FALSE)`,
casts sponsor/campaign to int, returns ascending int IDs, honors `exclude_id`, and has no explicit
`deleted` condition. The change is proportionate and drift-free. **Verdict: PASS.**

## Verified

**Single-query invariant (grep run by A):**
```
grep -rn "field_coupon_code.value" web/modules/custom/badcamp_sponsors/src web/modules/custom/badcamp_sponsors/badcamp_sponsors.module
→ exactly ONE hit: web/modules/custom/badcamp_sponsors/src/CouponDuplicateFinder.php:45
```
`CouponCounter.php` has no `field_coupon_code` reference (grep: no matches), so no second query site exists.

**Extraction, not addition** — the validator diff removes the inline query in full and replaces it
with a delegation; the module never gains a second code query:
- `CouponCodeUniqueConstraintValidator.php` (diff): the `getQuery()->accessCheck(FALSE)->condition('type','coupon')->condition('field_coupon_code.value',$code)…->range(0,1)` block is **deleted** and replaced by the finder call (`CouponCodeUniqueConstraintValidator.php:35-36`).

**Hook extension, not parallel mechanism** — the new `coupon` + `restore` branch is a third `if`
inside `badcamp_sponsors_node_access()` before the final `return AccessResult::neutral()`
(`badcamp_sponsors.module:68-87`); no new hook, subscriber, or access handler was added.

**A's decisions honored:**
- (a) Locator, not tagged service: `CouponCodeUniqueConstraintValidator.php:35` and
  `badcamp_sponsors.module:78` both use `\Drupal::service('badcamp_sponsors.coupon_duplicate_finder')`;
  `badcamp_sponsors.services.yml:7-10` registers it with no `tags:`.
- (b) Inline reason: `badcamp_sponsors.module:82` `sprintf('Cannot restore coupon: the code "%s" is already used for this sponsor and campaign.', $code)` — no new class.
- (c) Cache metadata: forbidden at `badcamp_sponsors.module:81-84` carries
  `->addCacheTags(['node_list:coupon'])->cachePerPermissions()`; neutrals at lines 70, 76, and 86 carry
  the same tag + context.
- (d) Finder shape: `accessCheck(FALSE)` at `CouponDuplicateFinder.php:43`; `(int)` casts at 46-47;
  ascending int IDs at 51-52; `exclude_id` honored at 48-50; **no** `deleted` condition anywhere in
  the class (live-only via trash's `entity_query_alter`, per survey §"Why no explicit deleted condition").

**No drift from the brief's Direction:**
- Validator keeps the empty/missing-field guard (`CouponCodeUniqueConstraintValidator.php:26-33`) and
  delegates (`35-36`); the exclude id on update is preserved (`!$value->isNew() ? (int) $value->id() : NULL`).
- Restore branch is scoped to exactly `coupon` + `restore` (`badcamp_sponsors.module:68`); the
  `view` and `delete` branches are untouched (diff shows only the added docblock paragraph + the new branch).
- No unrelated refactor of the delete branch; the finder is `final` with a single method — no dead code, no speculative generalization.

## Findings

No blocking findings. No parallel path and no architectural drift.

| # | Severity | Where | Finding | Suggested fix |
|---|---|---|---|---|
| 1 | warn | `.gitignore:44-46` | Three added lines ignoring dual-review gate scratch (`*.prompt.txt`, `*.usage.json`) were not listed in `handoff-F.md`'s "What I changed". Benign — it sits inside the existing "Coding pipeline run state and worktrees" ignore section and touches no source/test/config — but it is a silent out-of-scope file. | O to note it in the PR body or fold it into a pipeline-hygiene follow-up; no code rework. |
| 2 | nit | `badcamp_sponsors.module:79` | The restore branch passes `(int) $entity->id()` as `exclude_id` to the finder. A's up-front note (handoff-A.md edge case 6) observed no exclude is needed there because the trashed entity is already excluded by `notExists('deleted')`; the extra condition is behavior-neutral and defensively consistent with the validator's update path. | None required (harmless belt-and-suspenders). No rework. |

## Verdict: PASS
