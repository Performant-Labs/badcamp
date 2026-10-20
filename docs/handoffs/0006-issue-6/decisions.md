# Decision journal: 0006-issue-6 (issue #6)

## preflight — 2026-10-20T10:41Z
- **Phase ended:** PASS (18 checks, 0 failing) — cleared to brief.
- **Decided:** run with rigor `second-opinion`, uiSurface `false`, runner OpenCode (roles run as the
  session's live agent blocks), worktree `.claude/worktrees/0006-issue-6` on branch
  `issue-0006-issue-6` (branched at 0a1e109 = origin/main). Kind: feature (issue labels its own rigor
  `second-opinion`). Story: refuse restoring a trashed coupon that would duplicate a live coupon's
  (code, sponsor, campaign).
- **Assumed:** none.
- **Hedged:** one preflight stumble, mine, fixed before the pass. The first `pipeline_preflight` ran
  **in-process** at the primary checkout and failed the `run-state-server` row ("no server run-state
  store is installed … no server marker") — the plugin's in-process host does not inherit the
  self-hosted server env (`AFTERSIGHT_SERVER_URL`/`AFTERSIGHT_PROJECT`/`AFTERSIGHT_API_TOKEN`). The
  same preflight **delegated with `--dir`** (a fresh child process) inherits the env and passes:
  `run-state-server: https://localhost:3443 answers for project badcamp`. Decision: **every pipeline
  tool call in this run is `dir`-delegated to the worktree** (fresh child, correct env), never
  in-process. The self-hosted server is live (version 0.8.0) and reachable; the env is sourced from
  `~/.aftersight-selfhost/run-env.sh` by the shell, not by the plugin host.
- **Evidence:** `pipeline_preflight --dir .claude/worktrees/0006-issue-6 --issue 6 --rigor
  second-opinion --uiSurface false --slug issue-6` → `PRE-FLIGHT: PASS (0 failing of 18)`.
- **Noted (not part of the story):** the primary checkout carries uncommitted session setup —
  `opencode.json` (role models deepseek/deepseek-v4-pro), `.aftersight/pipeline-pin.json` (pin
  opencode-pipeline-v2.0.0, local checkout), `.aftersight/.gitignore` — that is **not** in the
  worktree (which is clean at main) and must never be staged with this story's files.

## survey-brief — 2026-10-20T10:4xZ
- **Phase ended:** survey (`survey.md`) and brief (`brief.md`) written; rigor `second-opinion`
  declared; advancing to the brief dual-review gate, then A.
- **Decided:** one story, one module touched (`badcamp_sponsors`). Mechanism direction (subject to A):
  (1) a new finder service `badcamp_sponsors.coupon_duplicate_finder`
  (`Drupal\badcamp_sponsors\CouponDuplicateFinder`) owns the single live-coupon query that today
  lives inline in `CouponCodeUniqueConstraintValidator`; (2) the validator delegates to it (AC 2
  single-query invariant); (3) `badcamp_sponsors_node_access()` gains a `restore` branch on the
  `coupon` bundle that returns `forbidden` (with reason containing the code, `node_list:coupon` cache
  tag, `cachePerPermissions()`) when the finder reports a live duplicate and the account holds
  `restore node entities`; the hook only forbids, never grants. Live-only semantics ride trash's
  query alter (no explicit `deleted` condition), the same pattern `CouponCounter` already uses.
- **Assumed:** the restore permission is trash's dynamic `restore node entities` (verified in
  `web/modules/contrib/trash/src/TrashPermissions.php`); trash 3.1.1's `TrashEntityAccess::entityAccess()`
  returns `allowed` for a deleted node when that permission is held, so this module's `forbidden`
  (OR-ed by the access handler) is the operative refusal.
- **Hedged:** none at this phase (open DI-vs-locator point for the validator is handed to A).
- **Evidence:** `survey.md` (file:line citations), `brief.md` (AC→test map).

## brief dual-review gate — 2026-10-20T10:5xZ
- **Phase ended:** round 1 **BLOCK** (2 blocking: B-1 "unchanged untestable", B-2 "no positive
  control"; plus 5 needs-verification, 2 warn, 2 nit) → amended `brief.md` (concrete AC 8 assertions;
  AC 2 positive control; AC 3 fourth missing-field case; cacheability asserted via
  `getCacheTags()`/`getCacheContexts()`; class file path; RED-recording note) and `survey.md` (new
  "live-only" section citing trash 3.1.1 `TrashEntityHooks::entityQueryAlter()` `notExists('deleted')`
  at `src/Hook/TrashEntityHooks.php:74-118`) → round 2 **PASS**.
- **Decided:** adopt all review amendments; the restore-side bypass paths (Drush bulk restore, storage
  restore API) are an issue-level out-of-scope decision and the PR body will document them.
- **Evidence:** `dual-review-brief.md` (round 2 PASS), `dual-review-brief-response.md` (operator
  response), amended `brief.md` / `survey.md`.

## architecture-review — 2026-10-20T11:0xZ
- **Phase ended:** A **PASS** (0 block, 2 warn + 3 nit) — brief amended, advancing to T(red).
- **Decided:** A resolved both open points — (1) the validator reaches the finder via the locator
  `\Drupal::service('badcamp_sponsors.coupon_duplicate_finder')` (matches the validator's existing
  `\Drupal::entityTypeManager()` and the hook's `\Drupal::service(...)`; no tagged-service promotion);
  (2) the restore reason is **inline** in the hook, no new reason-builder class (unlike
  `DeleteRefusalReason`, which exists for a two-consumer provenance round-trip). Folded both warn
  amendments into the brief: the neutral branch now carries
  `->addCacheTags(['node_list:coupon'])->cachePerPermissions()` (a cached "restore allowed" otherwise
  goes stale when a live duplicate is later created), and the finder query sets `accessCheck(FALSE)`.
  Also folded the nits (finder normalizes internally + reason embeds the normalized code; validator
  keeps emptiness check; PR body notes `bypass node access` and the bypass paths).
- **Assumed:** A's core trace (OR-combine via `AccessResult::orIf()` gives `forbidden` precedence;
  `NodeGrantDatabaseStorage::access()` returns neutral for `restore`; `node_list:coupon` invalidates
  on save/delete).
- **Hedged:** none.
- **Evidence:** `handoff-A.md` (6 verified-mechanism items + 2 open-point decisions + 5 findings,
  file:line citations read in-checkout); amended `brief.md` Direction + Non-goals.

## t-red — 2026-10-20
- **Phase ended:** two new Kernel test files authored (no existing test edited, no production code
  touched); RED confirmed — `scripts/run-tests.sh --testsuite kernel` → 56 tests, 4 errors + 3
  failures, all mine and all for the right reason.
- **Decided:** sit AC 1 at Kernel tier (not unit) — the finder's live-only guarantee only means
  anything against the real DB where trash's `entity_query_alter` (`notExists('deleted')`) is active;
  a unit test with a mocked query would prove nothing. Authored the finder test
  (`CouponDuplicateFinderTest`, 4 methods) and the restore-access test (`CouponRestoreAccessTest`,
  5 methods) exactly per the brief's AC→test map; regression guards (AC 5 triple-differs/missing-field,
  AC 8 unrelated-ops, and the AC 4/AC 6 positive controls) are marked as guards and pass before F.
