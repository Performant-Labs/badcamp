# Handoff-A: Phase 3 — Up-front plan review (0004-delete-refusal-message)

**Date:** 2026-10-17
**Branch:** `issue-0004-delete-refusal-message` (base caf4a7b)
**Brief reviewed:** `docs/handoffs/0004-delete-refusal-message/brief.md`   **Reuse map:** `docs/handoffs/0004-delete-refusal-message/survey.md`   **Wireframe:** N/A (uiSurface false, declared in brief)
**Verdict:** PASS

## Summary

PASS. The plan extends the platform at the platform's own seam: a `kernel.exception`
subscriber preempting core's -128-priority `DefaultExceptionHtmlSubscriber` is the stock
Drupal mechanism for replacing a 403 page, and every core-file claim in the survey checks
out against this checkout. The reason string and the `node_list:coupon` cache tag both
already ride on `CacheableAccessDeniedHttpException`, so the subscriber re-uses them with
zero re-counting (AC 2 clean by construction). No parallel path: the module has no existing
subscriber to extend, `src/EventSubscriber/` + a tagged `services.yml` entry is the correct
new-file shape, and the refusal/count/reason all stay single-source. Five warn-level
findings below tighten the plan (priority upper bound, scoping leaks, `#plain_text`,
no `hook_theme`, response shape) — none is architectural drift; O should fold the
corresponding one-line amendments into the brief before T-red.

## Verified: the mechanism is sound (all citations read in this checkout)

1. **The refusal reaches the subscriber intact.**
   `web/core/lib/Drupal/Core/Routing/AccessAwareRouter.php:112-118` — a non-allowed,
   cacheable access result on a cacheable request (GET default) throws
   `CacheableAccessDeniedHttpException($access_result, reason)`;
   `web/core/lib/Drupal/Core/Http/Exception/CacheableAccessDeniedHttpException.php:12-22`
   stores the whole access result's cacheability (`CacheableDependencyTrait`) with the
   reason as message. The route's requirement is `_entity_access: node.delete`
   (`web/core/lib/Drupal/Core/Entity/Routing/DefaultHtmlRouteProvider.php:282-291`, route
   name `entity.node.delete_form` added at `:94-95`).
2. **Our hook's forbidden-with-reason survives the combine.**
   `EntityAccessControlHandler::access()` OR-chains `hook_entity_access` +
   `hook_ENTITY_TYPE_access` results first and skips `checkAccess()` when already
   forbidden (`web/core/lib/Drupal/Core/Entity/EntityAccessControlHandler.php`,
   `access()` + `processAccessHookResults():131-143`). Our
   `AccessResult::forbidden()->setReason(...)->addCacheTags(['node_list:coupon'])`
   (`badcamp_sponsors.module:55-57`) is the only forbidden on this route for the story's
   user, and `AccessResult::or()` propagates a forbidden operand's reason
   (`web/core/lib/Drupal/Core/Access/AccessResult.php:314-319`). Reason + tags land on
   the exception together — AC 1 and AC 5 mechanisms confirmed.
3. **The preempt seam works as surveyed.**
   `DefaultExceptionHtmlSubscriber` priority -128 (`:70-74`), `on403` → subrequest to
   `/system/403` (`:114-116`) → `Http4xxController::on403()` generic markup, title
   "Access denied" (`web/core/modules/system/src/Controller/Http4xxController.php:56-66`).
   Any subscriber with priority > -128 that calls `$event->setResponse()` wins
   (`setResponse()` stops propagation —
   `vendor/symfony/http-kernel/Event/RequestEvent.php:40-45`).
4. **AC 4's two enumerated cases stay generic — traced, not assumed.**
   (a) Permitted-bundle user without delete permission: node module's permission logic is
   `allowedIfHasPermission('delete any … content')`
   (`web/core/modules/node/src/Hook/NodeEntityHooks.php:132`) → neutral *with* reason when
   missing (`AccessResult.php:121-126`), but OR-merges drop reasons from non-forbidden
   operands (`AccessResult.php:314-319`) and every other contributor is neutral
   (grants path returns bare neutral when no grant modules —
   `web/core/modules/node/src/NodeGrantDatabaseStorage.php`, `access()` early return).
   Final reason `''` → subscriber no-op → generic 403. (b) Non-sponsor/campaign bundle:
   our hook returns bare neutral (`badcamp_sponsors.module:59-62`), same empty-reason
   outcome. The plan's "non-empty reason" scope is correct for the tested AC 4 cases.
