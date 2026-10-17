# Handoff-T-green: Phase 6 — Verify GREEN + Tier 2 (0004-delete-refusal-message)

**Date:** 2026-10-17
**Branch:** `issue-0004-delete-refusal-message` (worktree `.claude/worktrees/0004-delete-refusal-message`)
**Issue:** #4
**Handoff-F reviewed:** `docs/handoffs/0004-delete-refusal-message/handoff-F.md`
**Handoff-T-red:** `docs/handoffs/0004-delete-refusal-message/handoff-T-red.md`

## Verdict: **PASS** — no rework needed. Two tests ADDED to my file during Tier 2 (coverage gaps closed); both GREEN against F's code unchanged.

## GREEN confirmation

Plugin's T-green run confirmed the Phase-4 suite GREEN. I re-ran independently, then
**strengthened the suite** (see "Coverage-gap analysis" — two real gaps found and closed
inside my own file, which I own) and re-ran again:

```
$ ./scripts/run-tests.sh web/modules/custom/badcamp_sponsors/tests/src/Kernel/DeleteRefusalMessageTest.php
OK (2 tests, 41 assertions)          # was 34 before my two Tier-2 guards; all 7 new assertions pass

$ ./scripts/run-tests.sh             # full suite
OK (57 tests, 597 assertions)        # = baseline 55/556 + my 2 tests @ 41; F reported 590 with the 34-assertion suite — delta is exactly my additions
```

Cross-check vs handoff-F: F's local runs (2/34, 57/590) match the plugin GREEN; my numbers
differ only by the assertions I added in this phase. No discrepancy.

## Tier 1 results

| Check | Command | Expected | Actual | PASS |
|---|---|---|---|---|
| Target file | `./scripts/run-tests.sh <file>` | GREEN | `OK (2 tests, 41 assertions)` | ✅ |
| Full suite | `./scripts/run-tests.sh` | 57 GREEN | `OK (57 tests, 597 assertions)` | ✅ |
| phpcs (my edited test file) | `vendor/bin/phpcs --standard=Drupal,DrupalPractice` via `ddev exec --dir <worktree>` | exit 0 | exit 0, no output | ✅ |
| F's phpcs claim (4 prod files) | re-read of F's run + my read of all four files | exit 0 | F reported exit 0; my reading of the files finds no smells (see Tier 2) | ✅ |

(Build/lint of production code is F's Tier 1; nothing in my verification contradicts it.
`web/robots.txt` shows modified in the worktree — F flagged it as pre-existing composer
scaffold drift, `git diff` confirms it is scaffold-only and F did not stage it. Not a blocker;
must not be committed.)

## Tier 2 results

### 1. Contract audit (read F's full diff + both new files against handoff-T-red)

