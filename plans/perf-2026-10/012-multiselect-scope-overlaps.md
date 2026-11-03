# Plan 012: Multiselect scope: one Stache index pass for all selected values

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving to the
> next step. If anything in the "STOP conditions" section occurs, stop and
> report — do not improvise. When done, update the status row for this plan
> in `plans/perf-2026-10/README.md`.
>
> **Drift check (run first)**: `git diff --stat 8096a0b..HEAD -- src/Scopes/Multiselect.php tests/Feature/MultiselectScopeSemanticsTest.php`
> This plan was written on the uncommitted `feature/dynamic-range-bounds` working tree
> (file list in `plans/perf-2026-10/reference/working-tree-status-at-review.txt`). If
> `src/Scopes/Multiselect.php` changed beyond that branch landing, compare the "Current
> state" excerpt against the live code before proceeding; on a mismatch, STOP.

## Status

- **Priority**: P2
- **Effort**: S
- **Risk**: LOW
- **Depends on**: none
- **Category**: perf
- **Planned at**: commit `8096a0b` + uncommitted dynamic-range-bounds work, 2026-10-21
- **Implements / refines**: perf review 2026-10 finding #6 (verifier V5, finding A2). Not one of PERF-01…PERF-08 in `plans/README.md`; adjacent to CORRECTNESS-08 there (`Multiselect` turning an empty value into `orWhereJsonContains($field, '')`), which this plan does not touch. The public docs already deprecate this scope in favour of Statamic's `overlaps` condition (`advanced/query-scopes.md:37`); this change gives the scope the same Stache semantics as that condition for the sites still using it.

## Why this matters

`Multiselect::apply()` wraps one `orWhereJsonContains($field, $value)` per selected value in a `where` closure. On the Stache builder every `where*` clause is evaluated by a filter method over the full value index of the field (`vendor/statamic/cms/src/Stache/Query/Builder.php:311`, `filterWhereJsonContains`), so k selected values cost k passes over N index rows plus the OR merge of k key sets. `whereJsonOverlaps($field, $values)` (`Stache/Query/Builder.php:346`, `filterWhereJsonOverlaps`) answers the same question in one pass with a single `array_intersect` per row. The query results are identical for array-stored values; the only semantic difference is the scalar case documented below.

All numbers: testbench on the addon's own vendor (Livewire 4.2.1 / Statamic 6.7.1, Stache driver, array cache), N = 3,000 entries in one collection, a `checkboxes` field with six options, the real `Entries` tag with `query_scope="multiselect"`, 5 timed repetitions after one warm-up, no addon config flags involved. Verifier run in `reference/012/bench/before-multiselect-N3000.txt` and `after-multiselect-N3000.txt`; plan-writer re-run on the clean copy in `after-multiselect-N3000-planwriter-run.txt`.

| Selected values | `count()` before → after | `paginate(12)` before → after | Result count (identical) |
|---|---|---|---|
| 1 | 4.1 → 4.7 ms | 18.8 → 19.2 ms | 1,019 |
| 3 | 6.7 → 4.8 ms | 37.4 → 33.5 ms | 2,268 |
| 6 | 10.9 → 4.8 ms | 53.7 → 41.9 ms | 3,000 |

The `count()` column is what the faceted-count path (`enable_filter_values_count`) and Plan 014's per-click recount pay per multiselect filter; it becomes flat in the number of values. The `paginate(12)` column still carries the collection's default title sort (Statamic core, untouched), which is why it does not drop as far.

## Current state

`src/Scopes/Multiselect.php:17-34` — the whole scope:

```php
    public function apply($query, $values)
    {
        collect($values)->filter(function ($value, $key) {
            return Str::startsWith($key, 'multiselect:');
        })->mapWithKeys(function ($value, $key) {
            $newKey = Str::after($key, 'multiselect:');

            return [$newKey => explode('|', $value)];
        })->each(function ($values, $field) use ($query) {
            $query->where(function ($query) use ($field, $values) {   // line 26
                foreach ($values as $value) {
                    $query->orWhereJsonContains($field, $value);       // line 28: one index pass per value
                }
            });
        });

        return $query;
    }
```

Statamic's two Stache filters (installed 6.7.1; verifier confirmed the same bodies in the demo app's 6.21):

- `filterWhereJsonContains` (`Stache/Query/Builder.php:311-320`): `if (! is_array($value)) return false;` then `count(array_intersect($value, $where['values'])) == count($where['values'])`. A scalar-stored value never matches.
- `filterWhereJsonOverlaps` (`:346-359`): null value or null needle → false; scalar vs scalar → `===`; otherwise `! empty(array_intersect(Arr::wrap($value), $where['values']))`. A scalar-stored value is treated as a one-element set.

`whereJsonOverlaps` is declared on `vendor/statamic/cms/src/Query/Builder.php:320` and on `EloquentQueryBuilder.php:272`. The addon requires `statamic/cms: ^5.0 || ^6.0` (`composer.json`); the method exists from Statamic 5.64.0, so a 5.x install below that would throw `BadMethodCallException` without a guard.