5. **Kernel-test seam is real.** `http_kernel->handle(Request::create(...))`:
   `web/core/tests/Drupal/KernelTests/Core/Action/GotoActionTest.php:46-47`,
   `web/core/tests/Drupal/KernelTests/Core/Htmx/HtmxRequestTest.php:81-82`,
   `web/core/tests/Drupal/KernelTests/Component/ComponentRenderTest.php:38` (note: survey
   cited `Core/Component/`; actual path is `KernelTests/Component/` — precedent valid).
   Account: the `router` service is `AccessAwareRouter('@router.no_access_checks',
   '@access_manager', '@current_user')` (`web/core/core.services.yml:1176-1177`), so
   `account_switcher`/`current_user->setAccount()` before `handle()` carries the user
   (dance shown in `web/core/tests/Drupal/KernelTests/Core/Session/AccountSwitcherTest.php:24-27`).
   Route table: `router.builder->rebuild()` lazily on route resolution
   (`web/core/tests/Drupal/KernelTests/RouteProvider.php:31-38`). Survey's T-caveats all
   stand.
6. **Escaping and cacheability have exact core precedents.** `#plain_text` escapes
   verbatim instead of XSS-filtering
   (`web/core/lib/Drupal/Core/Render/Renderer.php:451-463`); `FinalExceptionSubscriber::on4xx`
   is the response-shape precedent: `new CacheableResponse(...)`,
   `$response->addCacheableDependency($exception)`, `$response->headers->add($exception->getHeaders())`,
   `$event->setResponse(...)` — and `DefaultExceptionHtmlSubscriber:180-182` shows the same
   exception→response cacheability merge.

## Findings