| Contract item | Verdict | Evidence |
|---|---|---|
| Reason builder, single `sprintf` site | ✅ | `grep -rn sprintf badcamp_sponsors/ --include='*.php'` → exactly one hit: `DeleteRefusalReason.php:41`. Hook now calls `DeleteRefusalReason::build($bundle, $count)`; hook diff is exactly 2 lines (`use` + `setReason`), forbidden/neutral/cache-tag lines untouched. |
| Wording byte-identical | ✅ | Mechanical compare: extracted the quoted pattern from `git show HEAD:…module` and from `PATTERN`, `od -c` both, `diff` clean (my first grep-diff showed only my own sed artifact — hence the od re-check). |
| Reason consumed only from the exception | ✅ | Subscriber: `$reason = (string) $exception->getMessage()`; no other message source; response cacheability solely `addCacheableDependency($exception)`. |
| No counter in subscriber (AC 2) | ✅ | `grep CouponCounter\|coupon_counter src/EventSubscriber/` → none. Constructor takes only `@renderer`. |
| No-ops on non-matching 403s | ✅ | Three ordered early-returns: not `CacheableAccessDeniedHttpException` / route ≠ `entity.node.delete_form` / `!DeleteRefusalReason::matches($reason)` — plain returns, no response set. **Empirically confirmed** by my new provenance guard (below): a non-empty foreign reason on the exact route stays generic. |
| Response shape per contract | ✅ | `['#type' => 'page', '#title' => t('Access denied'), 'content' => ['#plain_text' => $reason]]`, `renderRoot`, `CacheableResponse`, 403 code, merged headers, `setResponse`. Deviations (renderer DI instead of `\Drupal::service`; no `<h1>` in the bare page) are F-declared, contract-compatible, and recorded below for S. |
| Service registration | ✅ | `badcamp_sponsors.delete_refusal_subscriber` + `event_subscriber` tag; priority correctly in `getSubscribedEvents()` (see #3). |

`matches()` logic re-derived by hand: `preg_quote(PATTERN)` leaves `%s`/`%d` intact (preg_quote
does not escape `%`), then substitution yields `^This sponsor is referenced by \d+ coupon\(s\), including coupons in trash\.$` (and the campaign twin) — matches any count, either bundle;
rejects empty strings, trash's `'Entity is deleted'`, node's `'access content'` string, arbitrary
foreign text. F's debug note (the `%d`-casts-`'%d'`-to-`0` bug in their first version) is
consistent with what the corrected code avoids. Two regex subtleties noted as advisories, not
defects (below).

### 2. Mutation-style probing (per AC) — and the two gaps it found

Worked mutation-by-mutation ("if F's code were subtly wrong, would MY test catch it?"):

- **(a) Count correctness** — ✅ already pinned. Sponsor case: 1 live + 1 trashed coupon, body
  asserts literal `referenced by 2 coupon(s)` — kills live-only counting (would say 1) and
  any wording drift. Campaign case: 1 live + 1 trashed = 2 asserted, and the environment holds
  3 coupons total at that point — also kills "counted all coupons" (would say 3). Counting
  itself is pinned at the cheapest tier by `DeleteBlockingTest::testCouponCounterIsTrashAware`
  (2 = live+trashed, per-bundle) — no duplication.
- **(b) Cache tag `node_list:coupon` on the 403 metadata** — ✅ pinned by executed assertions
  (`instanceof CacheableResponseInterface` + tag in `getCacheableMetadata()->getCacheTags()`).
  Kill: dropping the hook's `addCacheTags` or the subscriber's `addCacheableDependency` both
  fail it (nothing else in the pipeline contributes that tag).
- **(c) 200 path unreferenced sponsor AND campaign** — ❌ **REAL GAP → FIXED.** Sponsor-200 was
  pinned; **unreferenced-campaign-200 was pinned nowhere in the entire suite** (no test, any
  tier, ever creates an unreferenced campaign — `DeleteBlockingTest`'s two campaigns are both
  referenced; its allowed-assertions are sponsor-only). Added an in-method guard to test 2:
  fresh unreferenced campaign + `delete any campaign content` user → 200 +
  `node-campaign-delete-form` + `value="Delete"`. GREEN against F's code.
- **(d) AC 4(a) no-permission user does NOT get "referenced by"** — the assertion existed, but
  probing showed it could not kill the **non-emptiness-scope mutant**: a
  permission-less-but-`access content` user's neutral result carries an *empty* reason
  (`AccessAwareRouter:114` passes `getReason()`; neutral has none), so an F scoped by
  non-emptiness would also no-op there. ❌ **REAL GAP → FIXED.** Read core
  (`NodeAccessControlHandler:99-101`): a user *without* `access content` gets forbidden with
  the **non-empty** reason `The 'access content' permission is required.` on the very same
  `entity.node.delete_form` route — the one in-environment foreign reason that reaches the
  subscriber. `user.role.authenticated.yml` ships `permissions: {}`, so `drupalCreateUser([])`
  is that user. Added guard: 403 + body contains core's `You are not authorized to access this
  page.` + does NOT contain the foreign reason. GREEN against F's code → the provenance scope
  (A finding 2) is now **empirically** pinned, not just read.
- **(e) AC 4(b) other-bundle generic 403** — ✅ pinned (coupon delete → 403, no `referenced by`,
  positive `You are not authorized…` marker). Positive marker matters: it kills a mutant that
  renders *some* wrong body rather than merely checking ours is absent.
- **(f) `#plain_text` escaping** — structural fact confirmed by reading (subscriber line 102:
  `'content' => ['#plain_text' => $reason]`, never `#markup`). Not assertable at the response
  tier: the reason is built only from a fixed bundle name + int, so escaped == raw for every
  possible message — identical output under both mechanisms. **Handed to S** (A finding 3) as a
  read-inspection item, per the RED handoff's note.

### 3. Priority/order verification

