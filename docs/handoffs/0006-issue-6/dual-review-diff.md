## Implementation Review (Round 1)

### BLOCK findings

None.

### Needs-verification findings

**[NV-1]** `web/modules/custom/badcamp_sponsors/src/CouponDuplicateFinder.php:45-50` — The finder relies on trash's `entity_query_alter` to add a `notExists('deleted')` condition in the default context, making the query live-only without an explicit `deleted` condition. The file's docblock states "trash filters queries that carry no explicit 'deleted' condition to live entities in the default active context." This prompt does not contain the trash module's `entity_query_alter` source or documentation showing that a query originating in a node-access hook (which itself may run inside a trash context during restore operations) always executes in the default active context rather than an `ignore` context. Evidence that would settle it: the `trash` module's query alter implementation (e.g., `web/modules/contrib/trash/trash.module` or its service class) showing the context resolution for entity queries.

**[NV-2]** `web/modules/custom/badcamp_sponsors/badcamp_sponsors.module:68-82` — The restore branch reads `field_coupon_code`, `field_coupon_sponsor`, and `field_coupon_campaign` from `$entity` inside the node-access hook. The hook assumes the passed `$entity` is a fully loaded Node object with these fields populated (i.e., that trash invokes the access check on a loaded entity, not a stub or an entity loaded only with a subset of fields). The prompt does not contain trash's access-check invocation source. Evidence that would settle it: trash's implementation showing how it calls `$entity->access('restore', ...)` (e.g., whether the entity is loaded fresh in the `ignore` context, as the tests' `loadTrashed()` helper explicitly recreates).

**[NV-3]** `web/modules/custom/badcamp_sponsors/tests/src/Kernel/CouponRestoreAccessTest.php:206-211` — The test asserts `$second->access('restore', $account, TRUE)->isForbidden()` after the first coupon is restored, but the prompt does not show whether `Trash::restoreEntity($first)` actually re-trashes or modifies the second coupon, or whether trash's own restore operation invokes node access at all. The test relies on the production hook being invoked via `$entity->access()`; evidence that would settle it: trash's `restoreEntity` implementation showing whether restoring one entity performs any write that would invalidate or bypass the access check on the second (noting that a `resetCache` alone does not clear a `forbidden` result cached in the static result cache; it does, but only if the access result cache is per-entity and not keyed by the list tag alone).

**[NV-4]** `web/modules/custom/badcamp_sponsors/badcamp_sponsors.module:70` — The hook calls `CouponCodeNormalizer::normalize((string) ($entity->get('field_coupon_code')->value ?? ''))`. The prompt does not contain the `CouponCodeNormalizer::normalize()` implementation to confirm that it accepts an empty string and returns an empty string (so the `$code === ''` guard correctly handles missing code). Evidence that would settle it: `web/modules/custom/badcamp_sponsors/src/CouponCodeNormalizer.php` (or wherever the class lives), showing `normalize('')` returns `''` and does not, for example, throw or return a non-empty placeholder.

**[NV-5]** `web/modules/custom/badcamp_sponsors/src/Plugin/Validation/Constraint/CouponCodeUniqueConstraintValidator.php:35-39` — The validator keeps its "empty/missing-field guard" from the original (the diff does not show the guard being touched, so the code preceding the replaced query remains). The brief's AC 2 says the validator "keeps its empty/missing-field guard." The prompt's diff context shows only from the query replacement point forward; the guard lines are not visible. Evidence that would settle it: the full validator file `CouponCodeUniqueConstraintValidator.php` showing the guard still returns early before calling the finder.

### WARN findings

**[W-1]** `web/modules/custom/badcamp_sponsors/badcamp_sponsors.module:68-82` — The restore branch is not tagged with the individual trashed coupon's cache tags (e.g., `node:<id>`), only `node_list:coupon`. If a trashed coupon's own fields (code/sponsor/campaign) are edited while trashed, the forbidden/neutral result is not invalidated by that node's own edit. The brief specifies only `node_list:coupon`, so this matches spec, but it is a cache-correctness gap worth noting. Recommendation: consider adding `node:<id>` tags for the trashed entity, or document the limitation.

**[W-2]** `web/modules/custom/badcamp_sponsors/badcamp_sponsors.module:80` — The reason string is constructed inline with `sprintf(...)` containing the normalized code. The brief says "No new reason-builder class — the restore reason has one consumer (A decision 2)." If a future consumer needs the same wording (e.g., the bulk action or a second op), this duplication risks drift. Recommendation: acceptable per brief, but note the single-consumer assumption is fragile.

### NIT findings

**[NIT-1]** `web/modules/custom/badcamp_sponsors/tests/src/Kernel/CouponDuplicateFinderTest.php:71-73` — The test's docblock says "A padded code is stored trimmed" but the production code (finder) does not store anything; it only normalizes at query time. The comment is slightly misleading. Minor.

**[NIT-2]** `web/modules/custom/badcamp_sponsors/src/CouponDuplicateFinder.php:30` — The `@return int[]` PHPDoc says "ascending integer node IDs" and the implementation uses `sort($ids, SORT_NUMERIC)`. `sort` with `SORT_NUMERIC` on an array of integers works, but a default `sort()` would suffice and be slightly clearer. Minor.

### Verdict

PASS — no BLOCK findings; testing may proceed.