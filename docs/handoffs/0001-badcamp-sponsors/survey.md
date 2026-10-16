# Survey: #1 badcamp_sponsors (run 0001-badcamp-sponsors)

Surveyed 2026-10-16 by O. All facts verified against the working tree or the running site
(unless marked "from the issue").

## Site shape (verified on the running DDEV site)

- Drupal core **11.4.10** (Drupal CMS 2.2.5 project), PHP 8.4.22, Drush 13.8, MySQL (DDEV `db`).
- The site DB was **empty at run start**; installed this run per `README.md` ("Installation
  options", Drush path): `ddev composer require drupal/drupal_cms_starter
  --update-with-all-dependencies` (recipe-unpack left `composer.json` unchanged; `recipes/` is
  gitignored) then `ddev drush site:install /var/www/html/recipes/drupal_cms_starter --yes`.
- Content types: **`page`** only. Workflow: **`basic_editorial`** (governs `page`).
- Roles: anonymous, authenticated, content_editor, administrator. No `sponsorship_manager`.
- Menus present: account, **admin**, content, footer, main, navigation-user-links, tools,
  top-tasks. The core Navigation module renders admin-menu children.
- Contrib enabled (relevant): pathauto (with token), simple_sitemap 4.x, metatag, **trash 3.1.1**,
  scheduler, scheduler_content_moderation_integration, views_ui, gin (admin theme), navigation.
- `views_data_export` / `csv_serialization`: **absent** — not in `composer.json`, not enabled.
- Config sync directory: `web/sites/default/files/sync` (DDEV default; not in git). The repo has
  **no committed config/**; modules ship their own `config/install`.
- `web/modules/custom/` and `web/themes/custom/` are **empty** — `badcamp_sponsors` is this
  repo's first custom module. There is no in-repo analogous code to extend.
- Tests run via `scripts/run-tests.sh` (PHPUnit 11.5 inside DDEV; `phpunit.xml` at repo root;
  first run in a fresh worktree does `composer install` inside it).
- `hook_module_preuninstall()` **exists in this core** (`web/core/lib/Drupal/Core/Extension/module.api.php:280`).
- Main-checkout tree is clean (an earlier scaffold drift on `web/robots.txt` was repaired by
  composer's scaffold refresh when the starter recipe was required).

## Reuse & Analogous-Feature map (extend, do not duplicate)

| Need | Closest existing thing | Plan |
|---|---|---|
| Content types + fields as shipped config | Core node config shapes: `web/core/modules/node/config/install/node.type.*.yml`, `field.storage.*`/`field.field.*`, `core.entity_form_display.*` | NEW config in `badcamp_sponsors/config/install` mirroring core shapes; enforced dependency on the module |
| Listing views with pager/tabs/exposed filters | Core `views.view.content` (`web/core/modules/node/config/optional/views.view.content.yml`); pathauto's shipped views | NEW three views; mirror core's display/field/plugin structure rather than inventing YAML |
| CSV display | `views_data_export` (to be required, `^1.10`, pulls `csv_serialization`) | NEW data-export display per view at `<path>/export.csv`; dependency declared in `.info.yml` |
| Uniqueness / redeemed-date rules | Entity Validation constraint plugins (`Drupal\Core\Validation\Annotation\ValidationConstraint`) | NEW constraints in `src/Plugin/Validation/Constraint`, pure logic in `src/` plain classes → Unit-testable |
| Delete blocking + coupon privacy | `hook_node_access()` (`web/core/modules/node/node.api.php`) | NEW hook impl; counting must be Trash-aware (trash 3.x soft-deletes via entity tables, so count with a storage query that includes trashed entities) |
| Title auto-generation | `hook_entity_presave()` | NEW hook; plain title-builder class → Unit-testable |
| Aliases | pathauto `pathauto.pattern.*` config (contrib shapes on disk) | NEW two patterns; coupon deliberately gets none |
| Sitemap exclusion | `simple_sitemap.bundle_settings.node.*` config | NEW settings setting index to false for the three types |
| Workflow exclusion | `basic_editorial` workflow config governs `page` only | Do not touch it; Kernel test asserts the types are never added |
| Role + menu | `user.role.*` config; `*.links.menu.yml` | NEW role config + module links menu plugin file (auto-removed at uninstall) |
| Uninstall cleanup | `hook_module_preuninstall()` | NEW hook: batch-delete all sponsor/campaign/coupon nodes (incl. trashed) before config goes |

Extend-vs-new verdict: **new module** (nothing in-repo to extend), but **reuse** every platform
mechanism above instead of parallel implementations. **No `hook_schema()`** and **no DB foreign
keys** (issue, settled).

## Where the work happens

- Production: `web/modules/custom/badcamp_sponsors/**`, `composer.json`, `composer.lock`.
- Tests: `web/modules/custom/badcamp_sponsors/tests/src/Unit` and `.../tests/src/Kernel`.
- Journal: `docs/handoffs/0001-badcamp-sponsors/`.
- Worktree: `.claude/worktrees/0001-badcamp-sponsors`, branch `issue-0001-badcamp-sponsors`.
