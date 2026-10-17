# Handoff-A-dup: Phase 7 - badcamp_sponsors  (anti-duplication gate)

**Date:** 2026-10-17
**Branch:** issue-0001-badcamp-sponsors
**Diff base:** c0a8dd9   **Diff head:** 4cc3022
**Reuse map:** docs/handoffs/0001-badcamp-sponsors/survey.md
**Verdict:** BLOCK

## Summary

F extended the platform faithfully almost everywhere: node config entities mirroring core
shapes, stock views `perm` access on two of three pages, `hook_ENTITY_TYPE_access` under the
modern contract, constraint plugins, the trash `executeInTrashContext('ignore')` seam at
preuninstall, and split-on-`deleted` counting exactly as my Phase-3 findings pinned them — no
raw SQL, no `hook_schema`, no hand-rolled CSV/token/batch helpers, no dead code, no drive-by
edits (diff = module + the issue-required composer line + run journal). But **two production
mechanisms are parallel paths that exist only to satisfy stale T pins, where stock platform
mechanisms fully meet the issue's contract**: a site-wide ServiceProvider swap of core's
`access_check.permission` class (to keep a *spaced* `_permission` string that core's checker
provably cannot honor — and that core itself never ships anywhere), and a resurrected
`node_type` condition plugin that line-for-line duplicates core's live
`EntityBundle` condition under an id core removed. For the `installSchema` ordering conflict and
two assertion-shape bugs F correctly routed test repairs through O/T; for these two pins it
instead changed production — the same class of conflict, handled inconsistently. Both fold-ins
are one-line config changes plus one-line test repairs, deleting ~185 lines of new mechanism.

## Findings

