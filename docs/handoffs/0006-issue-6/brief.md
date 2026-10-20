# Brief: #6 refuse restoring a trashed coupon that would duplicate a live coupon's code

Repo: Performant-Labs/badcamp. Issue: #6. Rigor: **second-opinion**. UI surface: no. Kind: feature.
Branch: `issue-0006-issue-6`. Worktree: `.claude/worktrees/0006-issue-6`.
Tests: `scripts/run-tests.sh` (PHPUnit in DDEV, from the worktree). **Kernel tests only** — no
Functional/BrowserTestBase tests (they exercise the main checkout's web root, not the worktree).

## Objective

Restoring a trashed coupon must be refused when it would create a live duplicate of a live coupon's
(normalized code, sponsor, campaign). The refusal is a node access result on the `restore` operation —
the operation trash checks before it offers or performs a restore (including its bulk action). The
existing uniqueness validation is untouched in behaviour: it still looks only at live coupons, so
re-issuing after a soft delete stays allowed.

## Direction (from the survey; subject to A's plan review — see `handoff-A.md`)

1. **Finder service** `badcamp_sponsors.coupon_duplicate_finder`
   (class `Drupal\badcamp_sponsors\CouponDuplicateFinder`, file
   `web/modules/custom/badcamp_sponsors/src/CouponDuplicateFinder.php`): one method, e.g.
   `findLiveDuplicates(string $code, int|string $sponsor_id, int|string $campaign_id, ?int $exclude_id = null): array`
   — normalizes the code internally via `CouponCodeNormalizer::normalize()`, runs **the one** entity
   query (`type=coupon`, `field_coupon_code.value` = code, `field_coupon_sponsor.target_id` = sponsor,
   `field_coupon_campaign.target_id` = campaign, `nid <>` exclude when given, **`accessCheck(FALSE)`**),
   no explicit `deleted` condition (live-only via trash's `entity_query_alter`, survey §"Why no
   explicit deleted condition"), casts IDs to int, sorts ascending. Registered in
   `badcamp_sponsors.services.yml` (deps: `entity_type.manager`). The finder is reached by
   `\Drupal::service()` (locator), matching the validator's existing `\Drupal::entityTypeManager()`
   and the hook's `\Drupal::service(...)` style — no tagged-service promotion (A decision 1).
2. **Validator delegates** (AC 2): `CouponCodeUniqueConstraintValidator::validate()` keeps its
   empty/missing-field guard, then calls `findLiveDuplicates(...)` and raises the violation when the
   result is non-empty (`!== []`); the module then contains exactly one query comparing
   `field_coupon_code.value` (grep over `src/` + `.module`).
3. **Restore branch** in `badcamp_sponsors_node_access()`: for `coupon` + `restore`, when the account
   holds `restore node entities` and the finder returns a non-empty duplicate set, return
   `AccessResult::forbidden()->setReason(<inline reason containing the normalized code>)->addCacheTags(['node_list:coupon'])->cachePerPermissions()`;
   otherwise return
   `AccessResult::neutral()->addCacheTags(['node_list:coupon'])->cachePerPermissions()` (so a cached
   "restore allowed" is invalidated when a live duplicate is later created, and the permission-gated
   branch varies by permission). Missing code/sponsor/campaign on the trashed coupon → `neutral`
   (same tag/context). The hook only forbids, never grants; `view`/`update`/`delete` on coupons and
   all ops on sponsors/campaigns are untouched (the branch is scoped to exactly `coupon` + `restore`,
   so every other (bundle, operation) keeps the hook's existing early-return/`neutral` behaviour).
   No new reason-builder class — the restore reason has one consumer (A decision 2).

## Acceptance criteria → tests (all Kernel, `web/modules/custom/badcamp_sponsors/tests/src/Kernel/`)

1. **Finder returns live duplicates (and only live).** Direct service test: build live + trashed
   coupons; assert the method returns the right ascending integer node IDs, never trashed, honours the
   exclude id, accepts numeric-string IDs. (AC 1)
2. **Restore refused when a live duplicate exists, with a positive control.** A trashed coupon whose
   triple matches a live coupon; account with `restore node entities` → `$coupon->access('restore',
   $account, true)` is forbidden; the reason is non-empty and contains the code. The same account is
   proven able to restore a *non*-duplicate coupon (AC 3's allowed cases), so the refusal is isolated
   to the finder, not to a missing permission. (AC 4)
3. **Not refused when code/sponsor/campaign differs, or a triple field is missing.** Four cases, all
   allowed for a restore-permitted account: different code, different sponsor, different campaign, and
   a trashed coupon missing one of code/sponsor/campaign (hook returns `neutral` → trash's own
   `allowed` stands). (AC 5 + the Direction's missing-field boundary)
4. **Two trashed same-triple coupons.** Both restorable with no live match; after the first is
   restored the second is refused; a coupon is never its own duplicate (exclude id). (AC 6)
5. **Cacheability.** The forbidden result's cache tags include `node_list:coupon` and its cache
   contexts include `user.permissions` — asserted on the returned `AccessResult` via
   `getCacheTags()` / `getCacheContexts()` from the `$coupon->access('restore', $account, true)` call
   (not merely inferred). (AC 7)
6. **Unrelated operations unaffected, testably.** (a) Creating a live coupon whose triple matches a
   trashed coupon still saves without a `field_coupon_code` violation (validation live-only); (b) for a
   coupon, `view`/`update`/`delete` for a permitted account are still allowed (the restore branch does
   not forbid them), and `view` still requires `view coupon content` (existing `CouponAccessTest`
   stays green); (c) sponsor/campaign `delete` is still governed by the counter (existing
   `DeleteBlockingTest` stays green). Assertions target the specific operations, not "unchanged" in
   the abstract. (AC 8)
7. **Test-first + regression + phpcs.** New Kernel tests fail before the change (RED — recorded in
   `handoff-T-red.md` with the failing run, and confirmed by the plugin-owned RED verdict at the
   t-red → implement advance) and pass after (GREEN); existing suite (12 Kernel + 3 Unit) keeps
   passing; `phpcs` (Drupal + DrupalPractice) clean on every touched file. (AC 9, AC 3)

## Handoffs

- Journal: `docs/handoffs/0006-issue-6/decisions.md` (this run)
- T: `.../handoff-T-red.md`, `.../handoff-T-green.md`
- F: `.../handoff-F.md`
- A: `.../handoff-A.md` (plan), `.../handoff-A-dup.md` (diff gate)
- S: `.../handoff-S.md`
- Brief gate artifact: `.../dual-review-brief.md`; diff gate artifact: `.../dual-review-diff.md`

## Pipeline configuration for this run

- Rigor `second-opinion`: the brief dual-review gate (after survey-brief) and the diff dual-review
  gate (after t-green, before a-dup) are **active**; each must record PASS (or a named waiver) before
  the corresponding `pipeline_advance`.
- uiSurface `false`: Phase D (design) and Phase U (UI walkthrough) are **N/A by declaration**,
  recorded, not skipped silently. The story is a node-access behaviour with no interactive UI surface;
  its observable is `$coupon->access('restore', ...)` + the trash admin's refusal, covered by Kernel
  tests.
- Phases: O → (D N/A) → A → T(red) → F → T(green) → A-dup → (U N/A) → S → PR.
- Config: no `stages` section and no `finalReview` mode → the A-dup and S stages are the usual
  separate in-session dispatches; the advance-side stage-artifact verification is inactive (the
  dispatch + on-disk handoff is the stage record, per run 0001/0004 precedent).

## Non-goals / guards

- No change to the delete block wording/behaviour, no case-insensitive comparison, no cleanup of
  existing duplicates, no UI beyond trash's own refused-restore rendering.
- Restore paths that skip entity access (Drush bulk restore, storage restore API) are **out of scope
  by issue decision** (the issue's own "Out of scope" note), so they remain unguarded; this limitation
  is documented in the PR body, not silently dropped. Likewise `bypass node access` keeps the
  platform's short-circuit (the restore block is node-access-level, same as the delete branch) — the
  PR body notes this too.
- No composer/dependency changes, no config schema changes, no new permissions.
- Never commit `web/robots.txt` (scaffold drift) or the primary checkout's uncommitted session setup
  (`opencode.json`, `.aftersight/pipeline-pin.json`, `.aftersight/.gitignore`); never edit `web/core`
  or `web/modules/contrib`.
- Stage files by explicit path; Conventional Commit subject + `Co-Authored-By` trailer; PR against
  `main`, body closes #6. Not a Node project → no version bump.