- **Assumed:** trash's dynamic `restore node entities` permission is available to
  `drupalCreateUser()` at test time because `trash.settings.enabled_entity_types` is set to
  `['node' => []]` before the accounts are created (permission callback reads config at runtime —
  verified against `TrashManager::getEnabledEntityTypes()`).
- **Hedged:** the AC 6 "never its own duplicate" half rides the finder's live-only query (the entity
  being restored is trashed, hence excluded by `notExists('deleted')`), not an explicit exclude in the
  restore branch; pinned via the "both restorable with no live match" assertion rather than a direct
  finder call. The missing-field case uses an empty code (post-trim `''`) as the representative
  "missing field", which the hook treats identically to absent.
- **Evidence:** `handoff-T-red.md` (exact failing output); `phpcs --standard=Drupal,DrupalPractice`
  clean on both new files after fixing two docblock short-description wraps and one unused `$live`.

## t-green — 2026-10-20
- **Phase ended:** independent GREEN + Tier 2 verification passed. `--testsuite kernel` → **OK (56
  tests, 671 assertions)**; `--testsuite unit` → **OK (10 tests, 15 assertions)**. Both match F's
  reported numbers exactly (filter run 56/671; single-query grep 1 hit at `CouponDuplicateFinder.php:45`;
  phpcs exit 0). Cross-check clean, no discrepancy.
- **Decided:** GREEN confirmed; no test repair needed (the two Phase-4 test files pin behaviour, not
  implementation — each fails for the right reason if the feature is removed). Tier 2 clean: all 9 ACs
  backed (AC 2 by grep invariant, AC 3 by empty test-dir diff, AC 1/4/5/6/7/8 by the 9 new Kernel
  tests, AC 9 by the RED record + green run + phpcs).
- **Assumed:** the full kernel suite (no filter) and the unit suite were run in addition to F's filter
  run to rule out off-module regression; both green.
- **Hedged:** AC 5's missing-field case is representative only (missing code), consistent with the
  t-red hedge — the hook's guard is a single symmetric `||` short-circuit, so one case pins the
  branch; noted as advisory, not blocking. The hook's `exclude_id` in the restore branch is redundant
  (the entity is trashed, already excluded by `notExists('deleted')`) but harmless.
- **Evidence:** `handoff-T-green.md` (suite output, F cross-check table, AC→test table, Tier 1/2
  findings). `web/robots.txt` scaffold drift left untouched.