| # | Severity | File:line | Finding | Suggested fix |
|---|---|---|---|---|
| 1 | block | `web/modules/custom/badcamp_sponsors/src/BadcampSponsorsServiceProvider.php:24-29` (+ `src/Access/TrimmingPermissionAccessCheck.php`) | **Parallel path replacing a core global service to work around a YAML formatting choice.** The coupons view ships `perm: 'access sponsors section, view coupon content'` (spaced); core's `PermissionAccessCheck` splits with a bare `explode(',')` (`web/core/modules/user/src/Access/PermissionAccessCheck.php:37`), so the second conjunct is unmatchable. The issue requires only "section permission + coupon permission (AND)" — the stock views `perm` option with the **unspaced** string `'access sponsors section,view coupon content'` delivers exactly that, and unspaced is the convention core itself uses in **100%** of its routing/views config (verified: zero spaced comma strings in `web/core/modules/*/​*.routing.yml` and core's shipped views). The swap exists solely to keep T's literal pinned string (`SectionAccessTest.php:68-71`), a pin F verified empirically is unsatisfiable on stock core. Blast radius is site-wide: every `_permission` route on the site runs through the module's subclass while it is enabled, and flips back on uninstall — a cross-cutting mechanism named by no Reuse-map row, brief amendment, or issue sentence. The class itself is well-built (guarded `hasDefinition`, subclass delegates to core, clone avoids mutating the cached route object), but quality of the mechanism is not justification for it; the docblock's claim that spaced lists are "the documented way" is contradicted by the tree. | Ship the perm unspaced in `config/install/views.view.coupons.yml` (views copies `perm` verbatim to the route requirement, so the same route-level AND semantics hold and `checkNamedRoute()` passes for both-permission holders — F verified this empirically). Delete `src/Access/TrimmingPermissionAccessCheck.php` and `src/BadcampSponsorsServiceProvider.php`. Route the one-line test repair (`SectionAccessTest.php:69` expected literal) to T through O — the same channel already used for the `installSchema`/assertion-shape repairs. If a spaced string were ever truly required, the scoped stock seam is a RouteSubscriber normalizing only the module's own three routes at build time, not a global service swap. |
| 2 | block | `web/modules/custom/badcamp_sponsors/src/Plugin/Condition/NodeType.php:1-141` (+ `config/schema/badcamp_sponsors.schema.yml:1-7`) | **Duplicate of a live core class under a removed plugin id.** Core 11.4 provides no `node_type` condition plugin (grep: only the `NodeType` config entity and a views argument carry that id); the platform mechanism is `entity_bundle` + deriver (`web/core/lib/Drupal/Core/Entity/Plugin/Condition/EntityBundle.php`), instantiated as `entity_bundle:node` (core's own `BlockForm.php:256`, `EntityBundleConditionTest`). F's class is a near line-for-line mirror of `EntityBundle` (constructor, `buildConfigurationForm`, `submitConfigurationForm`, `evaluate`, `summary`) minus the deriver, plus a `condition.plugin.node_type` schema stanza — 141 lines of core logic this module now maintains forever. It exists only because T pinned the legacy id (`InstallTest.php:167` `getPluginId() === 'node_type'`; T-red handoff prose), a Drupal-9-era id the installer rejects on this core (F's own "plugin does not exist" evidence). Nothing in the issue, brief, or Reuse map (row: "NEW two patterns", shape from pathauto's entity + schema) authorizes shipping a condition plugin. Resurrecting a deleted plugin is the opposite of the map's "reuse every platform mechanism". | Change `selection_criteria.<uuid>.id` from `node_type` to `entity_bundle:node` in `pathauto.pattern.sponsor.yml:19` and `pathauto.pattern.campaign.yml:19` (the `bundles` mapping shape is identical; core provides the `condition.plugin.entity_bundle:*` schema). Delete `src/Plugin/Condition/NodeType.php` and the `condition.plugin.node_type` stanza from the module schema. One-line test repair: `InstallTest.php:167` compares against `'entity_bundle:node'` — route to T through O. |
| 3 | warn | `web/modules/custom/badcamp_sponsors/src/Plugin/views/field/CouponCount.php:75-88` | No drift — this is the right mechanism (see Summary): a views field plugin is the platform seam for computed columns, and delegating to `coupon_counter` keeps the column and the delete-block rule on one source of truth; SQL aggregation would either re-implement the trash-aware rule a second time or be inexpressible with trash filtering trashed entities out of plain queries. Two consequences for O to triage, both flowing from the mechanism: (a) the count column is **not click-sortable** while the issue's section spec says "sortable columns" — whether that phrase reaches the computed count column is S's read; (b) per-row counting costs up to 2 queries × pager-50 rows per page render. Acceptable at this scale; note it. | Route (a) to S at spec-audit with the mechanism trade-off stated (sortable via SQL = a second, non-trash-aware implementation of the count, or a custom join plugin). Open perf follow-up only if the sponsors/campaigns pages grow past tens of rows. |
| 4 | warn | `web/modules/custom/badcamp_sponsors/src/Plugin/Validation/Constraint/CouponCodeUniqueConstraintValidator.php:35-44` | No parallel mechanism — the plain entity query takes the platform default (trash's `hook_entity_query_alter` filters trashed rows), and trash's own ecosystem treats trash-aware uniqueness as an explicit per-constraint opt-in (trash ships `TrashAwareUniqueFieldValueValidator` and swaps it in for *its* `UniqueField` constraint, `trash.services.yml:116-122`). But the module now holds two trash policies: counting **includes** trashed coupons (issue-mandated) while uniqueness **excludes** them (issue-silent — F did not declare this as a deviation). Edge worth S's ruling: soft-delete coupon with code X → create a new coupon with code X for the same (sponsor, campaign) → **restore** the trashed one → two live rows the constraint never rejected. | Route to S as a spec question against the issue's settled "unique per (Sponsor, Campaign)". If S rules uniqueness must be trash-aware, the fix is wrapping the validator's query in `executeInTrashContext('ignore')` — one line, still the platform seam, no new mechanism. |

Also verified clean (no finding): no reimplemented platform helpers (no token/CSV/serialization
code; entity queries via storage API; chunked `array_chunk(50)` deletes are the correct in-process
form of "in batches" — the Batch API cannot run during uninstall, and trash's own
`TrashCliActions` chunks identically); every shipped config entity carries
`dependencies.enforced.module: [badcamp_sponsors]` and no id collides with existing site config
(`page`, `basic_editorial`, core/contrib view ids untouched); no dead code (every class and schema
stanza has a caller, modulo findings 1-2 whose "callers" are the mechanisms themselves); no
drive-by edits (`composer.json` +1 line is the issue-required dep; `web/` untouched outside the
module). My Phase-3 pinned seams were all honored: `hook_ENTITY_TYPE_access` returning
`AccessResultInterface` (never grants; `cachePerPermissions` / `node_list:coupon` cache tags);
sitemap config with the variant segment (`.default.node.<bundle>`); `.info.yml` deps
node/pathauto/simple_sitemap/views_data_export + link/datetime/image with trash soft; preuninstall
inside `executeInTrashContext('ignore')` via the interface-alias service id guarded by
`hasService()` — the same idiom trash's own `TrashNodeBulkUpdate.php:33` uses; counting as live +
`exists('deleted')` summed, degrading to live-only. Hand-written views YAML is acceptable per the
issue's own mitigation note (mirrored verified exports, installer round-trip, strict schema
checker, live render verification); it is platform-native config, not a parallel path — the
post-merge walkthrough remains its gate. The `int|string` counter widening and the trash
service-id correction are verified platform facts, not drift.

## Notes for F

Two fold-ins, both "delete mechanism, change config, route a one-line test repair to T":

1. **Permission check.** Set `perm: 'access sponsors section,view coupon content'` (unspaced —
   the form core itself ships everywhere) in `views.view.coupons.yml`'s page_1 and data_export_1
   displays. Delete `src/Access/TrimmingPermissionAccessCheck.php` and
   `src/BadcampSponsorsServiceProvider.php` outright. The route still carries AND semantics via
   the stock checker; `SectionAccessTest`'s expected literal at line 69 changes with it (O → T,
   alongside the already-routed `installSchema` and assertion-shape repairs — do not edit the
   test yourself).
