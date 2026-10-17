# Handoff-F: Phase 5 - Implement

**Date:** 2026-10-17
**Branch / worktree:** `issue-0004-delete-refusal-message` (`.claude/worktrees/0004-delete-refusal-message`)
**Contract:** handoff-T-red.md (tests + the behaviors they pin), Direction per brief.md as amended, A's plan review handoff-A.md (PASS)

## What I changed

### 1. NEW `web/modules/custom/badcamp_sponsors/src/DeleteRefusalReason.php`

The shared reason builder — the single construction site of the coupon-count reason
(T contract file 1, brief Direction ¶"shared reason-builder", A finding 2's preferred fix).

- `build(string $bundle, int $count): string` — `sprintf(self::PATTERN, $bundle, $count)` where
  `PATTERN` is byte-identical to the frozen wording at `badcamp_sponsors.module:56` (T contract:
  "wording byte-identical (frozen by the issue)"). The only `sprintf` in the module lives here
  (A finding 6 invariant).
- `matches(string $reason): bool` — provenance test without re-counting (T contract file 1: "a way
  for the subscriber to test provenance of an arbitrary reason string … WITHOUT re-counting").
  Quotes the raw `PATTERN` via `preg_quote`, then substitutes `%s` → the literal bundle name and
  `%d` → `\d+`, anchored `^…$`, for each of the two bundles. No counting, no service calls; a
  count of any magnitude and either bundle name match, everything else (empty reason, trash's
  `'Entity is deleted'`, node's `'access content'` refusal, any other module's string) does not.
- Idiom follows the module's existing builder classes (`CouponTitleBuilder`): final class, static
  methods, `declare(strict_types=1)`, full typing.

### 2. NEW `web/modules/custom/badcamp_sponsors/src/EventSubscriber/DeleteRefusalSubscriber.php`

The `kernel.exception` subscriber (T contract file 2, brief Direction ¶1).

- `getSubscribedEvents()` → `KernelEvents::EXCEPTION => [['onException', 0]]` — priority **0**,
  the stock default in the blessed window −128 < p < 50 (A finding 1): below
  `ExceptionLoggingSubscriber` (50) so core's access-denied watchdog log survives, above
  `DefaultExceptionHtmlSubscriber` (−128) so `setResponse()` preempts the `/system/403` subrequest.
- `onException()` guards, in order, each a plain early-return so every non-matching 403 keeps
  core's generic page:
  1. `$exception instanceof CacheableAccessDeniedHttpException` (plain access-denied exceptions
     pass through);
  2. route name (`RouteObjectInterface::ROUTE_NAME` from the request attributes, populated by
     `AccessAwareRouter::matchRequest()` **before** `checkAccess()` throws — verified in this
     checkout at `AccessAwareRouter.php:88-91`) equals `entity.node.delete_form`;
  3. `DeleteRefusalReason::matches((string) $exception->getMessage())` — provenance scope, not
     non-emptiness (A finding 2: keeps trash's and node's unenumerated 403s generic).
- Response construction per `FinalExceptionSubscriber::on4xx()` (A finding 5 option i):
  a `['#type' => 'page', '#title' => t('Access denied'), 'content' => ['#plain_text' => $reason]]`
  array — the exact shape of core's bare pages (`BareHtmlPageRenderer::renderBarePage()`: `'page' => ['#type' => 'page', '#title' => $title, 'content' => $content]`) — rendered with `renderRoot()`,
  then `new CacheableResponse($content, $exception->getStatusCode(), …)`,
  `addCacheableDependency($exception)` (brings `node_list:coupon`), `headers->add($exception->getHeaders())`,
  `setResponse()`. `#plain_text` (not `#markup`) is the escaped-verbatim contract (A finding 3).
- The reason is consumed **only** from `$exception->getMessage()` and cacheability **only** from
  the exception (A finding 6 invariant); `CouponCounter` is never called from the subscriber (AC 2).
- Renderer is constructor-injected (`@renderer`) rather than `\Drupal::service('renderer')` at
  call time — same mechanism the plan names, but DI-clean for a registered service (and required
  to keep DrupalPractice's GlobalDrupal sniff quiet in a service class).

### 3. MODIFY `web/modules/custom/badcamp_sponsors/badcamp_sponsors.module`

The two-line refactor of T contract file 4: `badcamp_sponsors_node_access()`'s
`setReason(sprintf('This %s is referenced by %d coupon(s), including coupons in trash.', …))`
becomes `setReason(DeleteRefusalReason::build($bundle, $count))`, plus the `use` import. Nothing
else in the hook, its forbidden/neutral results, or its `node_list:coupon` cache tags changed —
the diff is exactly those two lines. The bundle list in the hook's `in_array()` stays literal
(minimal diff; the builder's list exists for provenance matching, a different concern).

### 4. MODIFY `web/modules/custom/badcamp_sponsors/badcamp_sponsors.services.yml`

`badcamp_sponsors.delete_refusal_subscriber` registered with class
`Drupal\badcamp_sponsors\EventSubscriber\DeleteRefusalSubscriber`, argument `@renderer`, tagged
`event_subscriber` (T contract file 3). Priority 0 is declared in `getSubscribedEvents()` — the
tag itself carries no priority in Drupal, so this is the correct (and only) place.

## Self-check

Authoritative GREEN is the plugin's T-green run; these are my local runs via `scripts/run-tests.sh`
from the worktree.

1. Target file (was 2 failures in T-red):
   `./scripts/run-tests.sh web/modules/custom/badcamp_sponsors/tests/src/Kernel/DeleteRefusalMessageTest.php`
   → `OK (2 tests, 34 assertions)`
2. Full suite (AC 6; T-red baseline was 57/574 with the 2 new failing):
   `./scripts/run-tests.sh`
   → `OK (57 tests, 590 assertions)` — 55 pre-existing + 2 new, 0 failures.
3. phpcs on all four files, via the script's ddev pattern from the primary checkout:
   `vendor/bin/phpcs --standard=Drupal,DrupalPractice <the 4 files>` → **exit 0, no output.**

### Debug note (how one real bug was found and fixed)

First run was still RED with the generic 403 body. Temporary throw-instrumentation in my own
subscriber (added and removed; no test file touched) proved: the service IS dispatched, the
exception IS `CacheableAccessDeniedHttpException`, route IS `entity.node.delete_form`, message IS
the exact reason. The failure was in my first `matches()`: it built the per-bundle pattern with
`sprintf(self::PATTERN, $bundle, '%d')` — but `%d` **casts** its argument, so `'%d'` became `0`
and the pattern matched only a count of zero. Rewritten to quote the raw pattern first and then
substitute the placeholders (current shape). Both tests went green on the next run.

## Tests I think are wrong

None. Both methods pin exactly the contract's response behavior, and their AC 4 guards (generic
403s) and AC 3/5 assertions (real form, cache tag) all pass against the implementation as specced.

## Deviations from the contract

- **Renderer DI vs `\Drupal::service('renderer')`**: injected via constructor (same `renderRoot`
  mechanism the plan names). Reason: registered-service idiom + DrupalPractice sniff.
- **No `<h1>` in the rendered body**: core's generic 403 gets its `<h1>Access denied</h1>` from
  `SimplePageVariant`'s `page_title` block in the *full* renderer pipeline; our bare page renders
  through `page.html.twig` directly, which has no title element — `#title => t('Access denied')`
  is on the array for parity, per the contract's exact page-array shape. Nothing in the test
  suite asserts an `h1` on our page; AC 4(b) asserts the generic page (which we don't touch).
  I kept the contract's array shape rather than inventing a `page_title` child.
- **`web/robots.txt` shows as modified in the worktree** — pre-existing scaffold drift from the
  worktree's `composer install` (brief: never commit it). Not touched by me, not staged.

## Ready for T(green)

Implemented against the RED contract; local suite fully green and phpcs clean. The plugin should
run the suite: GREEN means the loop advances; RED means I rework.