Repo conventions: the scope is exercised through `Facades\Entry::query()` in tests (PHPUnit, `#[Test]`, `EntryFactory`); style via `vendor/bin/pint --dirty --format agent`.

## Scope

**In scope** (the only files you should modify):
- `src/Scopes/Multiselect.php`
- `tests/Feature/MultiselectScopeSemanticsTest.php` (new)

**Out of scope** (do NOT touch):
- `src/Http/Livewire/Traits/HandleParams.php` `handleQueryScopeCondition()` and the `query_scope` registry: how the scope is *selected* is unchanged.
- The empty-value case (`multiselect:field=''` → CORRECTNESS-08 in `plans/README.md`): a separate finding; `explode('|', '')` still yields `['']` and `whereJsonOverlaps($field, [''])` matches nothing, same as before.
- Statamic's `overlaps` condition and the deprecation box in the docs: this plan does not remove or rename the scope.
- Any Eloquent-specific optimisation (`JSON_OVERLAPS`): untested, deliberately excluded.

## The change

1. **`src/Scopes/Multiselect.php`** — import `Statamic\Stache\Query\Builder as StacheBuilder`. Inside the `each` callback, before the existing `where` closure, add: if `$query instanceof StacheBuilder && method_exists($query, 'whereJsonOverlaps')`, call `$query->whereJsonOverlaps($field, array_values($values))` and `return`. Leave the nested `orWhereJsonContains` form as the fallback for every other builder (Eloquent, older Statamic, custom repositories). Extend the method docblock with the two-sentence rationale and the scalar note.
2. **`tests/Feature/MultiselectScopeSemanticsTest.php`** — new file, copied from `reference/012/tests/Feature/`.
3. **Docs** — see "Compatibility & docs".

Reference implementation: `plans/perf-2026-10/reference/012/012-multiselect-overlaps.diff` (apply from the addon root with `git apply` or `patch -p1`; it was generated against the working tree at review time). Pint reformats the docblock's `@param` to an imported `Builder` type; that is in the diff.

Rejected variants (details in "What verification refuted"): reviewer A's bare `instanceof` check without `method_exists`; and using `whereJsonOverlaps` on the Eloquent path too.

## Tests to add

`tests/Feature/MultiselectScopeSemanticsTest.php` (namespace `Reach\StatamicLivewireFilters\Tests\Feature`, class `MultiselectScopeSemanticsTest`). Fixture: a `products` collection with six entries covering array-stored, scalar-stored, null, missing and numeric values. Each test applies the scope to `Facades\Entry::query()` and compares sorted slugs.

| Test | Proves |
|---|---|
| `array_stored_values_match_any_selected_value` | OR semantics across selected values on array fields (and the scalar-stored entry is now included). **Fails on current code.** |
| `scalar_stored_values_match_the_multiselect_scope` | A `select` with `max_items: 1` (scalar) matches; a non-matching scalar does not. **Fails on current code** (0 rows before). |
| `null_and_missing_values_never_match` | `null` and absent fields stay excluded, as with Contains. |
| `numeric_strings_and_integers_compare_loosely_like_before` | `['1', 2]` matches `'1'` and `'2'` (`array_intersect` compares as strings in both filters). |
| `multiple_multiselect_fields_are_anded_together` | Two `multiselect:*` params AND; unrelated params are ignored. **Fails on current code** because the scalar `brand` leg never matched. |
| `non_stache_builders_keep_the_nested_or_where_json_contains_path` | A fake non-Stache builder receives `where(closure)` + `orWhereJsonContains` per value and never `whereJsonOverlaps`. |

The three "fails on current code" results are the verifier's record of the behaviour change (`reference/012/bench/verifier-tests-on-current-code.txt`). No existing test changes; the suite grows from 406 to 412.

## Verification

1. Copy the test file in, run it on the **unchanged** scope: `vendor/bin/phpunit --filter MultiselectScopeSemanticsTest` → exactly the 3 tests marked above fail, the other 3 pass. If all 6 pass, STOP (see below).
2. Apply the scope change. `vendor/bin/phpunit --filter MultiselectScopeSemanticsTest` → `OK (6 tests, 13 assertions)`.
3. `vendor/bin/phpunit` → `OK (412 tests, 1281 assertions)` (clean-copy count at review time; with other perf plans landed, expect 406 + 6 + their additions).
4. `vendor/bin/pint --dirty --format agent` → `{"result":"pass"}` or a fix on the two touched files only.
5. Bench (optional, ~2 min): copy `reference/012/bench/CollectionBenchTest.php` to `tests/Bench/`, run `BENCH_N=3000 vendor/bin/phpunit --filter it_benchmarks_the_multiselect_scope tests/Bench/CollectionBenchTest.php`, read `.bench-results-array-it_benchmarks_the_multiselect_scope.txt` in the addon root. Expect `count()` at 1/3/6 values ≈ 4–5 ms flat (before: 4.1/6.7/10.9 ms) and `paginate(12)` at 6 values ≈ 39–42 ms (before: 53.7 ms), counts 1019/2268/3000; ±25 % machine noise is normal, the shape (flat count, identical totals) is what matters. Then delete `tests/Bench/`, `.bench-results-array-*.txt`, `.bench-sample.html` and `tests/__fixtures__/dev-null-bench-array/` before taking the suite count again.