| # | Severity | Plan element | Drift dimension | Finding | Suggested fix |
|---|---|---|---|---|---|
| 1 | warn | "priority > -128" (brief Direction ¶1) | pattern consistency | Lower bound only. `ExceptionLoggingSubscriber` logs every 403 ("access denied" watchdog) at priority **50** (`ExceptionLoggingSubscriber.php:124-127`); since `setResponse()` stops propagation (`RequestEvent.php:40-45`), a subscriber at ≥ 50 silently kills that log entry — exactly what core's own docs warn against (`HttpExceptionSubscriberBase.php:34-53`, "Do not register a listener with a higher priority unless you want exceptions to not get logged"). | Pin the window in the brief: **-128 < priority < 50; use the stock default 0** (docs at `HttpExceptionSubscriberBase.php:36-38` bless 0 for module listeners). |
| 2 | warn | Scope = route `entity.node.delete_form` + `CacheableAccessDeniedHttpException` + non-empty reason | cross-cutting / scope tightness (AC 4 "other 403s unchanged") | Correct for both *enumerated* AC 4 cases (verified, see item 4 above), but leaks two unenumerated 403s into the themed page: (a) trash's `forbidden('Entity is deleted')` on a hand-typed delete URL for a **trashed** sponsor/campaign (`web/modules/contrib/trash/src/Hook/TrashEntityAccess.php:212-216`); (b) node's `forbidden("The 'access content' permission is required.")` for any account lacking `access content` (`NodeAccessControlHandler.php:99-103`). Both would newly render themed reason pages — behavior change beyond the story. | Tighten in the brief — either (preferred) also match the reason's provenance via a **shared reason-builder** (one small class/const method used by BOTH `badcamp_sponsors_node_access()` and the subscriber; wording byte-identical, single construction site — a spec'd refactor-to-extend, so name it in the brief or A-dup must flag it), or additionally scope by node bundle read from the upcast `node` route param (available at exception time: `AccessAwareRouter.php:88-94` adds route params *before* `checkAccess()` throws). Bundle-only still leaks (a) for sponsor/campaign; provenance-matching leaks nothing. A prefix/regex match on the frozen wording without a shared builder works but leaves the string in two places — fragile; don't. |
| 3 | warn | "message is the exception's reason string verbatim, HTML-escaped" (brief) | error handling / security | The escape mechanism must be `#plain_text`, not `#markup`: `#markup` runs through `Xss::filterAdmin()` (mangles verbatim-ness) or passes a `MarkupInterface` raw (`Renderer.php:850-876`); `#plain_text` is the escaped-verbatim contract (`Renderer.php:451-463`). Load-bearing, not hygiene: with finding 2's leaks, the reason can be an arbitrary other-module string. | Brief should name `#plain_text` explicitly so T asserts the escaped form (AC 5) against the right mechanism. |
| 4 | info | "themed 403 page" / survey's "hook_theme optional" | abstraction level | `hook_theme` + template is not warranted: the AC demands only the escaped message in the body; core's own 403 is a bare controller render array (`Http4xxController.php:62-66`); `#type => 'page'` + `#title` already themes through `page.html.twig` with zero new registry surface (`web/core/lib/Drupal/Core/Render/Element/Page.php:18-23`). | State "render array only, no hook_theme, no template" in the brief; keep title parity with core ("Access denied"). |
| 5 | warn | Response construction (implied by plan) | pattern consistency | Two viable shapes; the brief should name one so T's assertions match. (i) `FinalExceptionSubscriber::on4xx` shape — render the page array via `renderRoot`, set a `CacheableResponse` + `addCacheableDependency($exception)` + merge exception headers (full precedent cited above); body is themed page content without the `<html>` envelope — satisfies every AC. (ii) Subrequest to a module route à la `DefaultExceptionHtmlSubscriber::makeSubrequest()` — full envelope but a new route + controller + more surface; not recommended for this story. | Pick (i) in the brief. Note for T: the test asserts "body contains message", so (i) suffices; `renderRoot` in-kernel is precedented (`ComponentRenderTest.php:38` drives the full pipeline). |
| 6 | warn | AC 2 "no second counter" | duplication (a-dup preview) | Verified single-source today: one counter (`CouponCounter.php:43-59`, `countBySponsor`/`countByCampaign`), one reason construction site (`badcamp_sponsors.module:56`), one consumer on the refusal path. The subscriber must read the reason ONLY from `$exception->getMessage()` and cacheability ONLY from the exception — never re-derive. If finding 2's shared-builder option is taken, the builder becomes the single construction site (still AC-2-clean). | Brief should state the invariant for A-dup: "reason consumed only from the exception; the only sprintf lives in [the hook / the shared builder]." |

No `block` findings.

## Notes for O

PASS — no amendment is *required*, but findings 1, 2, 3 and 5 are one-line brief edits
that make F's contract unambiguous and make finding 2 checkable at A-dup:

1. Priority: "-128 < priority < 50; use 0."
2. Scope: pick provenance-match via a shared reason-builder (preferred) or bundle scope,
   and name the builder refactor as spec'd so it is not read as drift.
3. Escaping: `#plain_text`, not `#markup`.
5. Response shape: `FinalExceptionSubscriber::on4xx` pattern; no `<html>` envelope needed.

## Patterns referenced

- `web/core/lib/Drupal/Core/EventSubscriber/HttpExceptionSubscriberBase.php:34-53` — the authoritative `kernel.exception` priority map and the "priority 0 for module listeners" guidance.
- `web/core/lib/Drupal/Core/EventSubscriber/FinalExceptionSubscriber.php` (`on4xx`) — exception-subscriber-sets-its-own-response precedent with `CacheableResponse` + `addCacheableDependency($exception)`.
- `web/core/lib/Drupal/Core/EventSubscriber/DefaultExceptionHtmlSubscriber.php:70-74,114-116,138-198` — the -128 generic-403 subrequest being preempted, and its exception→response cacheability merge.
- `web/core/modules/system/src/Controller/Http4xxController.php:56-66` — core's own 403 as a bare render array (the "no hook_theme" baseline).
- `web/modules/custom/badcamp_sponsors/badcamp_sponsors.module:48-60` + `src/CouponCounter.php` + `badcamp_sponsors.services.yml` — the existing refusal seam being surfaced, unchanged.