2. **Condition plugin.** Switch both pathauto patterns' `selection_criteria` id to
   `entity_bundle:node` (same `bundles`/`context_mapping` shape). Delete
   `src/Plugin/Condition/NodeType.php` and the `condition.plugin.node_type` stanza from
   `config/schema/badcamp_sponsors.schema.yml`. `InstallTest.php:167`'s comparison becomes
   `'entity_bundle:node'` (O → T).

Everything else stands as implemented. After the two folds, re-run the suite and the live-site
install/uninstall check; then re-enter A for the re-review of just these seams.

## Round 2 — 2026-10-17

**Diff reviewed:** `git diff cbfdb2d..6ce742c` (round-1 verdict commit → fold + test-repair commit)
**Verdict:** PASS

### Summary

Both block findings were folded exactly as specified, and the fold introduced no new drift. The
diff is precisely deletions + 3 one-line config changes + 2 sanctioned test repairs + handoff/
journal docs — nothing else. Finding 1: `src/Access/TrimmingPermissionAccessCheck.php` (42 lines)
and `src/BadcampSponsorsServiceProvider.php` (31 lines) are deleted (directories removed; zero
grep matches for either class, for any `ServiceProvider`, or for `condition.plugin.node_type`
anywhere in the module), and `views.view.coupons.yml:561` now ships the unspaced
`perm: 'access sponsors section,view coupon content'` in the `default:` display — the file's only
`access:` stanza, so `page_1` and `data_export_1` inherit it and one line covers all displays and
the CSV route. The AND contract is now carried end-to-end by stock core, verified on core source:
`user/src/Plugin/views/access/Permission.php:96` copies `perm` verbatim to the route's
`_permission` requirement, and `user/src/Access/PermissionAccessCheck.php` splits the unspaced
string on the bare comma into exactly `['access sponsors section', 'view coupon content']` with
`'AND'` — both permissions required, no leading-space poisoning, no site-wide service swap.
Finding 2: `src/Plugin/Condition/NodeType.php` (141 lines) is deleted along with the
`condition.plugin.node_type` schema stanza (module schema now opens directly with the
`views.field.badcamp_sponsors_coupon_count` stanza); both `pathauto.pattern.{sponsor,campaign}.yml`
ship `id: entity_bundle:node` with the identical `bundles`/`context_mapping` shape, resolving to
core's deriver-backed `EntityBundle` (`#[Condition(id: "entity_bundle", deriver:
EntityBundleDeriver::class)]`, `core/lib/Drupal/Core/Entity/Plugin/Condition/EntityBundle.php:15-17`)
under core's own `condition.plugin.entity_bundle:*` schema
(`core.data_types.schema.yml:510`). T's repairs changed only the two stale pins — the
`SectionAccessTest` expected literal to the unspaced form (with a comment correctly stating the
core split semantics) and the `InstallTest` plugin-id comparison to `'entity_bundle:node'` — with
the behavioral assertions untouched; the GREEN suite (40 tests, 392 assertions, exit 0) plus the
documented bite-checks (single-perm mutation fails both the pin and `testSectionRouteAccess`;
either variant of a selection-criteria id drift hard-fails install — `node_type` with core's own
valid-ID list naming `entity_bundle:node`, `request_path` with `SchemaIncompleteException`)
empirically confirm the contract still bites through the stock seams. Round-1 findings 3 and 4
(`warn`: count-column sortability/perf; live-only uniqueness) remain open and routed to S at
spec-audit — not re-litigated here.

### Findings

None. No parallel path remains; both folds deleted mechanism and moved the contract onto the
stock platform seams named in round 1; no drive-by edits or new code appeared in the fold diff.

### Notes for O

- Out-of-diff residue, not a fold finding: the working tree carries an **uncommitted**
  `web/robots.txt` modification (removed `Sitemap: /sitemap.xml` line) that is not part of
  `cbfdb2d..6ce742c` (HEAD still carries the line) and is disclosed as pre-existing/do-not-commit
  in both handoff-F and handoff-T-green. Triage before merge — the merge-forward worktree-clean
  verification will refuse a dirty worktree regardless.
- Proceed to spec-audit with round-1 warns 3 and 4 attached as open questions for S.

## Round 3 — 2026-10-17

**Diff reviewed:** `git show a491e03` (spec-audit rework; scope per O: test + module + form-display
config + handoff docs). Commits since round-2 head `6ce742c`: `8e85953` (a-dup round-2 docs),
`7c9ea7e` (spec-audit REWORK docs), `a491e03` — docs-only in between, so this commit is the whole
production delta.
**Verdict:** PASS

### Summary

The rework extends the platform through its stock seam; no parallel path. The mechanism is
`hook_entity_extra_field_info()` declaring a form-context `badcamp_sponsors_coupon_id` pseudo-field
on `node`.`coupon` + `hook_form_BASE_FORM_ID_alter` (`badcamp_sponsors_form_node_form_alter`)
populating it — verified shape-identical on disk to contrib redirect's `url_redirects`
(`redirect.module:226-244` declares `['node'][bundle]['form']['url_redirects']` with
label/description/weight; `redirect_form_node_form_alter()` at `:249-254` populates under an
`!$node->isNew()` guard). F used exactly the base-form-id variant redirect uses and that S's
finding named first ("hook_form_NODE_FORM_alter (or a form-display extra field)" — F ships both
halves, which is how the seam works: the info hook makes the component placeable, the alter
supplies its content). No custom element type (`#type => 'item'` is stock Form API, `#input` FALSE —
read-only by construction, so "never editable" needs no extra mechanism), no plugin class, no
service, no schema stanza, and no new file under `src/` — the module gains two hook functions and
two imports, nothing else.

