# Brief: #4 show the coupon-count reason on a refused delete

Repo: Performant-Labs/badcamp. Issue: #4. Rigor: in-session. UI surface: no. Kind: feature.
Branch: `issue-0004-delete-refusal-message`. Worktree: `.claude/worktrees/0004-delete-refusal-message`.
Tests: `scripts/run-tests.sh` (PHPUnit in DDEV, from the worktree). **Kernel tests only** — no
Functional/BrowserTestBase tests (they exercise the main checkout's web root, not the worktree).

## Objective

When an editor requests `/node/{nid}/delete` for a sponsor or campaign that coupons still
reference (live or trashed), the 403 response body must show the existing coupon-count reason.
Today the reason lives only on the `AccessResult` and the editor sees core's generic
"You are not authorized to access this page." (mechanism and core citations: `survey.md`).

## Direction (from the survey, as amended after A's plan review — see `handoff-A.md`)

A `kernel.exception` event subscriber in `badcamp_sponsors`, **priority 0** (the blessed stock
default; the valid window is -128 < p < 50 — `ExceptionLoggingSubscriber` logs 403s at 50 and
`setResponse()` stops propagation, so p ≥ 50 would silence core's access-denied watchdog log:
`HttpExceptionSubscriberBase.php:34-53`), scoped to route `entity.node.delete_form` and to
`CacheableAccessDeniedHttpException`, that sets its own 403 response instead of letting
`DefaultExceptionHtmlSubscriber` (-128) subrequest `/system/403`.

Scope matching is by **reason provenance, not mere non-emptiness**: A traced two unenumerated
403s that would leak into a themed page under a non-empty-reason scope — trash's
`'Entity is deleted'` and node's `'access content'` refusal (`handoff-A.md` finding 2). So the
reason string gets a **shared reason-builder** — one construction site (a small final class,
e.g. `src/DeleteRefusalReason.php`) used by BOTH `badcamp_sponsors_node_access()` and the
subscriber (which matches `exception->getMessage()` against the builder's output for the node's
bundle, count read from the message). This builder refactor is **spec'd here**, so it is
extend-not-drift; wording stays byte-identical (frozen by the issue). The two enumerated AC 4
cases were traced by A to land with empty reason and stay generic.

Response shape follows `FinalExceptionSubscriber::on4xx` (A finding 5, option i): render a
`#type => 'page'` array (title `t('Access denied')`, parity with core) with the reason as
**`#plain_text`** (NOT `#markup` — `#plain_text` is the escaped-verbatim contract,
`Renderer.php:451-463`), via `renderRoot`; set a `CacheableResponse`, `addCacheableDependency($exception)`
(brings `node_list:coupon` from the access result) and merge the exception's headers. No
`<html>` envelope needed; **no `hook_theme`, no template** (A finding 4 — core's own 403 is a
bare render array).

A-dup invariant (A finding 6): the subscriber reads the reason ONLY from
`$exception->getMessage()` and cacheability ONLY from the exception; the only `sprintf` lives
in the shared builder. No second counter ever (AC 2).

## Acceptance criteria → tests (all Kernel, `web/modules/custom/badcamp_sponsors/tests/src/Kernel/`)

1. **Refused delete renders the reason.** `http_kernel->handle(Request::create('/node/<nid>/delete'))`
   as a user holding `delete any sponsor content` (or campaign) on a referenced sponsor/campaign
   → HTTP 403, body contains `This sponsor is referenced by 2 coupon(s), including coupons in
   trash.` (correct count incl. a trashed coupon; correct bundle name for campaign case).
2. **No second counter.** Verified by inspection: the reason string on the existing access hook
   is the single source; the subscriber re-uses it (A-dup/S check this; no new counting code).
3. **Unreferenced → unaffected.** Same request on a sponsor/campaign with zero referencing
   coupons → HTTP 200 (the delete form loads). Covers live-coupon-moved + trashed-hard-deleted
   fixtures per `DeleteBlockingTest` shapes.
4. **Other 403s unchanged.** (a) A user without the delete permission on an unreferenced sponsor
   → 403 whose body does NOT contain "referenced by"; (b) a non-sponsor/campaign node (e.g.
   basic `page`) delete request by an unauthorized user → 403 generic body.
5. **Escaping + cache tags.** The message renders HTML-escaped (assert with a bundle/title
   fixture chosen so escaping is observable, or assert the escaped form of the message); the
   response's cache tags include `node_list:coupon` (assert via the response/cacheable metadata
   or by rendering the returned array and inspecting `CacheableMetadata`).
6. **Test-first + regression.** The new Kernel test file fails before the change (RED) and passes
   after (GREEN); the existing 12 Kernel + 3 Unit tests in `badcamp_sponsors` keep passing.

## Handoffs

- Journal: `docs/handoffs/0004-delete-refusal-message/decisions.md` (this run)
- T: `.../handoff-T-red.md`, `.../handoff-T-green.md`
- F: `.../handoff-F.md`
- A: `.../handoff-A.md` (plan), `.../handoff-A-dup.md` (diff gate)
- S: `.../handoff-S.md`

## Pipeline configuration for this run

- Rigor `in-session`: brief/diff dual-review gates are declared N/A by the plugin; reviews run
  in-session (A up-front, A-dup after GREEN, S at the end).
- uiSurface `false`: Phase D (design) and Phase U (UI walkthrough) are **N/A by declaration**,
  recorded here, not skipped silently. The story is an HTTP-response behaviour with no
  interactive UI surface; its observable is the response body, covered by Kernel tests.
- Phases: O → (D N/A) → A → T(red) → F → T(green) → A-dup → (U N/A) → S → PR.
- Every role runs as the project's configured zai/glm-5.3 (no model overrides on dispatch).

## Non-goals / guards

- No change to *when* deletion is refused, the message wording, or admin UI beyond the 403
  response (issue: Out of scope).
- No composer/dependency changes, no config schema changes, no new permissions.
- Never commit `web/robots.txt` (scaffold drift), never edit `web/core` or `web/modules/contrib`.
- Stage files by explicit path; Conventional Commit subject + `Co-Authored-By` trailer.
- PR against `main`, title Conventional-Commit (e.g. `feat(badcamp_sponsors): show the
  coupon-count reason on a refused delete`), body closes #4. **No merge** (operator's
  instruction), no version bump (not a Node project).
