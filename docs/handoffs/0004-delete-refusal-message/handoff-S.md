# Handoff-S: Phase spec-audit — Issue #4 coupon-count reason on refused delete

**Date:** 2026-10-17
**Branch:** `issue-0004-delete-refusal-message` (worktree `.claude/worktrees/0004-delete-refusal-message`, base caf4a7b)
**Issue:** #4
**Handoff-T-green reviewed:** `docs/handoffs/0004-delete-refusal-message/handoff-T-green.md`
**Handoff-A reviewed:** `handoff-A.md` (up-front plan) + `handoff-A-dup.md` (diff gate) — both PASS
**Handoff-F reviewed:** `handoff-F.md`
**Handoff-T-red reviewed:** `handoff-T-red.md` (RED evidence)
**Operator-facing report:** N/A — non-visual audit (uiSurface false, declared in brief); no rendered UI surface to diff.

## A precondition

Confirmed: A returned PASS on the up-front plan review and PASS on the a-dup diff gate. No blocking
architecture findings.

## T precondition

Confirmed: T-green verdict PASS, zero blocking issues. Fresh independent re-run by S (this phase):
`./scripts/run-tests.sh` → **OK (57 tests, 597 assertions)** — matches T-green exactly.

## Browser / visual-diff preconditions

N/A by declaration (uiSurface false, recorded in the brief). The story's observable is the HTTP
response body, pinned by Kernel tests; no interactive UI surface exists to walk or diff.

## AC-by-AC evidence map (the binding spec: issue #4 verbatim)

