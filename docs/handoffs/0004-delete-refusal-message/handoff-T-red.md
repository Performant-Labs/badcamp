# Handoff-T-red: Phase 4 — Author the failing suite (0004-delete-refusal-message)

**Date:** 2026-10-17
**Branch:** `issue-0004-delete-refusal-message` (worktree `.claude/worktrees/0004-delete-refusal-message`)
**Brief reviewed:** `docs/handoffs/0004-delete-refusal-message/brief.md` (Direction as amended after A's plan review)
**A's plan review:** `docs/handoffs/0004-delete-refusal-message/handoff-A.md` — PASS; its "Verified" section drove the test design
**Test file (new):** `web/modules/custom/badcamp_sponsors/tests/src/Kernel/DeleteRefusalMessageTest.php` — staged by explicit path

## A precondition

Confirmed: A returned PASS on the plan (Phase 3). Wireframe N/A (uiSurface false, declared in the brief).

## The observable contract the suite pins (what F must satisfy)

All assertions are HTTP-level against the **response** of
`$this->container->get('http_kernel')->handle(Request::create('/node/<nid>/delete'))`
with `$this->container->get('current_user')->setAccount($user)` set first. The
access-level behavior (hook returns forbidden with the reason) is already pinned by
`DeleteBlockingTest`; this file pins only the RESPONSE contract.

| # | AC | Pinned behavior | Test method |
|---|----|-----------------|-------------|
| 1 | AC 1 (sponsor) | Referenced sponsor (1 live + 1 trashed coupon), user holds `access content` + `delete any sponsor content` → **403** and body contains `This sponsor is referenced by 2 coupon(s), including coupons in trash.` | `testRefusedSponsorDeleteRendersCouponCountReason` |
| 2 | AC 1 (campaign) | Campaign referenced by 1 live + 1 trashed coupon, user holds `delete any campaign content` → **403** and body contains `This campaign is referenced by 2 coupon(s), including coupons in trash.` (bundle name + count both interpolated from its own fixtures) | `testRefusedCampaignDeleteRendersCouponCountReason` |
| 3 | AC 5 (cache tag) | The 403 response `instanceof CacheableResponseInterface` and `node_list:coupon` ∈ `getCacheableMetadata()->getCacheTags()` | method 1 (in-method) |
| 4 | AC 3 | Unreferenced sponsor + permitted user → **200** with the real delete confirm form in the body (markers `node-sponsor-delete-form`, `value="Delete"`) | method 1 (in-method guard) |
| 5 | AC 4(a) | User with only `access content`, unreferenced sponsor → 403, body does NOT contain `referenced by` | method 1 (in-method guard) |
| 6 | AC 4(b) | Other bundle: coupon node delete by user without `delete any coupon content` → 403, body does NOT contain `referenced by` AND contains core's `You are not authorized to access this page.` | method 2 (in-method guard) |

**Tier:** Kernel for everything — the observable is the HTTP response of the full
request pipeline; Unit cannot reach it, Functional is banned for worktree runs.

### Notes F must know

- **The 200-form markers are empirical.** In-kernel the delete confirm form renders
  with `class="node-sponsor-delete-form node-confirm-form confirmation"` and an
  `<input type="submit" value="Delete">`. The form's `action` attribute is `/` in
  this environment (no request-URI context) — do not "fix" that; nothing asserts it.
- **`page` node type does not exist** under KernelTestBase (testing profile) — AC 4(b)
  uses the `coupon` bundle, as the task anticipated.
- **AC 4(a) must stay on the unreferenced sponsor.** `badcamp_sponsors_node_access()`
  is permission-independent: on a *referenced* sponsor even a permission-less user's
  forbidden carries the coupon reason (OR-propagation keeps the forbidden operand's
  reason). On the unreferenced sponsor the hook is neutral and the reason lands empty
  — which is why the guard uses it. This is exactly A's finding-4 trace.
- **Escaping (AC 5) is asserted as the exact string.** The reason is built only from
  the bundle name (fixed `sponsor`/`campaign`) and an integer count — no user input
  can ever enter it, so escaped == raw for every possible message. The exact-string
  assertion therefore IS the escaped-form assertion; the *mechanism* (`#plain_text`,
  not `#markup`) is structural and left pinned for S's inspection per A's finding 3.

## RED confirmation

Command (from the worktree):

```
./scripts/run-tests.sh web/modules/custom/badcamp_sponsors/tests/src/Kernel/DeleteRefusalMessageTest.php
```

Output tail:

```
There were 2 failures:
1) Drupal\Tests\badcamp_sponsors\Kernel\DeleteRefusalMessageTest::testRefusedSponsorDeleteRendersCouponCountReason
Failed asserting that '<!DOCTYPE html>\n … <h1>Access denied</h1>\n\nYou are not authorized to access this page.\n …' [ASCII](length: 1891) contains "This sponsor is referenced by 2 coupon(s), including coupons in trash." [ASCII](length: 70).
/var/www/html/.claude/worktrees/0004-delete-refusal-message/web/modules/custom/badcamp_sponsors/tests/src/Kernel/DeleteRefusalMessageTest.php:169
2) Drupal\Tests\badcamp_sponsors\Kernel\DeleteRefusalMessageTest::testRefusedCampaignDeleteRendersCouponCountReason
Failed asserting that '<!DOCTYPE html>\n … You are not authorized to access this page.\n …' [ASCII](length: 1891) contains "This campaign is referenced by 2 coupon(s), including coupons in trash." [ASCII](length: 71).
/var/www/html/.claude/worktrees/0004-delete-refusal-message/web/modules/custom/badcamp_sponsors/tests/src/Kernel/DeleteRefusalMessageTest.php:232
FAILURES!
Tests: 2, Assertions: 18, Failures: 2.
```

**Both methods fail at the message assertion and nothing earlier**: each method's
first assertion (`assertSame(403, …)`) passes — the refusal itself already works
today — and the failure is precisely "the generic 403 page does not contain the
reason", i.e. the feature is absent. The dumped haystack in each failure is the
current stark-themed generic 403 page (`<h1>Access denied</h1>` + `You are not
authorized to access this page.`), proving the whole in-kernel request/exception/
theme pipeline renders correctly — the RED is by assertion, not a setup error.

**Guard validation.** Because PHPUnit aborts a method at its first failed
assertion, the later in-method guards (200 form, generic 403s, cache tag) do not
execute in the RED run. I validated every one of them separately with a temporary
scratch test (same setUp, guards only): `OK (1 test, 24 assertions)`. The scratch
file was then deleted; the two shipped methods are the only new tests.

Full suite (command `./scripts/run-tests.sh`, no args) — pre-existing GREEN, new RED:

```
FAILURES!
Tests: 57, Assertions: 574, Failures: 2.
```

Baseline 55 tests / 556 assertions + exactly the 2 new tests (18 completed
assertions): **all 55 pre-existing tests pass; the only failures are the 2 new
feature tests.**

## phpcs

`vendor/bin/phpcs --standard=Drupal,DrupalPractice` (via `ddev exec --dir` from the
primary checkout, per the harness rules) on the new file: **exit 0, no output.**

## Kernel-test dance settled (caveats hit and resolved)

1. **Theme required.** The current 403 body arrives via
   `DefaultExceptionHtmlSubscriber` (-128) → `/system/403` subrequest →
   `HtmlRenderer`, which needs an active theme. Fixed in setUp:
   `theme_installer->install(['stark'])` + `system.theme:default = 'stark'`
   (stark renders through the `system` module's default templates; nothing extra
   to install).
2. **Router table.** `router.builder->rebuild()` after module install (A's
   verified caveat) — done once at the end of setUp.
3. **Account.** `current_user->setAccount($user)` before each `handle()` (A's
   AccountSwitcherTest-derived dance); a `requestDeleteForm()` helper owns it so no
   request can forget it.
4. **Fixtures.** `setUp` mirrors `DeleteBlockingTest`'s proven shape verbatim
   (trash.settings before node schema, module_installer, user 1 first,
   createSponsor/createCampaign/createCoupon/createCouponFixtures) — per-file
   helper duplication is the run-0001-accepted pattern (survey's reuse map).
5. First run in this worktree did the `composer install` (about a minute), as the
   harness notes.

## Files F may create/modify (the implementation contract, per the A-approved brief)

1. **NEW** `web/modules/custom/badcamp_sponsors/src/DeleteRefusalReason.php` — the
   shared reason builder: the single `sprintf('This %s is referenced by %d
   coupon(s), including coupons in trash.', …)` site.
2. **NEW** `web/modules/custom/badcamp_sponsors/src/EventSubscriber/DeleteRefusalSubscriber.php`
   — `kernel.exception` subscriber, priority **0** (window −128 < p < 50), scoped to
   route `entity.node.delete_form` + `CacheableAccessDeniedHttpException` + the
   reason matching the builder's output for the node's bundle; response per
   `FinalExceptionSubscriber::on4xx` (`#type => 'page'`, title `t('Access denied')`,
   reason as `#plain_text`, `renderRoot`, `CacheableResponse`,
   `addCacheableDependency($exception)` + merged headers).
3. **MODIFY** `web/modules/custom/badcamp_sponsors/badcamp_sponsors.services.yml` —
   the subscriber service, tagged `event_subscriber` with priority 0.
4. **MODIFY** `web/modules/custom/badcamp_sponsors/badcamp_sponsors.module:48-60` —
   route the reason through the builder; **wording byte-identical** (frozen by the
   issue). No second counter, no `hook_theme`, no wording change.

## Ready for F

**Confirmed: RED is valid.** Both new tests fail because the reason is not rendered
(feature absent), not for any setup reason; all 55 pre-existing tests pass; phpcs
clean. F may implement against `DeleteRefusalMessageTest`.
