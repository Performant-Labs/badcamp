# Handoff-A-dup: Phase 7 — Anti-duplication gate (0004-delete-refusal-message)

**Date:** 2026-10-17
**Branch:** `issue-0004-delete-refusal-message` (base caf4a7b; all changes uncommitted in the worktree)
**Diff base:** caf4a7b   **Diff head:** working tree (modified: `badcamp_sponsors.module` +3/-1, `badcamp_sponsors.services.yml` +6; staged+unstaged test file 295 lines; untracked `src/DeleteRefusalReason.php` 76, `src/EventSubscriber/DeleteRefusalSubscriber.php` 117)
**Reuse map:** `docs/handoffs/0004-delete-refusal-message/survey.md`
**Verdict:** PASS

## Summary

PASS. F extended exactly the objects the map and the amended brief named: the reason now has
one construction site (`DeleteRefusalReason::build()`, the spec'd refactor-to-extend) consumed
by BOTH the access hook and the new subscriber; the subscriber itself is the survey's sanctioned
new-file shape (first `event_subscriber` in the module, core stock pattern) and reads the reason
only from `$exception->getMessage()` and cacheability only from the exception — zero re-counting
(no `CouponCounter` reference anywhere in `src/EventSubscriber/` or the builder). No parallel
path exists: the single `sprintf` left in the module is the builder's own
(`grep -rn sprintf --include='*.php'` → exactly `DeleteRefusalReason.php:41`), the wording is
byte-identical to the pre-change literal (`od -c` diff of HEAD's string vs `PATTERN` is clean),
and `matches()` derives its regex from that same `PATTERN` constant rather than restating the
string. All six a-dup invariants verified (below). One warn: the two-element bundle literal is
duplicated between the hook's `in_array()` and the builder's `BUNDLES` — declared by F, degrades
gracefully, fix is an XS follow-up.

## Invariant verification (brief + handoff-A findings 1/2/3/5/6)

| # | Invariant | Verdict | Evidence |
|---|---|---|---|
| 1 | ONE reason construction site; hook routes through it; wording byte-identical | ✅ | `badcamp_sponsors.module:57` → `DeleteRefusalReason::build($bundle, $count)`; the only `sprintf` in the module is `DeleteRefusalReason.php:41` (`sprintf(self::PATTERN, …)`); `od -c` of HEAD's `'This %s is referenced by %d coupon(s), including coupons in trash.'` vs `PATTERN` (DeleteRefusalReason.php:27) — identical byte streams. |
| 2 | Reason only from `$exception->getMessage()`; cacheability only from the exception; never re-counts | ✅ | Subscriber `:89` (`$reason = (string) $exception->getMessage()`) and `:112` (`addCacheableDependency($exception)`) are the only sources; `grep 'CouponCounter\|coupon_counter\|countBy' src/EventSubscriber/ src/DeleteRefusalReason.php` → no hits. AC 2 clean. |
| 3 | Provenance scope: exception type + route + reason-matches-builder; every other 403 falls through | ✅ | Three ordered early-returns at `DeleteRefusalSubscriber.php:74` (`instanceof CacheableAccessDeniedHttpException`), `:80-82` (`ROUTE_NAME !== 'entity.node.delete_form'`), `:90` (`!DeleteRefusalReason::matches($reason)`) — plain returns, no response set, so core's -128 generic page wins. T-green additionally pinned this empirically (node core's non-empty foreign reason stays generic). |
| 4 | Priority 0 in `getSubscribedEvents()`; window −128 < p < 50 | ✅ | `DeleteRefusalSubscriber.php:62` — `KernelEvents::EXCEPTION => [['onException', 0]]`; services.yml tag carries no priority (correct: Drupal reads it from `getSubscribedEvents()`), so `ExceptionLoggingSubscriber` (50) still logs before `setResponse()` stops propagation. |
| 5 | `#plain_text`; `FinalExceptionSubscriber::on4xx` shape; no hook_theme/template/route/controller; no composer or config-schema change | ✅ | `:102` `'content' => ['#plain_text' => $reason]`; `:99-104` page array (`#type => 'page'`, `#title => t('Access denied')`) + `renderRoot`; `:111-114` `CacheableResponse` + status + headers-merge + `setResponse` — the on4xx precedent. `src/EventSubscriber/` contains exactly one file; the module diff is 2 lines (no hook_theme); no composer/config paths in the diff. |
| 6 | Diff hygiene: module + test + run handoffs only | ✅ | `git status --porcelain`: module, services.yml, test file, the two new src files, `docs/handoffs/0004-delete-refusal-message/`. `web/robots.txt` is the declared pre-existing composer-scaffold drift — out of the run, unstaged, must not be committed (info finding 3). |

## Findings

| # | Severity | File:line | Finding | Suggested fix |
|---|---|---|---|---|
| 1 | warn | `web/modules/custom/badcamp_sponsors/badcamp_sponsors.module:52` vs `src/DeleteRefusalReason.php:22` | Duplicated bundle literal: the hook's `in_array($bundle, ['sponsor', 'campaign'], TRUE)` and the builder's `private const BUNDLES = ['sponsor', 'campaign']` restate the same domain fact. F declared it (handoff-F §3: minimal diff; "different concern") — a reviewed decision, not drift, and both drift directions degrade safely (a bundle refused by the hook but absent from `BUNDLES` falls back to core's generic page; the converse is a dead pattern). Still, a third refused bundle would need two edits, and the class docblock already promises the class is the coupling point. | XS follow-up: make `BUNDLES` public (or add `DeleteRefusalReason::appliesToBundle(string $bundle): bool`) and use it in the hook's `in_array()`. Not required for this run. |
| 2 | info | `src/DeleteRefusalReason.php:59-74` | `matches()` regex tolerances (T-green advisories): `$` without `/D` also matches one trailing newline; `\d+` accepts leading zeros. Verified by my own re-derivation of the built pattern (`preg_quote(PATTERN)` leaves `%s`/`%d` intact; substitution yields `^This (sponsor|campaign) is referenced by \d+ coupon\(s\), including coupons in trash\.$`). Both require a foreign near-identical string built through a path that does not exist in this codebase, and the render goes through `#plain_text` regardless — no injection surface. The mechanism itself is sound single-source provenance: the regex is derived from the same `PATTERN` constant the hook builds with, so no second copy of the wording exists. Recording; no action. | None (do NOT "fix" to a prefix/marker — that would change the frozen wording or add a second construction site). |
| 3 | info | `web/robots.txt` | Worktree shows scaffold drift (removal of the `Sitemap:` line) — pre-existing from the worktree's `composer install`, explicitly out of the run per brief/survey; F declared it and did not stage it. | O must keep excluding it when staging the PR (`git add` by explicit path only). |
| 4 | info | `src/EventSubscriber/DeleteRefusalSubscriber.php:111` | Explicit `Content-Type: text/html; charset=UTF-8` duplicates `Response`'s constructor default — harmless, one line of self-documenting redundancy consistent with naming the response shape. | None (leave as-is). |

No duplication; extension is clean. No dead code or leftover instrumentation
(`grep -rniE 'var_dump\|print_r\|dpm\|kint\|file_put_contents\|error_log\|logger' src/` → no hits);
F's throw-instrumentation from the debug note was fully removed. Constructor injection of
`@renderer` matches the module's existing service idiom (`CouponCounter`'s constructor-args
shape in `badcamp_sponsors.services.yml`), and the services.yml entry mirrors the existing
style exactly.

## Notes for F

None — verdict is PASS. Finding 1 is a follow-up suggestion, not rework; the current shape is
a declared, contract-compatible minimal diff.
