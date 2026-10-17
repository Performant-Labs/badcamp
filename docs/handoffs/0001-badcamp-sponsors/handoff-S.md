# Handoff-S: Phase 8 - badcamp_sponsors spec audit

**Date:** 2026-10-17
**Branch:** issue-0001-badcamp-sponsors (worktree `.claude/worktrees/0001-badcamp-sponsors`)
**Issue:** #1 (Performant-Labs/badcamp)
**Diff audited:** `git diff c0a8dd9..8e85953 -- web/modules/custom composer.json composer.lock` (65 files, +6,441/−1; docs/handoffs = journal, excluded per instruction)
**Handoff-T reviewed:** docs/handoffs/0001-badcamp-sponsors/handoff-T-green.md (+ handoff-T-red.md)
**Handoff-A reviewed:** docs/handoffs/0001-badcamp-sponsors/handoff-A.md (up-front PASS) + handoff-A-dup.md (round 1 BLOCK → round 2 PASS after fold)
**Handoff-F reviewed:** docs/handoffs/0001-badcamp-sponsors/handoff-F.md (incl. deviations 1–7 and "Fold (a-dup round 2)")
**Operator-facing report:** N/A — non-visual audit (brief: `uiSurface: false`; state.json:166 records the U skip)

## A precondition

**Confirmed.** A's up-front plan review returned PASS (handoff-A.md:6, five `warn` findings all folded into
brief.md "Plan amendments" and survey.md before T/F). The a-dup gate BLOCKed in round 1
(handoff-A-dup.md:7), both blocks were folded exactly as prescribed and re-reviewed to **PASS**
(handoff-A-dup.md:76-112, "Round 2 — Verdict: PASS"). A's final state on both halves is PASS.

## T precondition

**Confirmed.** handoff-T-green.md:9 "Verdict: GREEN", final suite `OK (40 tests, 392 assertions)` exit 0
(three consecutive runs + post-bite reverts), "Blocking issues: None" (handoff-T-green.md:145-147).
T's six test repairs and two fold repairs each carry source proofs and no-weakening tables
(handoff-T-green.md:26-92, 165-217); 4/4 mutation spot-checks + 2 fold bite-checks bite.

## Visual-diff-tool precondition

**N/A.** The story has no visual surface (`uiSurface: false` in brief.md:3; the pages are stock admin
views). state.json:166-167 records `ui-walkthrough (N/A: uiSurface is false)` — the skip is recorded,
not silent. This is a non-visual audit: markdown handoff only, no HTML report required.

## Spec compliance

### Acceptance criteria (issue #1's ten boxes)