### Checklist (per scope)

- **Extra field = stock seam:** yes. Extra-field info is the platform's canonical way to make a
  non-field form component placeable by the form display; the form-context-only declaration (no
  `display` key) cannot leak into view displays. No guard for `moduleExists('node')` is needed —
  node is a hard `.info.yml` dependency of this module (verified round 1); redirect guards only
  because it doesn't share that guarantee.
- **Form alter scoped and guarded:** yes. Early return unless `EntityFormInterface` + `NodeInterface`
  + `bundle() === 'coupon'` + `!isNew()` — bundle-scoped in code, saved coupons only, strictly more
  defensive than the redirect precedent and within the same idiom. T's contract parenthetical named
  `badcamp_sponsors_form_node_coupon_form_alter` (the FORM_ID variant) while naming the hook
  `hook_form_NODE_FORM_alter`; F implemented the base-form variant, which the bundle guard makes
  behaviorally identical and which is the variant redirect ships — implementation latitude under
  the pinned behavioral contract (T's test pins element/config behavior, not the function name).
- **Form-display config minimal:** yes. 5 lines under `content:` (weight 0, `region: content`,
  empty `settings`/`third_party_settings`, no `type` key) — the correct widget-less extra-field
  shape, the same shape `url_redirects` takes in exported form displays; accepted by the Kernel
  config-schema checker on install, suite OK 41/402, phpcs 0/0 per T's GREEN section. `hidden:`
  untouched.
- **Nothing else crept in:** confirmed. Diff = 2 production files (`badcamp_sponsors.module` +45,
  coupon form display +5), 1 test file (T's RED-authored Kernel test), and 3 run docs
  (handoff-F/handoff-T-green sections + decision-journal entries). No composer change, no schema
  addition, no service/ yiplugin/ element registrations, no edits outside the module, no second
  visibility path (core's `EntityFormDisplay::processForm()` governs placement of the declared
  field — F verified in-source and the test pins the shown-component config).

### Findings

None. There was no pre-existing ID-on-form mechanism to extend (S's finding established the
absence, including "no hook_form_alter exists"); the rework adopted the platform's canonical seam
for exactly this need, pattern-matched to the contrib precedent on disk, with no new mechanism
classes to maintain. Rounds 1-2 findings and the warns routed to S are not re-litigated. The
pre-existing `web/robots.txt` worktree residue is unchanged and still O's pre-merge triage item.