Browser check (optional): on the demo app's `/advanced` page tick several "Special categories" (a `multiselect` query-scope filter); the result set must be identical before and after, and the `transmission` select (a `max_items: 1` field) is unaffected because it uses the `is` condition, not this scope.

## Done criteria

ALL must hold:

- [ ] `src/Scopes/Multiselect.php` takes the `whereJsonOverlaps` path only behind `instanceof StacheBuilder && method_exists(...)`; the nested `orWhereJsonContains` form is still present as the fallback.
- [ ] `tests/Feature/MultiselectScopeSemanticsTest.php` exists with the six tests; the three marked ones were seen failing before the change and passing after.
- [ ] `vendor/bin/phpunit` exits 0 (412 tests on the review-time tree) and `vendor/bin/pint --dirty --format agent` exits 0.
- [ ] Only the two in-scope files are modified (`git status`); `tests/Bench/` and bench artifacts are not committed.
- [ ] Changelog line added; docs sentence handed to the maintainer; `plans/perf-2026-10/README.md` status row updated.

## STOP conditions

- Step 1 shows all 6 new tests passing on the unchanged scope: the scalar behaviour described here does not reproduce on the installed Statamic, so the semantics analysis is stale. Report before changing anything.
- After the change, `null_and_missing_values_never_match`, `numeric_strings_and_integers_compare_loosely_like_before` or `non_stache_builders_keep_the_nested_or_where_json_contains_path` fails: Statamic's `filterWhereJsonOverlaps` body differs from the one quoted above (`Stache/Query/Builder.php:346`); re-read it and report.
- `grep -n "function whereJsonOverlaps" vendor/statamic/cms/src/Query/Builder.php` finds nothing: the guard will silently keep the old path and the bench numbers will not reproduce. Report.
- Any `LivewireCollectionComponentTest` or `Tags` test fails after the change (the scope is reachable through `query_scope="multiselect"` on the tag).
- The live `src/Scopes/Multiselect.php` no longer matches the excerpt (for example CORRECTNESS-08 was fixed in the meantime); re-base the hunk by hand, keep the guard, and note it in the status row.

## What verification refuted

Nothing in this finding was refuted; two requirements were added by verification and must stay:

- **Version guard.** Reviewer A's prototype used `if ($query instanceof \Statamic\Stache\Query\Builder)` alone. `whereJsonOverlaps` exists only from Statamic 5.64.0 and the addon allows `^5.0`, so the `method_exists` check is mandatory. Do not "simplify" it away.
- **Scalar values now match.** With `orWhereJsonContains` a field stored as a scalar (e.g. a `select` with `max_items: 1`, like the demo's `transmission`) returned zero rows through this scope; with `whereJsonOverlaps` it matches as a one-element set. The verifier recommends keeping this (it is the behaviour users expect and the one Statamic's `overlaps` condition has) and documenting it. If the maintainer wants the old zero-row behaviour preserved, the scope would need a `whereJsonLength`/type pre-filter and the gain disappears; that is a product decision, not an executor's.
- **Eloquent stays on the nested form.** Laravel's `whereJsonOverlaps` compiles to `JSON_OVERLAPS`, which needs MySQL ≥ 8.0.17 and is unsupported on SQLite/PostgreSQL grammars. The Eloquent path was not measured and must fall back to current behaviour.

## Compatibility & docs

- **Published views**: none involved.
- **Config flags**: none. Behaviour is identical on `enable_filter_values_count` on/off; the count path simply gets faster.
- **Livewire 3 vs 4**: not involved (query layer only).
- **Stache vs Eloquent**: Stache takes the new path when the method exists; Eloquent and any other builder keep the current nested OR. Not measured on Eloquent.
- **Multi-site**: the scope filters by field value only; site scoping comes from the surrounding query. Unchanged.
- **Changelog line**: "Multiselect query scope: on the Stache the selected values are matched in one `whereJsonOverlaps` pass (Statamic ≥ 5.64) instead of one `orWhereJsonContains` per value; fields stored as a single value (e.g. `select` with `max_items: 1`) now match too. Eloquent unchanged."
- **Public docs** (sibling repo `/Volumes/1TB/Sites/livewirefilters-docs`, read-only for this plan; hand the text to the maintainer): `advanced/query-scopes.md:43` says the scope "magically works using `whereJsonContains`" — change to "uses `whereJsonOverlaps` on the Stache (one pass for all selected values; single-value fields match too) and `whereJsonContains` on other drivers". The deprecation box at `:37` stays.
