## Brief Review (Round 2)

### BLOCK finding responses

**[B-1] ACCEPTED** — AC 8 now names concrete, observable assertions per operation: creation with a trashed-matching triple saves without violation; coupon `view`/`update`/`delete` remain allowed for a permitted account; sponsor/campaign `delete` remains counter-governed with the existing `DeleteBlockingTest` as the green gate. "Unchanged" is no longer asserted in the abstract, and each claim maps to a specific operation or named existing test.

**[B-2] ACCEPTED** — AC 2 now explicitly requires the same `restore node entities` account to be proven able to restore a non-duplicate coupon. This isolates the forbidden result on the duplicate to the finder logic rather than a missing permission. AC 3's allowed cases serve as the positive control, which is a clean and sufficient design.

### Needs-verification findings

**NV-1 / NV-2 (live-only mechanism)** — **RESOLVED**. The `survey.md` citation to `TrashEntityHooks::entityQueryAlter()` (with file/line reference and explanation of both the explicit-`deleted`-skip and default-context `notExists('deleted')` behavior) establishes the live-only mechanism. The `CouponCounter` precedent also supports the pattern.

**NV-3 (finder implementation not shown)** — **ACCEPTED AS DEFERRED**. This is properly a diff-gate concern, not a brief-gate blocker. The finder contract is specified with sufficient precision (normalize, single query, int cast, ascending sort, exclude id, live-only) that the diff gate can verify it.

**NV-4 (locator style open point)** — **ACCEPTED AS DELIBERATE**. The brief explicitly records this as an open point for the architecture reviewer, requires the decision in `handoff-A.md`, and assigns the diff gate to re-check for circularity. This is an appropriate deferral.

**NV-5 (cacheability test must assert metadata)** — **RESOLVED**. AC 5 now requires `getCacheTags()` / `getCacheContexts()` assertions on the returned `AccessResult`, not inference.

### WARN findings

**W-1 (unguarded restore paths)** — **ACCEPTED**. This is an issue-level scope decision ("Out of scope" note), not a brief omission. The brief's Non-goals now require explicit PR-body documentation of the limitation.

**W-2 (missing-field boundary untested)** — **RESOLVED**. AC 3 now includes the fourth case: trashed coupon missing one of code/sponsor/campaign → `neutral` → allowed.

### NIT findings

**NIT-1 (class file path)** — **RESOLVED**. Direction now states the full path `web/modules/custom/badcamp_sponsors/src/CouponDuplicateFinder.php`.

**NIT-2 (RED recording)** — **RESOLVED**. AC 7 now specifies `handoff-T-red.md` with the failing run and the plugin-owned RED verdict at t-red → implement.

---

### Verdict

**PASS** — all BLOCK findings accepted; implementation may proceed.