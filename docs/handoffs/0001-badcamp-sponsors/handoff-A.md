# Handoff-A: Phase 3 - badcamp_sponsors plan  (up-front plan review)

**Date:** 2026-10-16
**Branch:** issue-0001-badcamp-sponsors
**Brief reviewed:** docs/handoffs/0001-badcamp-sponsors/brief.md   **Reuse map:** docs/handoffs/0001-badcamp-sponsors/survey.md   **Wireframe:** N/A
**Verdict:** PASS

## Summary

PASS. The plan reuses every platform mechanism instead of building parallel paths: node content
types as config entities, core views/config shapes, `hook_module_preuninstall()` for cleanup,
entity-validation constraint plugins, and the node access-control layer for delete-blocking and
coupon privacy — all verified present in this tree at the exact layer the plan names. Nothing in
the brief or survey contradicts the issue's settled decisions. Five `warn` findings are precision
gaps in the plan documents (a stale hook citation, one wrong config-name shape, undeclared
`.info.yml` dependencies, and two trash seams that must be pinned so F cannot "fix" a failing
test with a non-platform workaround). None is drift today; all are cheap to correct in the brief
or Reuse map before T/F start.

## Findings

| # | Severity | Plan element | Drift dimension | Finding | Suggested fix |
|---|---|---|---|---|---|
| 1 | warn | AC 5 + "Decisions already made" + Reuse-map row "hook_node_access()" citing `node.api.php` | pattern consistency | The legacy per-node `hook_node_access()` does **not** exist in core 11.4: no `invokeAll('node_access')` for it, no `NODE_ACCESS_*` tri-state constants on `NodeInterface`, and `node.api.php` documents only `hook_node_access_records()`. The intended seam is real and correct — `hook_ENTITY_TYPE_access()` for `node` (hook string `'node_access'`), invoked at `EntityAccessControlHandler.php:100-101`, returning `AccessResultInterface`. A function literally named `badcamp_sponsors_node_access()` still receives the call, but under the modern contract. Reference implementation: node module's own `NodeEntityHooks::nodeAccess()` (`web/core/modules/node/src/Hook/NodeEntityHooks.php:119`). | Amend brief/survey: delete blocking + coupon privacy implement `hook_ENTITY_TYPE_access` (node) / `hook_entity_access` returning `AccessResultInterface` (docs: `entity.api.php:716,748`); never return `NODE_ACCESS_*` constants (removed). Note the seam's inherent `bypass node access` behavior (`NodeAccessControlHandler.php:94-114`) — admins bypass the delete block, which the issue's chosen mechanism implies. |
| 2 | warn | Reuse-map row "simple_sitemap.bundle_settings.node.*" | config shape | simple_sitemap 4.x reads/writes `simple_sitemap.bundle_settings.<variant>.<entity_type>.<bundle>` (`EntityManager.php:229,310`); the variant segment is missing from the plan's name. A file named without it installs but is never read, and the three types leak into the sitemap. | Correct shape: `simple_sitemap.bundle_settings.default.node.<bundle>.yml` (this site's default sitemap variant is `default`; F should confirm the variant key from `simple_sitemap.sitemap.*` present on the site). |
| 3 | warn | `.info.yml` dependency plan (brief names only `views_data_export`) | pattern consistency | The module ships `pathauto.pattern.*` and `simple_sitemap.bundle_settings.*` in `config/install`, so `pathauto` and `simple_sitemap` must be in `.info.yml` `dependencies` (or those files move to `config/optional`). It installs on THIS site only because both are coincidentally enabled; on any other site the config is silently skipped. Decide trash's status too (hard dep, or soft with `interface_exists`/service checks since delete-blocking must degrade gracefully). | Amend the brief's install section: dependencies = `node`, `pathauto`, `simple_sitemap`, `views_data_export` (+ trash as decided); state it in the Reuse-map rows. |
| 4 | warn | Uninstall plan: "delete every node (incl. trashed) before config goes" | wrong-layer risk | With trash enabled, `$node->delete()` **soft-deletes** (`TrashStorageTrait.php:63-100`): field data stays in `node__field_coupon_*`, so at config uninstall `FieldStorageConfig::preDelete()` takes the deleted-fields-repository path (`FieldStorageConfig.php:451-459`) and tables linger until cron `field_purge_batch` — failing AC 10's "no field tables" assertion and inviting a raw `db_drop_table()` workaround (real drift). The platform seam exists: `\Drupal::service('trash')->setTrashContext('ignore')` (or its scoped wrapper, `TrashManager.php:126-161`) makes storage `delete()` hard-delete (`TrashStorageTrait.php:65-68`). | Pin the seam in the brief's uninstall decision: preuninstall runs inside trash-context `ignore`, then batch-deletes (this also makes the synchronous table-drop path real, since `countFieldData()` then sees zero). |
| 5 | warn | Reuse-map row "counting must be Trash-aware … storage query" | pattern consistency | Correct idea, unnamed seam. Trash filters every entity query via `hook_entity_query_alter` **unless** the query already carries an explicit `deleted` condition (`TrashEntityHooks.php:86-88`); trash's own purger uses `condition('deleted', …)` (`TrashEntityPurger.php:59-62`). "Coupons referencing sponsor X incl. trashed" = live query + `exists('deleted')` query summed. | Pin the mechanism in the Reuse map so F doesn't reach for a raw SQL join: two storage queries split on the `deleted` base field. |