- `DeleteRefusalSubscriber::getSubscribedEvents()` → `KernelEvents::EXCEPTION => [['onException', 0]]`
  — priority **in `getSubscribedEvents()`**, which is where Drupal actually reads event
  priorities for `event_subscriber` services; the services.yml tag correctly carries no
  priority (a tag priority would be ignored — F's placement note is right).
- Core read in this checkout: `ExceptionLoggingSubscriber::getSubscribedEvents()` = **50**
  (`['onException', 50]`); `DefaultExceptionHtmlSubscriber` inherits
  `HttpExceptionSubscriberBase::getSubscribedEvents()` → `getPriority()` = **-128**.
  So 50 (logs) → **0 (ours, sets response)** → -128 (generic HTML page). Our `setResponse()`
  preempts -128, and logging at 50 has already run — core's access-denied watchdog entry
  survives. (dblog isn't installed in the kernel environment, so this is by
  priority-arithmetic + code reading, not a db query — noted per the checklist.)
- Route-attribute timing verified: `AccessAwareRouter::matchRequest()` (lines 88-94) adds the
  matched route parameters to the request **before** `checkAccess()` throws, so the subscriber's
  `ROUTE_NAME` read is populated on the master request. ✓

### 4. Suite + phpcs re-run

See Tier 1 table: full suite `OK (57 tests, 597 assertions)`; phpcs on my edited file exit 0.

## Acceptance criteria status

| AC | Status | Backed by |
|---|---|---|
| AC 1 sponsor 403 + reason w/ count | PASS | `testRefusedSponsorDeleteRendersCouponCountReason` (403 + exact `This sponsor is referenced by 2 coupon(s)…`) |
| AC 1 campaign 403 + own bundle/count | PASS | `testRefusedCampaignDeleteRendersCouponCountReason` (403 + `This campaign is referenced by 2 coupon(s)…`) |
| AC 2 reason from exception, no re-count | PASS | Code read (only `getMessage()`; no counter in subscriber) + the count-in-body is the hook's own, cross-pinned by `DeleteBlockingTest` |
| AC 3 unreferenced delete forms keep working | PASS | Sponsor 200 guard (test 1) + **new** campaign 200 guard (test 2) |
| AC 4(a) permission-less → generic 403 | PASS | Test 1 guard, now incl. positive generic marker for the empty-reason user + **new** non-empty-foreign-reason (no-`access content`) guard |
| AC 4(b) other bundle → generic 403 | PASS | Test 2 coupon guard (absence + positive marker) |
| AC 5 cacheability rides the response | PASS | Test 1: `CacheableResponseInterface` + `node_list:coupon` in metadata tags |
| AC 6 full suite green | PASS | `OK (57 tests, 597 assertions)` |

## Tests added in this phase (my file only — no production change)

1. **Provenance guard** (test 1): user with no permissions (no `access content`) → 403 +
   core's generic marker present + node core's foreign reason NOT rendered. Kills the
   non-emptiness-scope mutant; empirically proves A finding 2's scoping.
2. **Campaign-200 guard** (test 2): unreferenced campaign + permitted user → 200 +
   `node-campaign-delete-form` + `value="Delete"`. Closes the only unpinned AC-3 bundle.

Net: file 34 → 41 assertions, still 2 methods (in-method guards, cheapest sufficient tier,
no duplication with `DeleteBlockingTest` — that file pins access results; this one pins HTTP
responses).

## Blocking issues

**None.**

## Advisory notes (for S / A-dup)

- **For S (visual/structural, not assertable headless):** the custom 403 renders through
  `page.html.twig` as a bare page — it has **no `<h1>Access denied</h1>`** (core's generic 403
  gets its h1 from the `page_title` block in the full pipeline). `#title => t('Access denied')`
  is on the render array but stark's bare page never prints it. If the h1 matters UX-wise it's
  a deliberate follow-up, not a defect vs the contract (which pinned the array shape). S should
  eyeball the real themed page.
- **For S:** `#plain_text` (escaped-verbatim) confirmed by reading — the mechanism A finding 3
  wanted; response-tier output is identical to `#markup` for all possible inputs (fixed bundle
  name + int), so this stays an inspection item.
- **`matches()` regex tolerances** (harmless, by reading): (i) `$` without `/D` also matches a
  single trailing newline (`reason . "\n"` passes `matches()`); (ii) `\d+` accepts leading
  zeros (`02`). Both require a caller to have built a near-identical string via a path that
  doesn't exist in this codebase, and the page renders through `#plain_text` regardless — no
  injection surface. Recording for completeness; no test warranted (would pin regex trivia, not
  behavior).
- **`FinalExceptionSubscriber::on4xx` divergence, declared:** core's own on4xx emits
  `text/plain` with the raw message; the contract (A finding 5 option i) specified the
  rendered-HTML page variant, which F built (`text/html; charset=UTF-8`). Consistent with the
  approved plan; S should confirm the HTML page is the desired end-user surface.
- Renderer constructor-injection instead of `\Drupal::service('renderer')` — F-declared
  deviation; same `renderRoot` mechanism, DI-clean for a registered service. Accepted.
- `web/robots.txt` drift is pre-existing scaffold noise — **must not be committed** with this
  branch's PR.
