# Survey: 0004-delete-refusal-message (issue #4)

Repo: Performant-Labs/badcamp (Drupal CMS 2 / Drupal 11.4.10, DDEV). Worktree:
`.claude/worktrees/0004-delete-refusal-message`, branch `issue-0004-delete-refusal-message`,
base caf4a7b (= origin/main). Tests: `scripts/run-tests.sh` (PHPUnit in DDEV, against this
checkout). Kernel tests only for this story (issue AC 6; Functional tests can't exercise a
worktree).

## The story in one paragraph

`badcamp_sponsors_node_access()` already refuses `delete` on a sponsor/campaign that coupons
still reference, setting the explanation only as the `AccessResult` reason string. Drupal's
generic 403 page never renders that reason, so editors see a bare "Access denied". The story:
make that refusal render as a themed 403 page showing the coupon-count message, without
changing when deletion is refused, the message wording, or any other 403.

## What exists today (verified in-tree)

| Fact | Where |
|------|-------|
| The refusal: `AccessResult::forbidden()->setReason('This %s is referenced by %d coupon(s), including coupons in trash.')` + cache tag `node_list:coupon` | `web/modules/custom/badcamp_sponsors/badcamp_sponsors.module:48-60` |
| Trash-aware counter service `badcamp_sponsors.coupon_counter` (`countBySponsor`/`countByCampaign`); AC 2 forbids a second counting implementation | `web/modules/custom/badcamp_sponsors/src/CouponCounter.php`; `badcamp_sponsors.services.yml` |
| Existing Kernel tests covering the refusal at API level (fixtures: sponsors, campaigns, live + trashed coupon; user with `delete any sponsor/campaign content`) | `web/modules/custom/badcamp_sponsors/tests/src/Kernel/DeleteBlockingTest.php` |
| This story is the recorded follow-up to run 0001's AC 9 advisory ("the rendered 403 page shows generic text; a themed 403 … would be a small follow-up") | `docs/handoffs/0001-badcamp-sponsors/decisions.md` (AC 9 entry) |

## How a 403 flows through core (all citations from this checkout's web/core)

1. Route access fails on `entity.node.delete_form` → the router throws
   **`CacheableAccessDeniedHttpException`** for cacheable requests, constructed with the whole
   `AccessResult` (cacheability — including `node_list:coupon` — survives) and the reason as the
   exception message: `web/core/lib/Drupal/Core/Routing/AccessAwareRouter.php:114-117`.
   (`CacheableAccessDeniedHttpException` exposes the cacheability via
   `CacheableDependencyTrait`; message = reason:
   `web/core/lib/Drupal/Core/Http/Exception/CacheableAccessDeniedHttpException.php`.)
2. `kernel.exception` subscribers run. Core's HTML fallback
   `DefaultExceptionHtmlSubscriber` has **priority -128** ("A very low priority so that custom
   handlers are almost certain to fire before it") and handles 403 by a **subrequest to
   `/system/403`**: `web/core/lib/Drupal/Core/EventSubscriber/DefaultExceptionHtmlSubscriber.php:69-120`.
3. `/system/403` → `Http4xxController::on403()` → generic
   `t('You are not authorized to access this page.')`, title `t('Access denied')`
   (`web/core/modules/system/src/Controller/Http4xxController.php`). This is the page editors
   see today, without the reason.

So the platform seam exists: a `kernel.exception` subscriber with priority > -128 that sets its
own response preempts the generic 403 subrequest. The reason and the cache tags are both already
on the exception object — nothing needs re-counting, nothing needs guessing.

## Kernel-test seam for an HTTP-level assertion (no Functional tests)

Kernel tests can drive the real request pipeline:
`$this->container->get('http_kernel')->handle(Request::create('/node/<nid>/delete'))`.
Core kernel precedents doing exactly this: `web/core/tests/Drupal/KernelTests/Core/Action/GotoActionTest.php`,
`.../Core/Htmx/HtmxRequestTest.php`, `.../Component/ComponentRenderTest.php`. Caveats T must
settle: router table needs a rebuild after module install; the request needs a user for the
access check (core kernel tests use `account_switcher` / the `current_user` service); response
body render must go through the renderer (e.g. `renderRoot` on the returned content, or the
`html` service/attachment machinery) — T verifies the exact dance that yields a string body.

## Reuse & Analogous-Feature map

| Need | Closest existing object | Extend vs new |
|------|------------------------|---------------|
| Detect the refusal with reason + cacheability | `CacheableAccessDeniedHttpException` (core, on the exception event) | **Reuse** — subscribe to it; no re-derivation |
| The message + count | Reason string from `badcamp_sponsors_node_access()` (`badcamp_sponsors.module:56`) | **Reuse verbatim** — wording frozen by the issue's out-of-scope note |
| Trash-aware count | `badcamp_sponsors.coupon_counter` | **Reuse** — already on the refusal path; AC 2 explicit |
| Event-subscriber shape in this module | none yet in `badcamp_sponsors` (first subscriber); core precedent `DefaultExceptionHtmlSubscriber` + module subscribers under `web/core/modules/*/src/EventSubscriber/` | **New file**, stock pattern: `src/EventSubscriber/DeleteRefusalSubscriber.php`, `badcamp_sponsors.services.yml` tag `event_subscriber` |
| "Themed page" render | `#type => 'page'` render element + `#title`; core's own 403 uses a controller render array | Stock render array; a `hook_theme` + template is optional polish for A to weigh — the AC only demands the message in the body, HTML-escaped |
| Fixtures for coupons/sponsors/campaigns + permitted user | `DeleteBlockingTest` helpers (`createSponsor/createCampaign/createCoupon/hardDelete`, `trash.settings` setup) | **Extend** — new Kernel test file may mirror the proven setUp shape (Drupal autoloading makes a shared base awkward pre-module; run 0001 accepted per-file duplication) |

## Constraints (binding)

- No change to *when* deletion is refused, to the message wording, or to any admin UI beyond
  the 403 response (issue: Out of scope).
- Other 403s unchanged: permission-less users on unreferenced nodes, non-sponsor/campaign
  bundles, other routes (AC 4) — the subscriber must scope tightly (route + bundle + reason
  present) or be a no-op.
- HTML-escaped message; response cache tags keep `node_list:coupon` (AC 5).
- No composer/dependency changes; `web/robots.txt` scaffold drift in the primary checkout is
  pre-existing and must not be committed (run 0001 journal precedent).
- Drupal coding standard: `vendor/bin/phpcs --standard=Drupal,DrupalPractice` via `ddev exec`.