| AC | Requirement (gist) | Evidence (test file + method, or inspection) | Pins what the AC says? | Verdict |
|----|--------------------|----------------------------------------------|------------------------|---------|
| 1 | 403 + body message with correct count (trash-aware) and bundle name, user otherwise allowed | `DeleteRefusalMessageTest::testRefusedSponsorDeleteRendersCouponCountReason` (:155): sponsor_a referenced by 1 live + 1 trashed coupon, user holds `access content` + `delete any sponsor content` → `assertSame(403,…)` (:168) + body contains `This sponsor is referenced by 2 coupon(s), including coupons in trash.` (:169-173). `…testRefusedCampaignDeleteRendersCouponCountReason` (:235): campaign_a, 1 live + 1 extra trashed coupon, `delete any campaign content` → 403 + `This campaign is referenced by 2 coupon(s)…` (:251-256). | Yes. Status code asserted with `assertSame` (not just 4xx). Count pinned as literal **2** with exactly 1 live + 1 trashed referencing coupon — kills live-only counting (would say 1) and, in the campaign case (3 coupons exist in env), all-coupons counting (would say 3). Bundle name pinned per case. "Would otherwise be allowed" demonstrated in-method: the same `$manager` user gets **200** on the unreferenced twin (:185-190). Trash-inclusive counting itself pinned at the cheapest tier by `DeleteBlockingTest::testCouponCounterIsTrashAware` — correct tier, no duplication. | **PASS** |
| 2 | Count is the existing trash-aware counter (`badcamp_sponsors.coupon_counter`); no second counting implementation | Inspection (S's own greps): `grep 'CouponCounter\|coupon_counter\|countBy\|entityQuery\|loadByProperties\|getQuery' src/DeleteRefusalReason.php src/EventSubscriber/` → **zero hits**. The only `sprintf` in the module is the builder's (`DeleteRefusalReason.php:41`). Hook diff is +3/−1 (use import + `setReason(DeleteRefusalReason::build($bundle, $count))`); the `$counter->countBySponsor/countByCampaign`, `$count > 0`, forbidden/neutral, and `node_list:coupon` lines are untouched context (verified in the diff). Subscriber takes only `@renderer`; reason only from `$exception->getMessage()` (:89); count never re-derived — it rides in the matched message. | Yes — by construction there is no second counter and no counting code path in the new surface. | **PASS** |
| 3 | Unreferenced sponsor/campaign unaffected: delete form still loads (200) for permitted user | Test 1 (:184-197): unreferenced sponsor_b + permitted user → 200 + `node-sponsor-delete-form` + `value="Delete"` (real form, not just any 200). Test 2 (:275-292): fresh unreferenced campaign → 200 + `node-campaign-delete-form` + `value="Delete"`. | Yes, both bundles, positive form markers. (Minor note: brief suggested "live-coupon-moved / trashed-hard-deleted" fixture shapes; delivered guards use never-referenced + fresh-unreferenced. At the response tier these are the same state — count==0 → hook neutral → 200 — and the paths to zero are pinned at the API tier by `DeleteBlockingTest`. Advisory only, below.) | **PASS** |
| 4 | Other 403s unchanged: (a) no-delete-permission user on unreferenced sponsor; (b) non-sponsor/campaign node → generic 403 | (a) Test 1 (:199-209): user with only `access content`, unreferenced sponsor_b → 403 + body does NOT contain `referenced by`. Strengthened (:211-229, T-green's addition): user with **no** permissions (not even `access content`) → 403 + generic marker `You are not authorized to access this page.` present + node core's foreign non-empty reason `The 'access content' permission is required.` NOT rendered. (b) Test 2 (:258-273): coupon-bundle delete by unauthorized user → 403 + no `referenced by` + positive generic marker. | Yes. The (a) case's "unchanged generic" is pinned by absence of our string for that user, plus a positive generic-marker pin on the *same no-op path* (both users hit the identical early-returns at subscriber :90-91 and core's -128 fallback; I verified the foreign reason exists in core at `NodeAccessControlHandler.php:99-100`). The provenance guard empirically kills the non-emptiness-scope mutant — the strongest possible pin of "other 403s unaffected". 4(b) uses the `coupon` bundle as representative non-sponsor/campaign node (`page` does not exist in the kernel env — T-red documented); proportionate. | **PASS** |
| 5 | Message HTML-escaped; response keeps `node_list:coupon` so it updates when coupons change | Cache tag: test 1 (:177-182) — `instanceof CacheableResponseInterface` + `assertContains('node_list:coupon', getCacheableMetadata()->getCacheTags())` (executed; kills both dropping the hook's tag and dropping the subscriber's `addCacheableDependency`). Escaping: **structural, verified by S in this checkout** — subscriber :102 `'content' => ['#plain_text' => $reason]`; core `Renderer.php:879-880`: `#plain_text` → `Markup::create(Html::escape(...))` — the escaped-verbatim contract (read directly, not via handoff chain). The test asserts the exact message in the body; the message's inputs are a closed set (bundle ∈ {sponsor, campaign} — enforced end-to-end by the hook's `in_array` AND `matches()`; count is an int), so escaped == raw for every producible string and the exact-string assertion IS the escaped-form assertion. | Yes. No HTML-special character can ever enter the string, and the mechanism escapes verbatim regardless. Satisfies AC 5's first half by construction. | **PASS** |
| 6 | Test-first: Kernel test in `tests/src/Kernel` covering ACs 1-4 fails before, passes after; existing tests keep passing | RED: `handoff-T-red.md` — both methods failed pre-implementation at the message assertion *after* their `assertSame(403,…)` passed (failure = "generic 403 body lacks the reason" — the feature absent, not a setup error; dumped haystack was the rendered generic page). Full suite pre-change: 57 tests / 574 assertions / 2 failures = exactly the 2 new tests; baseline 55/556 all green. GREEN: plugin-confirmed + T-green independent run + **S's fresh re-run this phase: OK (57 tests, 597 assertions)** (597 = T-green's two Tier-2 guards added). File lives at `web/modules/custom/badcamp_sponsors/tests/src/Kernel/DeleteRefusalMessageTest.php`, Kernel tier. | Yes. Covers AC 1 (both bundles), 3 (both bundles), 4 (both enumerated cases + provenance), 5 (cache tag executed / escaping structural). All 55 pre-existing tests still pass in every run. | **PASS** |

## Items routed to S (from handoff-T-green) — judgments