Also verified sound (no finding): `views.view.content.yml` exists as the mirror baseline and
views Page displays ship a `use_admin_theme` option that sets `_admin_route` on the route
(`views/src/Plugin/views/display/Page.php:110-111`) — the admin-theme requirement for `/sponsors`
pages is pure views config, no route subscriber needed; menu tabs/action links are native views
menu options / `*.links.action.yml` against `view.<id>.<display>` routes; the constraint plan
matches core placement (`user/src/Plugin/Validation/Constraint/` Constraint+Validator pairs);
presave title auto-build is standard; the Unit/Kernel split and `scripts/run-tests.sh` +
`phpunit.xml` exist as cited; uninstall of a module does not cascade to its dependencies
(`views_data_export` staying enabled is platform behavior).

## Notes for O

No BLOCK, so amendments are optional but recommended before T/F: fold findings 1-5 into
brief.md's decisions/evidence and survey.md's Reuse-map rows (one line each; the corrected hook
contract in #1 and the `setTrashContext('ignore')` seam in #4 are the two most likely to cause
rework if left stale). Minor: pathauto contrib ships no example `pathauto.pattern.*` on disk —
the pattern shape comes from `pathauto.src/Entity/Pattern.php` + its schema, not from a mirror
file; the survey's "contrib shapes on disk" slightly overstates for pathauto.

## Patterns referenced

1. `web/core/modules/node/src/Hook/NodeEntityHooks.php:119` with `web/core/lib/Drupal/Core/Entity/EntityAccessControlHandler.php:100-101` and `web/core/lib/Drupal/Core/Entity/entity.api.php:716,748` — the modern node/entity access hook contract.
2. `web/core/modules/node/config/optional/views.view.content.yml` with `web/core/modules/views/src/Plugin/views/display/Page.php:110-111` — views config shape and admin-theme page option.
3. `web/core/lib/Drupal/Core/Extension/module.api.php:280` (hook_module_preuninstall) with `web/core/modules/field/src/Entity/FieldStorageConfig.php:437-460` — uninstall ordering and field purge behavior.
4. `web/modules/contrib/trash/src/Hook/TrashEntityHooks.php:74-137`, `web/modules/contrib/trash/src/TrashStorageTrait.php:63-100`, `web/modules/contrib/trash/src/TrashManager.php:126-161` — trash query/load/delete seams.
5. `web/core/modules/user/src/Plugin/Validation/Constraint/` and `web/modules/contrib/simple_sitemap/src/Manager/EntityManager.php:229,310` — constraint placement and sitemap config naming.