| AC | Requirement (issue) | Where verified | Real & sufficient? |
|---|---|---|---|
| 1 | `drush en` creates types, fields, 3 views (each +CSV), 2 pathauto patterns, role, menu links; `views_data_export` + `csv_serialization` enabled | `InstallTest` 7 methods: deps (`InstallTest.php:42-58`), types (`:63-68`), fields incl. ER target bundles (`:73-109`), views + exactly one `data_export` display each at `<path>/export.csv` (`:114-146`), patterns (`:151-173`), role exact 13-perm set (`:178-199`), menu links parent/children (`:204-216`); install is via `module_installer()->install()` — the `drush en` equivalent (`:36`). Composer: `composer.json` +1 line `drupal/views_data_export: ^1.10`; `composer.lock:5151` `views_data_export 1.10.0`, `:2150` `csv_serialization` | YES. Kernel GREEN (T-green); real config round-tripped through the real installer (handoff-F "Config generation method") |
| 2 | All three types in `/admin/content`, normal create/edit/delete | `CouponCrudTest::testCreateEditDelete` (`CouponCrudTest.php:82-123`, entity-API CRUD all three types), `InstallTest::testContentOverviewIntegration` (`:252-272`: core content view exists, type filter `plugin_id: bundle` with `value: []` — unrestricted — read from the `default` display where core stores it, bundles in list). The page itself = AC 9 (journal) | YES at API/config level, per the issue's own testing rules (no Functional); UI = walkthrough |
| 3 | Coupon unsavable without valid Sponsor/Campaign, or code already used for that Sponsor+Campaign | `CouponValidationTest`: missing refs/code (`:104-122`), sponsor ER restricted to sponsor bundle (`:127-136`), duplicate exact + whitespace-padded (`:141-156`), same code allowed for other sponsor/campaign (`:161-180`), redeemed-date combos (`:185-224`) — all via `$node->validate()` (API-level, `:83-89`) | YES. Constraint: `CouponCodeUniqueConstraintValidator.php:35-49` (normalized compare `:26`, self-exclusion `:41-43`, violation at `field_coupon_code`); attached entity-wide via `hook_entity_type_alter` (`badcamp_sponsors.module:96-101`) so forms/API both hit it |
| 4 | Deleting Sponsor/Campaign with coupons (incl. trashed) refused, count shown | `DeleteBlockingTest`: counter trash-aware 2 = 1 live + 1 trashed (`:134-143`), delete forbidden while referenced incl. trashed-only case (`:148-182`), allowed once unreferenced + real delete runs (`:187-223`), hook never grants (`:177-181`). Count shown: reason string carries the number (`badcamp_sponsors.module:53`, `sprintf('...%d coupon(s)...')`) | YES for the rule (mutations 1 & 4 bite, handoff-T-green.md:108,111). The reason text's on-form rendering is a real-page check → AC 9 |
| 5 | User without coupon permission gets 403 on a coupon's page | `CouponAccessTest::testCouponViewRequiresPermission` (`CouponAccessTest.php:88-110`: forbidden without, allowed with; 403-equivalent at the access layer), `testOtherTypesStayPublic` (`:115-128`) | YES at API level; router/form 403 = AC 9. Hook: `badcamp_sponsors.module:38-43` (neutral-with-permission so core grants published views — correct contract) |
| 6 | `/sponsors`, `/sponsors/campaigns`, `/sponsors/coupons` list records for a Sponsorship manager; 403 without section permission | `SectionAccessTest`: route paths + `_admin_route` (`:43-58`), exact `_permission` requirements (`:59-73`, coupons = unspaced `'access sponsors section,view coupon content'`), behavioral `checkNamedRoute` for plain/section/full manager (`:106-138`), shipped role grants all three (`:143-155`) | YES. Fold bite-check proved the pin and the behavior both fail on perm drift (handoff-T-green.md:202-207) |
| 7 | Each list exports a CSV of its filtered rows | `InstallTest::testViewsAreCreatedWithCsvExports` (`:134-145`: exactly one `data_export` display per view at `<path>/export.csv`), `SectionAccessTest::testCsvRoutesSharePermissions` (`:79-101`: one route each, same `_permission` as page). Config: `views.view.{sponsors,campaigns,coupons}.yml` `data_export_1` with `displays: page_1` attachment (`sponsors.yml:589-601`, `campaigns.yml:432-447`, `coupons.yml:934-949`) — attachments inherit the page's exposed input, i.e. "respecting the current filters" | YES at config/route level; live CSV bytes = AC 9 (per brief.md:56 and issue's no-Functional rule) |
| 8 | Sponsors `/sponsor/<name>`, Campaigns `/campaign/<name>`, Coupons none | `PathautoTest::testSponsorAndCampaignAliases` (`PathautoTest.php:96-110`, exact `/sponsor/acme-corp`, `/campaign/fall-blast`), `testCouponsGetNoAlias` (`:115-138`, empty + no catch-all `/coupon*` from any pattern). Config: exactly two patterns `pathauto.pattern.sponsor.yml:11` `/sponsor/[node:title]`, `pathauto.pattern.campaign.yml:11` `/campaign/[node:title]`, `id: entity_bundle:node` (`:19` both) | YES |
| 9 | Post-merge walkthrough on the running DDEV site as a Sponsorship manager | **PENDING by design — correctly so.** Recorded at survey-brief (decisions.md:18: "the post-merge walkthrough AC recorded as PENDING by design, never faked") and handoff-T-green.md:142 ("PENDING by design | journal (not faked)"). Nobody faked or pre-verified it as an AC: F's live worktree-site install/uninstall checks (handoff-F.md:100-107) were implementation verification of config round-tripping, not recorded anywhere as AC 9 completion; the AC 9 box is unchecked in every artifact | YES (the pending itself is the compliant state). Post-merge obligation lives with O |
| 10 | `drush pmu` leaves no nodes/config/field tables/aliases; re-enabling works | `UninstallTest::testUninstallRemovesEverything` (`UninstallTest.php:120-210`: raw `node_field_data` count 0 — sees the trashed coupon's surviving row —, ~30 named config objects + `badcamp_sponsors.*` prefix gone, `findTables('node\_%')` filtered for `field_coupon*`/`field_sponsor*` empty, no `/sponsor/`-`/campaign/` aliases and none pointing at deleted nids, `views_data_export`+`csv_serialization` still enabled), `testReEnablingWorks` (`:215-222`). Anti-vacuous sanity seeds at `:51-56` | YES. Mutation 3 (trash-context dropped) bites: 4 surviving rows (handoff-T-green.md:110) |

### Settled design decisions ("do not reopen")

| Decision | Verified | Evidence |
|---|---|---|
| Node content types, **no `hook_schema()`** | YES | grep across module: zero `hook_schema`/`*_schema` implementations; schema = field storage config (8 × `field.storage.node.*.yml`) |
| **No DB foreign keys**; relationships = ER fields + code rules | YES | grep "foreign": only a docblock mention (`badcamp_sponsors.module:22`); no `foreign key` mappings in any `field.storage.*.yml`; rules live in the access hook + constraint as the issue specifies |
| IDs are node IDs; Coupon ID = nid | YES | `views.view.coupons.yml:28-38` `nid` field labeled 'Coupon ID'; nothing overrides entity keys |
| Code sponsor-provided, **never generated**, unique per (Sponsor, Campaign), trimmed | YES | `field.field.node.coupon.field_coupon_code.yml`: no `default_value`, no `default_value_callback`; presave only normalizes (`badcamp_sponsors.module:74-75`); uniqueness + trim in `CouponValidationTest.php:141-156` |
| Delete blocking via node `delete` access; **trashed coupons count** | YES | `badcamp_sponsors.module:45-57` + `CouponCounter.php:74-110` (live + `exists('deleted')` summed); `DeleteBlockingTest.php:164-167` (trashed-only reference still blocks) |
| Coupon privacy: permission-gated view, no alias, not promoted, sitemap-excluded, no metatag | YES | `badcamp_sponsors.module:38-43`; `PathautoTest.php:115-138`; `core.base_field_override.node.coupon.promote.yml:14-17` (default 0) + behavior `CouponCrudTest.php:102`; `simple_sitemap.bundle_settings.default.node.{sponsor,campaign,coupon}.yml` `index: false` + `InstallTest.php:224-234`; no metatag field asserted `InstallTest.php:240-246` |
| **No editorial workflow** for the three types | YES | `WorkflowExclusionTest.php:55-79` (reproduces `basic_editorial`→page only, asserts bundles unchanged, no module-shipped workflow config); grep: no `workflows.*` config shipped |
| Singular record aliases (never `/sponsors/…`) | YES | Pattern strings `/sponsor/[node:title]`, `/campaign/[node:title]` only (`pathauto.pattern.*.yml:11`); `PathautoTest.php:152-154` exactly two patterns |
| Uninstall deletes every node (incl. trashed) before config, in batches; `views_data_export` survives | YES | `hook_module_preuninstall` (`badcamp_sponsors.module:117-145`): chunks of 50 (`:128`), inside `executeInTrashContext('ignore')` guarded by `hasService` (`:138-144`), no `db_drop_table()` anywhere (grep clean); `UninstallTest.php:205-209` deps stay enabled |
| Section: three tabbed views, exposed filters, action links, admin theme, CSV at `<path>/export.csv` | YES | Tabs: `sponsors.yml:564-571` (`default tab`) + `campaigns.yml:416` / `coupons.yml:912` (`tab`) — one local-task set rooted at `/sponsors`; `use_admin_theme: true` on all three page_1 (`sponsors.yml:563`, `campaigns.yml:414`, `coupons.yml:910`); filters: name `sponsors.yml:395-420`/`campaigns.yml:267-276`, sponsor+campaign+redeemed+date-range `coupons.yml:585-791` (redeemed `value: null` at `:723`, the live-caught fix); action links `badcamp_sponsors.links.action.yml` (3 × `node.add`); operations columns `entity_operations` in all three views |
| Field spec (website link optional; logo image optional **alt required**; date-issued required default-now; redeemed default no; redeemed-date rule; auto title + hidden title widget) | YES, except one item → Verdict | `InstallTest.php:73-109` types/required; `field.field.node.sponsor.field_sponsor_logo.yml` `alt_field: true, alt_field_required: true`; date default `badcamp_sponsors.module:77-79` + `CouponCrudTest.php:104-106`; redeemed default 0 (`field_coupon_redeemed.yml:18-20`); title `SAVE20 — Acme Corp / Fall Blast` `CouponCrudTest.php:96-100`, regenerates on edit `:108-114`; title hidden `core.entity_form_display.node.coupon.default.yml:95` + `CouponCrudTest.php:128-135`. **Gap: Coupon ID not shown on the edit form (see Verdict item 1)** |
| Sponsorship manager role + top-level admin menu with children | YES | `user.role.sponsorship_manager.yml` (13 perms, `is_admin: false`) asserted exactly (`InstallTest.php:178-199`); `badcamp_sponsors.links.menu.yml` (`system.admin` parent, 2 children) asserted (`InstallTest.php:204-216`) |
| Tests: Unit + Kernel only, **no Functional** | YES | All 12 test files extend `KernelTestBase` (9) or `UnitTestCase` (3) — base-class grep; zero `Functional`/`BrowserTestBase`/`JavascriptTestBase` matches in the audited diff; placement `tests/src/Kernel` + `tests/src/Unit` exactly |

### Two warns routed to S (A-dup round 1 findings 3–4) — rulings

**(a) Coupon-count column: views field plugin reusing `coupon_counter`, not SQL aggregation; not click-sortable.**
**Ruling: the issue tolerates it — not a violation.**
- The issue's section spec says "Each is a table with a pager, sortable columns and operation links" —
  a description of the table style, satisfied: every ordinary column is sortable
  (`sponsors.yml:493`, `campaigns.yml:356`, `coupons.yml:813-851`, `sortable: true`). The phrase does
  not single out the computed count column, and the issue's column list says only "number of coupons".
- Making that one column click-sortable via SQL aggregation would require either a **second,
  non-trash-aware count implementation** — directly contradicting the settled rule that trashed
  coupons count (the sponsors page would show 0 while the sponsor's delete is blocked "referenced by
  1"), i.e. the column and the delete-block message would disagree — or a custom join plugin, which is
  exactly the parallel-mechanism class A-dup BLOCKed twice for. The single-source-of-truth reading
  (column = the same trash-aware number as the delete block) is the only internally consistent one.
- Perf: the plugin runs ≤2 count queries per rendered row (`CouponCount.php:80-89`, `query()` empty at
  `:75`), bounded by pager 50. Acceptable at this scale; recorded as advisory, not a finding.
- The trade-off is documented where the issue's own risk note demands disclosure: F deviation 5
  (handoff-F.md:185-189) and the plugin docblock (`CouponCount.php:17-21`).

**(b) Coupon-code uniqueness is live-only (a trashed coupon does not block reusing its code for the same
sponsor+campaign).**
**Ruling: the issue is ambiguous at this margin; live-only is the correct, conservative reading — not a
violation, not an ADVISORY-HOLD. The edge case is recorded below as an advisory for O.**
- Textual: the issue mentions trash-awareness exactly once, explicitly scoped to delete blocking
  ("Coupons in Trash count: a sponsor cannot be deleted while a trashed coupon still points at it").
  The uniqueness rule appears twice (settled decision "unique per (Sponsor, Campaign)"; referential
  rule "an entity validation constraint on Coupon rejects a second coupon with the same code…") with
  no trash mention. The drafters demonstrably knew how to make trash count where they wanted to.
- Platform: live-only is Drupal's default. I verified trash's own ecosystem treats trash-aware
  uniqueness as an explicit per-constraint opt-in: `trash.services.yml:116-122` ships
  `TrashAwareUniqueFieldValueValidator` and swaps it in **only** for its own `UniqueField` constraint
  (`web/modules/contrib/trash/src/Validation/TrashAwareUniqueFieldValueValidator.php:18`). The module's
  plain entity query (`CouponCodeUniqueConstraintValidator.php:35-40`) takes the platform default.
  Following the platform default absent an explicit requirement is the conservative implementation;
  making uniqueness trash-aware would be the unrequested **product change** — the exact thing an
  ADVISORY-HOLD exists to avoid guessing at.
- The operative mechanism sentence — "an entity validation constraint… rejects a second coupon with
  the same code for the same Sponsor and Campaign", working "through the API" — is implemented
  literally and verified (`CouponValidationTest.php:141-180`), against the live set the platform
  presents to entity queries.
- Residual edge (advisory, not a finding): soft-delete coupon X → re-issue X for the same
  (sponsor, campaign) → **restore** the trashed one → two live rows share the code; restores do not
  re-validate, so no constraint sees it. Consequence is an editorial data-quality blemish in a
  tracking-only system (the issue's out-of-scope list excludes redemption/validation), not a
  referential-integrity break. If O wants belt-and-braces anyway, the one-line platform-seam fix is
  wrapping the validator's query in `executeInTrashContext('ignore')` (already named by A-dup round 1
  finding 4) plus one Kernel case — an operator decision, not required by any issue sentence.

### AC 9 integrity check (explicitly requested)

Confirmed pending, not faked, not pre-verified: `decisions.md:18` (survey-brief entry) and
`handoff-T-green.md:142` both record AC 9 as PENDING by design. F's live-site work on the worktree
DDEV (handoff-F.md:100-107) was disclosed as config-verification during implementation and is nowhere
recorded as AC 9 completion; the checkbox remains open in every artifact. **No violation.** The
walkthrough obligation transfers to O post-merge: enable → add records from `/sponsors` → export each
CSV → attempt the refused delete → uninstall.

## Quality audit

| Area | Result | Notes |
|------|--------|-------|
| API consistency | N/A | No custom REST/API surface; routes are stock views routes with core `_permission` requirements — verified real (`SectionAccessTest.php:43-101`) |
| Error handling | PASS | Validation violations at correct property paths (`CouponCodeUniqueConstraintValidator.php:47`, `CouponRedeemedDateConstraintValidator`); delete-block reason carries the count (`badcamp_sponsors.module:53`); missing refs/code delegated to core required-field constraints, no double-reporting (`CouponCodeUniqueConstraintValidator.php:29-33`) |
| UI/UX match to spec | PASS except Verdict item 1 | Views columns match the issue's lists exactly (CSV field lists: `title, website, logo, coupon_count` / `title, coupon_count` / `nid, code, sponsor, campaign, date_issued, redeemed, redeemed_date`); tabs, filters, action links, admin theme, operations all present. The coupon **edit-form ID display** is missing (see Verdict) |
| Accessibility | N/A (this run) | No visual surface in the pipeline run; the admin pages are stock Drupal table views. Worth a glance in the AC 9 walkthrough |
| Architecture gate | PASS | No conflict between my findings and A's verdicts. The two a-dup blocks were folded to stock seams (unspaced `perm` carried end-to-end by core's checker; `entity_bundle:node` via core's deriver) — re-verified on disk: `views.view.coupons.yml:561` unspaced; `pathauto.pattern.{sponsor,campaign}.yml:19` `entity_bundle:node`; grep confirms zero `ServiceProvider`/`TrimmingPermissionAccessCheck`/`Plugin/Condition` residue |
| Code organization | PASS | 4 hooks + 4 plain classes + 4 plugin/constraint classes, `declare(strict_types=1)` throughout, accurate post-fold docblocks, no dead code (every class has a caller; deletions verified), phpcs Drupal+DrupalPractice 0/0 (handoff-T-green.md:99) |
| Security | PASS | Coupon view gated by permission incl. cache metadata (`badcamp_sponsors.module:38-43`, `cachePerPermissions`); counter/validator/preuninstall queries use `accessCheck(FALSE)` correctly (privileged internal operations, not data exposure); no secrets; `restrict access: true` on `view coupon content` (`badcamp_sponsors.permissions.yml`) |
| Performance | PASS (advisory) | Count column ≤2 queries/row bounded by pager 50 (ruling a); preuninstall chunks of 50; no N+1 anywhere else |
| Visual regression | N/A | `uiSurface: false`; real pages gated by AC 9 |
| Naming consistency | PASS | Field machine names are verbatim the issue's (`field_sponsor_website`, `field_sponsor_logo`, `field_coupon_{code,sponsor,campaign,date_issued,redeemed,redeemed_date}`); types `sponsor`/`campaign`/`coupon`; role `sponsorship_manager`; permissions match their prose ("access the Sponsors section", "View coupon content") |
| Test quality (rubric §7) | PASS | Per-method review (all 40): each names one behavior; cheapest sufficient tier (Unit only for the three pure rules, Kernel for the rest, zero Functional per the issue's binding rule); asserts behavior not implementation — 4/4 mutations + 2 fold bites prove failure-for-the-right-reason (handoff-T-green.md:104-113, 200-217); suite proportionate (40 methods for a full module story, tiered). Two config-literal pins (perm string `SectionAccessTest.php:70-73`, plugin id `InstallTest.php:167`) each encode a platform-required spelling and are paired with behavioral twins that bite — acceptable. No assertion-free, tautological, unreachable-outcome, mock-shaped or coverage-padding tests found. Minor (non-blocking, already journaled by T): `PathautoTest`/`UninstallTest` duplicate a `getAliases()` helper with different key shapes (`PathautoTest.php:83-91` vs `UninstallTest.php:107-115`) |

## Scope check

**Nothing material beyond the issue.** The only additions are platform-authorized: the
`CouponCount` views field plugin (the issue's "number of coupons" column — ruling a),
its 7-line config-schema stanza (the plugin's `counter` option), and a `configure:` key in
`badcamp_sponsors.info.yml` (standard module metadata pointing at the section view — trivial).
`access content` on the role (beyond the issue's literal permission list) is functionally required
for the role to view content and is test-pinned. The issue's out-of-scope list is respected: no
redemption page, no import, no DB FKs.

**One item in the issue left unimplemented without a recorded justification** → Verdict item 1.

## Verdict

**REWORK — one required change before commit:**

1. **Show the Coupon ID on the coupon edit form for saved coupons.** The issue's Coupon field spec
   says: "Coupon ID: the node ID (nid). Shown in lists **and on the edit form once saved**; never
   editable." Lists: satisfied (`views.view.coupons.yml:28-38`, first column labeled 'Coupon ID').
   Edit form: **absent** — `core.entity_form_display.node.coupon.default.yml` places no ID component
   (content = created/uid/status + the six fields, `:21-91`; hidden = promote/sticky/title, `:92-95`),
   the node view display carries no ID either (`core.entity_view_display.node.coupon.default.yml:22-77`),
   and no `hook_form_alter` exists (`badcamp_sponsors.module` implements exactly 4 hooks). No test
   covers it and no handoff or journal entry records a deviation — the brief's "Also required by the
   issue and tested" enumeration (brief.md:68-73) silently dropped it, and it fell through every
   subsequent gate.

   Smallest platform-native fix: a `hook_form_NODE_FORM_alter` (or a form-display extra field) adding
   a plain, non-input rendering of the nid (e.g. a disabled/`#markup` element in the form's footer or
   a read-only item) only when the coupon is not new, plus the cheapest sufficient Kernel assertion
   (build the form for a saved coupon → the ID is rendered and is not an editable form input).
   Never editable is already guaranteed (no widget exists for nid).

   **Operator alternative:** O may instead accept this as a recorded deviation (judging the list
   column, CSV column and `/node/<nid>/edit` URL sufficient identification) — but that acceptance
   must be written into the journal; shipping it silently is exactly the gap this audit exists to
   catch. Everything else in the run is ready.

All ten acceptance criteria are otherwise met (AC 9 correctly pending post-merge), every settled
design decision is implemented and verified, both routed warns are ruled non-violations with
recorded reasoning, and the testing rules (Unit/Kernel only) are honored. This is the single
remaining delta between the diff and the issue text.

## Advisory notes

1. **Ruling (b) residual edge** (restore-after-reissue can produce two live coupons sharing a code):
   if the operator wants trash-aware uniqueness after all, the one-line
   `executeInTrashContext('ignore')` wrapper in `CouponCodeUniqueConstraintValidator.php:35` + one
   Kernel case is the platform-seam fix. Not required by the issue as written.
2. **Ruling (a) follow-up trigger:** if the sponsors/campaigns lists grow past tens of rows and
   editors ask to sort by coupon count, that is a product decision (it needs a second,
   non-trash-aware count or a custom join plugin) — reopen with O, don't let F improvise it.
3. **Delete-block reason surfacing:** the count-bearing reason (`badcamp_sponsors.module:53`) is the
   mechanism the issue's "the delete form says how many coupons reference it" relies on; its actual
   rendered appearance on the delete confirm form is a real-page check — include it in the AC 9
   walkthrough explicitly.
4. **AC 9 walkthrough script** should also glance at: coupon form (item 1 above, once fixed),
   exposed "Redeemed" filter neutral by default, CSV bytes open in a spreadsheet, tabs reading as
   one section.
5. **Pre-existing `web/robots.txt` drift** (removed `Sitemap:` line) sits uncommitted in the worktree
   (disclosed since T-red; `git status` confirms it is still the only working-tree change). Restore
   before merge — the merge-forward clean-worktree verification will refuse it regardless.
6. **`UninstallTest::getAliases()`** still has the pre-repair key shape (`UninstallTest.php:107-115`);
   harmless (only `assertNotEmpty` uses it), already journaled by T — apply the `array_values`
   treatment if a future assertion needs exact keys.

## Round 2 — 2026-10-17

**Re-audited:** `git show a491e03` (fix commit: T's RED-authored test + F's implementation + form-display
config) in full; handoff-T-green.md "spec-audit rework: edit-form Coupon ID" (RED 41/1 → GREEN) and
"Rework GREEN" lines; decisions.md rework RED/GREEN entries; handoff-A-dup.md Round 3
(`**Verdict:** PASS`, :134 — no parallel mechanism, stock extra-field seam, redirect-precedent pattern).
HEAD = a491e03; the only worktree changes are the appended a-dup round-3 journal and the known
`web/robots.txt` residue (advisory 5 stands). Preconditions re-confirmed: A PASS (up-front + a-dup r2/r3),
T GREEN ("Blocking issues: None"; suite `OK (41 tests, 402 assertions)` exit 0; phpcs 21/21 files 0/0).

### Round-1 finding 1 — closed

Issue text: "Coupon ID: the node ID (nid). Shown in lists and on the edit form once saved; never editable."

- **Lists — unchanged.** `git diff 8e85953..HEAD --stat` over `src/` and the three `views.view.*.yml` is
  empty; round-1 evidence (coupons view first column `nid` labeled 'Coupon ID', `views.view.coupons.yml:28-38`)
  stands verbatim.
- **Edit form once saved — now satisfied.** Extra field `badcamp_sponsors_coupon_id` declared form-context
  only (`$extra['node']['coupon']['form']`, `.module:113` — no `display` key, so it cannot leak into view
  displays); shipped as a **shown** component of `node.coupon.default` (config on disk
  `core.entity_form_display.node.coupon.default.yml:22-26`: `weight: 0`, `region: content`, not in `hidden`).
  `badcamp_sponsors_form_node_form_alter()` populates it only for saved coupons — early return unless
  `EntityFormInterface` + `NodeInterface` + `bundle() === 'coupon'` + `!isNew()` (`.module:133-138`) — as
  `#type => 'item'`, `#title => 'Coupon ID'`, `#markup => (string) $node->id()` (`.module:141-145`).
  **Never editable — verified in code:** `item` is core's display-only element (its own docblock:
  "display-only form element… this is a read-only field", `Item.php:7-12`); it renders no editor control
  (themed `#markup`, no input widget in the DOM); the component is widget-less (extra field, nothing maps
  it onto the node); and nid is immutable on entity save. Verified on disk, not from the handoffs.
- **Tested.** `CouponCrudTest::testCouponIdShownReadOnlyOnEditForm` builds the real form via
  `entity.form_builder->getForm($coupon, 'default')`, then asserts element presence, `#type === 'item'`
  (the read-only mechanism — exactly the assertion round 1 prescribed), `#title`, `#markup` containing the
  saved nid, and the shown-component config contract (present in `content`, absent from `hidden`).
  RED failed for the right reason (key-absent at `CouponCrudTest.php:161`, 24 assertions ran, file's other
  2 tests green, full suite 41/1 = exactly the new test); GREEN per T's records: suite OK (41, 402) exit 0,
  phpcs 0/0. Test-quality rubric: one behavior, cheapest sufficient tier (Kernel entity-form build, no
  browser, honoring the no-Functional rule), fails-for-the-right-reason proven by RED, proportionate
  (one method guarding one finding). Round 1's "PASS except Verdict item 1" in the UI/UX row and the
  Scope check's "one item unimplemented" are both resolved by this section.

### No new spec damage

- The commit's production delta is exactly round 1's prescribed smallest platform-native fix: 2 hooks +
  2 imports in `.module` (+45) and the 5-line shown component in the coupon form display (+5); plus T's
  test (+65) and 3 run docs. No composer change, no schema stanza, no service/plugin/element registration,
  no edits outside the module. a-dup round 3 PASS confirms the stock-seam/no-parallel-mechanism reading.
- **Round-1 rulings untouched:** ruling (a) count column (`CouponCount.php`, views config) and ruling (b)
  live-only uniqueness (`CouponCodeUniqueConstraintValidator.php`) are byte-identical since 8e85953 (empty
  diff over `src/` and the views YMLs, verified above). Settled decisions re-spot-checked: no `hook_schema`
  creep (the extra field is runtime info, not storage), uninstall story unaffected (no new config entity,
  no field table).

**Verdict: PASS** — round-1 REWORK item 1 is closed by a platform-native, tested, read-only Coupon-ID
element on the coupon edit form for saved coupons; lists unchanged; no scope creep, no new spec damage,
and both round-1 rulings stand. Ready for O's merge decision (AC 9 post-merge walkthrough and the
`web/robots.txt` triage remain O's, per advisories 3–5).

### Round-2 advisory

1. **Inaccurate parenthetical, non-blocking:** F's handoff, T's contract/test docblocks, and the module
   comment say `item` is "`#input` FALSE". In this core version `Item.php:27` declares `'#input' => TRUE`
   (so Form API can internally assign values when a form dynamically switches textfield↔item); the
   element is still display-only and read-only per its own docblock and rendering, and the test pins
   `#type`/`#markup` behavior — nothing false is asserted or behaves wrongly. If O wants the record
   exact, a one-line comment rewording in `.module` and `CouponCrudTest.php` ("display-only/read-only,
   no editor control" instead of "#input FALSE") can ride any future touch; it does not warrant a rework
   cycle on its own.

## Round 3 — 2026-10-17

**Re-audited (post-audit rework, uncommitted working tree vs `1c729af`):** `git diff` in full —
`badcamp_sponsors.module` (+12/−2, the −2 a docblock rewrap), `PathautoTest.php`
(`testCatchAllPatternDoesNotAliasCoupons` added), decisions.md RED entry, handoff-T-green.md
"post-audit rework: coupon alias vs catch-all pattern", handoff-F.md "post-audit rework: coupon alias
skip"; issue body re-read via REST (`gh issue view` currently dies on a GitHub Projects-classic GraphQL
deprecation before printing — `gh api repos/Performant-Labs/badcamp/issues/1` works). Preconditions
re-confirmed: A PASS (up-front; a-dup round 3 PASS, committed at `1c729af`); T zero blocking issues
(valid RED + pinned contract; GREEN attested `OK (42 tests, 409 assertions)` exit 0, phpcs 0/0 —
407→409 is exactly the two pinned mechanism assertions now completing).

### The violation and the fix

The live finish check exposed what the Kernel env structurally could not: the Drupal CMS starter's
`menu_path` pattern (no selection criteria, weight 10) matches every node bundle, and a saved coupon
got `/save20-acme-corp-fall-blast` — violating the issue's settled text at three points ("Coupons are
private… have no path alias", issue :52; "Coupon: **no alias** (confirmed). Coupons are private and
stay at `/node/<nid>`", :73; AC ":102 Coupons get none"). The original `testCouponsGetNoAlias` passed
vacuously: no fixture pattern matched a coupon, and `PathautoState::getValue()` computes SKIP when
nothing matches.

F's fix is **exactly T's pinned contract**: `$entity->path->pathauto = PathautoState::SKIP;`
(`badcamp_sponsors.module:98`) plus the `use Drupal\pathauto\PathautoState;` import, inside the existing
`badcamp_sponsors_entity_presave()` coupon branch — after the early return for non-coupon bundles
(`:75-77`), so **sponsors/campaigns never reach it and their aliasing is untouched**; the module's two
shipped patterns are byte-identical. The import is safe: `drupal:pathauto` is a hard dependency
(`badcamp_sponsors.info.yml:6-8`), so the class exists whenever the module is enabled.

**No parallel mechanism — by construction.** SKIP is pathauto's own state machine
(`PathautoGenerator.php:393` returns without aliasing when state ≠ CREATE); the fix adds no service,
plugin, hook, config surgery, or alias-deletion pass. It also correctly does **not** touch the
starter's `menu_path` pattern — that is site config outside the module's ownership; the module-side
SKIP guarantees the invariant under *any* site configuration, which is the stronger and correct
placement. Persistence via `PathautoItem::postSave()` → keyvalue `pathauto_state.node` closes the
recurrence hole: later editor saves and pathauto bulk updates skip too.

### Test quality and no collateral damage

`testCatchAllPatternDoesNotAliasCoupons` reproduces the live shape (enabled catch-all, weight 10,
zero criteria — the actual violation surface; the simpler token string is a fair stand-in since the
matching surface, not the tokens, is the defect). Three assertions, each guarding a distinct failure
mode: (1) sponsor keeps `/sponsor/acme-corp` under the catch-all — specific weight-0 beats catch-all
weight-10, the no-collateral guard that would fail if the fix broke sponsor/campaign aliasing;
(2) coupon gets `[]` — the RED, which pre-fix reproduced the live alias **byte-for-byte** and failed
as a behavior assertion (not setup/import error), 41 prior tests green; (3) SKIP pinned in-memory and
persisted. The in-method placement of the mechanism assertions is sound: standalone, they would be
vacuously green pre-fix (no matching pattern → SKIP anyway). Cheapest sufficient tier (Kernel, no
Functional — the issue's binding testing rule), one method guarding one finding, no smells. Round-1
rulings (a)/(b) and the round-2 edit-form fix are byte-identical since `1c729af` (diff touches only
`.module`, the test, and docs).

**Verdict: PASS** — the one-line fix satisfies the settled "Coupon: no alias (confirmed)" decision
through pathauto's own canonical seam, damages nothing else (sponsor/campaign aliasing guarded in code
and by test), introduces no parallel mechanism, and is locked in by a valid-RED test that reproduces
the exact live violation. Green suite 42/409 exit 0, phpcs 0/0. Ready for O's merge decision (AC 9
post-merge walkthrough remains O's).

### Round-3 advisories

1. **Stale aliases created before the fix survive it.** SKIP makes pathauto *not touch* a coupon's
   alias; it never deletes an existing row (`PathautoGenerator.php:393` returns NULL, leaving the old
   alias). Any coupon aliased on the running DDEV site pre-fix — the observed
   `/save20-acme-corp-fall-blast` — keeps that alias until manually deleted. Before or during the AC 9
   walkthrough, delete that alias (or the test coupon); new saves are covered.
2. **Two untracked strays** (`diag-check.php`, `install-check.php`, worktree root) remain — disclosed
   by T and F, pre-dating this rework, outside every diff. Keep them out of any commit; ideally delete
   before merge.
3. **Round-2 advisory 5 is closed:** `web/robots.txt` no longer appears in `git status` (restored since
   round 2; HEAD now `1c729af`, docs-only).