**(i) Bare page with no `<h1>` vs "themed 403 page".** SATISFIED for the binding AC. AC 1 requires
"HTTP 403 and the response body contains the message" — met. The survey's narrative phrase "themed
403 page" is met in the load-bearing sense: the reason is rendered through the theme system
(`#type => 'page'` → active theme's `page.html.twig` via `renderRoot`) — the identical rendering
happens in test and production (no kernel-environment dependency). The bare shape (no `<html>`
envelope, no site chrome/regions, no `<h1>`) was the *explicitly approved* plan choice (A finding 5
option i; amended brief: "No `<html>` envelope needed; no `hook_theme`, no template"). Core's own
4xx precedent (`FinalExceptionSubscriber::on4xx` → `renderBarePage`) produces the same shape.
Operator awareness item, not a defect: core's *generic* 403 in production gets site chrome + an
`<h1>` from the `/system/403` subrequest's full page pipeline, so an editor seeing both pages will
notice ours is plainer. Full-chrome parity would be a deliberate follow-up (a `page_title` child or
the subrequest approach A declined) — see Advisory 1.

**(ii) `#plain_text` vs `#markup` — AC 5 "HTML-escaped".** SATISFIED, verified in this checkout's
core source: `Renderer.php:879-880` applies `Html::escape()` to `#plain_text` (escaped-verbatim;
`#markup` would instead run `Xss::filterAdmin` or trust a `MarkupInterface` object). Subscriber
:102 uses `#plain_text` exclusively; there is no `#markup` anywhere in the new surface. With the
closed input set the two mechanisms produce identical output for every producible message, so an
executed escaping-differentiating assertion is impossible without changing production code — the
exact-string body assertion plus the structural inspection is the strongest available pin, and it
holds.

**(iii) `text/html` rendered page vs `FinalExceptionSubscriber`'s text/plain.** INTENT CONSISTENT.
Core's `on4xx` text/plain body is the non-HTML last-resort surface; the issue's audience is a human
editor in a browser who today sees core's HTML generic page. The approved plan adopted on4xx's
*response-construction* shape (`CacheableResponse` + `addCacheableDependency($exception)` +
headers merge), not its content type — F built the HTML-page variant the plan named. `text/html`
is correct for the end-user surface (and is `Response`'s default anyway; the explicit header at
:111 is harmless self-documentation, as A-dup noted).

**(iv) `matches()` regex tolerances.** CONFIRMED HARMLESS. I re-derived the built pattern myself:
`preg_quote` leaves `%s`/`%d` intact (it does not escape `%`), substitution yields
`^This (sponsor|campaign) is referenced by \d+ coupon\(s\), including coupons in trash\.$` —
anchored, correct. Tolerances: PCRE `$` without `/D` admits one trailing newline; `\d+` admits
leading zeros. Both require a foreign producer of a byte-identical-plus string through a path that
does not exist in this codebase (the only reason producer is the builder; `sprintf %d` never emits
leading zeros; the hook's reason never carries a newline). Even if hit, the render goes through
`#plain_text` — no injection surface. Agree with T and A-dup: record, do NOT "fix" (any tightening
either restates the frozen wording in a second place or changes matching semantics for zero gain).

## Spec compliance summary

- The implementation matches the issue: the one behavior change is that the module's own
  delete-refusal 403 body now carries the existing reason. Nothing else about the request pipeline
  changed.
- Data model, permissions, config: untouched (no config schema, no new permissions, no composer
  changes — verified in the diff).
- Naming consistent with module idioms (`CouponTitleBuilder`-style final class + static methods;
  snake_case service id mirroring `badcamp_sponsors.coupon_counter`).

## Quality audit

| Area | Result | Notes |
|------|--------|-------|
| API consistency | N/A | No new routes/endpoints/services API; one internal service registered per stock pattern. |
| Error handling | PASS | The 403 is the error surface; status code from the exception (`getStatusCode()`), headers merged, cacheability preserved. Every non-matching 403 falls through to core unchanged (three ordered early-returns, plain `return;`). |
| UI/UX match to spec | PASS | Per binding AC (body contains the message). Bare-page shape is the approved plan choice — see Advisory 1. |
| Accessibility | N/A | No interactive UI added; the response is a static page fragment. (No `<h1>` — see routed item (i) / Advisory 1.) |
| Architecture gate | PASS | A PASS + A-dup PASS; implementation matches the approved plan exactly (priority 0 in `getSubscribedEvents()`, provenance scope, `#plain_text`, on4xx response shape, no hook_theme). No conflict between quality findings and A's verdict. |
| Code organization | PASS | Two small focused classes; follows module idioms (`declare(strict_types=1)`, final, fully typed, docblocks). No dead code, no TODOs, no leftover instrumentation (A-dup grepped; S read both files in full). |
| Security | PASS | `#plain_text` escaping; closed input set; reason consumed only from the exception; no user input can reach the body un-derivative. |
| Performance | PASS | Subscriber cost on non-matching exceptions: one `instanceof` + one route-name compare + one regex on a short string; `renderRoot` only on match. Negligible. |
| Visual regression | N/A | No UI surface (declared). |
| Naming consistency | PASS | `DeleteRefusalReason` / `DeleteRefusalSubscriber` / `delete_refusal_subscriber` / `DeleteRefusalMessageTest`. |
| Test quality (rubric §7) | PASS | 2 methods / 41 assertions, all HTTP-response-tier (behavior, not implementation — no mocks at all; real pipeline driven). Each guard maps to a named AC (labeled in comments); multi-guard methods are the module's established shape and share expensive fixtures — cheapest sufficient tier. No smells: no assertion-free/tautological tests, no unreachable outcomes (every guard executes — 41 assertions counted), no snapshot abuse, no coverage padding (T-green's two additions each kill a named mutant: the non-emptiness-scope mutant and the unpinned campaign-200 case). No "delete or merge" findings — splitting the in-method guards into per-AC methods would multiply fixture cost for zero signal. Proportionate to a 6-AC behavior story. |

## Scope check

Delivered = exactly the story. Diff comprises: the spec'd reason-builder (`DeleteRefusalReason`,
named in the amended brief — extend-not-drift), the new subscriber (the survey's sanctioned
new-file shape), the 2-line hook routing through the builder, the services.yml registration, and
the new Kernel test (+T-green's two in-file guards). Nothing beyond: no when-refused change
(count/threshold/forbidden/neutral all untouched context), wording byte-identical (S's own `od -c`
diff of HEAD's literal vs `PATTERN`: clean), no admin UI beyond the 403 response (no forms, no
routes, no controllers, no theme hooks). `web/robots.txt` is the declared pre-existing scaffold
drift, unstaged — out of the run (Advisory 4). No scope creep, no silent omissions.

## Verdict

**PASS** — all six acceptance criteria met as written; spec-compliant; suite independently
re-confirmed GREEN by S (57/597); no out-of-scope changes; no scope creep. Ready for O to stage
(by explicit path, excluding `web/robots.txt`) and open the PR.

## Advisory notes (non-blocking)

1. **Production page chrome (routed item i):** the custom 403 renders as the active theme's bare
   page content — no site chrome (regions/blocks) and no `<h1>Access denied</h1>`, unlike core's
   generic 403 which gets both from the `/system/403` subrequest's full page pipeline. This is the
   approved plan's explicit choice and satisfies the binding AC. If O wants visual parity with
   core's generic 403 (heading + chrome), that is a small deliberate follow-up — not rework for
   this run.
2. **AC 3 fixture shapes:** brief suggested moved-coupon / hard-deleted-coupon fixtures; delivered
   guards use never-referenced/fresh-unreferenced nodes. Response-tier-equivalent (count==0 is
   count==0); the paths to zero are pinned at the API tier by `DeleteBlockingTest`. No action
   needed.
3. **AC 4(a) positive marker:** the exact 4(a) user's guard asserts absence of our string but not
   the generic marker; the marker is positively pinned on the same subscriber no-op path by the
   no-permissions guard (:220-224) and the 4(b) guard. Mechanistically airtight as-is; a stricter
   future suite could add `assertStringContainsString('You are not authorized…')` to the 4(a)
   guard. Optional.
4. **`web/robots.txt`** must stay excluded from the PR (staging by explicit path only) — pre-existing
   scaffold drift, declared out of run by brief/survey and reaffirmed by A-dup finding 3.
5. **A-dup's XS follow-up stands:** the `['sponsor','campaign']` literal is duplicated between the
   hook's `in_array()` and `DeleteRefusalReason::BUNDLES`; a third refused bundle would need two
   edits. Suggested follow-up: `DeleteRefusalReason::appliesToBundle()` used by the hook. Not
   required this run.
