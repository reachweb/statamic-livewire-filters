# Plan 008: Share resolved key sets and drop the sort in faceted count and bounds queries

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving to the
> next step. If anything in the "STOP conditions" section occurs, stop and
> report — do not improvise. When done, update the status row for this plan
> in `plans/perf-2026-10/README.md`.
>
> **Drift check (run first)**: `git diff --stat 8096a0b..HEAD -- src/Support/CountEntries.php src/Support/CountQueryPool.php src/Http/Livewire/Traits/HandleFieldBounds.php src/Http/Livewire/Traits/HandleFieldValuesQuery.php src/Support/RangeBoundsQuery.php tests/Feature/CountEntriesTest.php tests/Feature/HandleFieldBoundsTest.php`
> This plan was written on the uncommitted `feature/dynamic-range-bounds` working tree
> (file list in `plans/perf-2026-10/reference/working-tree-status-at-review.txt`), so
> those files are expected to differ from `8096a0b` by exactly that branch work. Compare
> the "Current state" excerpts against the live code before proceeding; on a mismatch,
> treat it as a STOP condition.

## Status

- **Priority**: P1
- **Effort**: M
- **Risk**: MED
- **Depends on**: none
- **Category**: perf
- **Planned at**: commit `8096a0b` + uncommitted dynamic-range-bounds work, 2026-10-21
- **Implements / refines**: PERF-06 (README "Non-island findings") and PERF-01 (README "Recorded, not yet planned"); perf review 2026-10 finding #1 (verifier V2, counts pool). PERF-06's own prototype reached a protected Stache method through `Closure::bind` and had no Eloquent path; this plan uses the public `Stache::store('entries')->getItemValues()` seam and falls back to the current per-field `pluck()` on Eloquent.

## Why this matters

With `enable_filter_values_count` or `enable_dynamic_range_bounds` on, every `params-updated` event fans out to one listener per filter (`HandleEntriesCount::updateCounts`, `HandleEntriesCount.php:37`; `HandleFieldBounds::updateBounds`, `HandleFieldBounds.php:49`). Each listener asks `CountQueryPool::getFieldValues()` for its own field, and three things make that expensive on the Stache. (1) The pool's cache key includes the field handle (`CountQueryPool.php:35`), so eight listeners with identical base params are eight misses, and the bounds trait passes `$baseParams['from']` plus params that still contain `from` (`HandleFieldBounds.php:246`, merged in by `RangeBoundsQuery::decodeForCollection`, `RangeBoundsQuery.php:73`) while the count trait passes `$this->collection` and params without `from` (`HandleFieldValuesQuery.php:68`), so counts and bounds never share a key. (2) Every miss runs `Entries::query()`, which applies the collection's default sort (`vendor/statamic/cms/src/Tags/Collection/Entries.php:193` → `Tags/Concerns/QueriesOrderBys.php:12-23`, default `sortField:sortDirection` at `Entries.php:247-253`); a Stache `pluck()` then goes `resolveKeys()` → `orderKeys()` → `DataCollection::multisort` over every matching key (`vendor/statamic/cms/src/Stache/Query/Builder.php:25-41, 75-110`). At N=5,000, 60 ms of a 62 ms no-filter pluck is sorting values nobody reads. (3) Date bounds call `Carbon::parse` per value (`HandleFieldBounds.php:250-257`) although the Stache value index already holds Carbon instances.

The fix resolves the matching key set once per distinct (collection, base params) per request, reads each field from the Stache index with the same call Statamic's own `pluck()` makes (`Builder.php:38` → `AggregateStore::getItemValues`, `Stache/Stores/AggregateStore.php:68`), and drops the `ORDER BY` unless `limit`/`offset` window the sorted set.

All numbers: Stache driver, testbench on the addon's vendor (Livewire 4.2.1 / Statamic 6.7.1), one request fanned out to 8 `params-updated` listeners (6 count filters + 2 range filters), N=5,000 entries, both features on, bench in `reference/008/bench/`.

| Interaction (N=5,000, counts+bounds on) | Before | After | Stache filter passes |
|---|---|---|---|
| mount, 0 active filters | 536 / 540 ms | 64 / 67 ms | 8 → 1 |
| 1 active (`color=red`) | 191 / 190 ms | 43 / 45 ms | 8 → 2 |
| 2 active (`color=red`, `transmission=manual`) | 124 / 132 ms | 46 / 46 ms | 8 → 3 |
| both features off (Livewire-only, 8 listeners) | 7.9 ms | 7.9 ms | 0 |

| Per pool call, 1 active filter | Before | After |
|---|---|---|
| the active field's own query (its filter stripped ⇒ full-collection sorted pluck) | 64.7 ms | 13.9 ms (key set) |
| first other field | 15.7 ms | 9.2 ms (second key set) |
| each of the six remaining fields | 15.3–15.9 ms | 1.5–1.7 ms (index read) |
| raw Stache `pluck('transmission')`, no filter: `orderBy(title)` vs `reorder()` | 62.5 ms | 7.8 ms |
| date values → timestamps, 5,000 Carbon index values | 3.5 ms (`Carbon::parse`) | 0.2 ms (`getTimestamp()`) |

The counts and `dynamicMin`/`dynamicMax`/`fieldMin`/`fieldMax`/`disabled` were byte-identical before and after for all three interactions (`RESULT` lines in the two bench outputs).

## Current state

**Pool keyed by field, `from` handled inconsistently.** `src/Support/CountQueryPool.php:13-38`:
```php
    public function getFieldValues(string|array $collection, array $baseParams, string $fieldHandle): Collection
    {
        $cacheKey = $this->cacheKey($collection, $baseParams, $fieldHandle);   // :15
        ...
        $params = Parameters::make(
            array_merge($baseParams, ['from' => $collection]),                 // :22 — $collection wins
            Context::make([])
        );

        return $this->fieldValuesCache[$cacheKey] = (new CountEntries($params))->pluck($fieldHandle);   // :26
    }

    protected function cacheKey(string|array $collection, array $baseParams, string $fieldHandle): string   // :29
    {
        ksort($baseParams);

        return md5(serialize([
            'collection' => $collection,
            'field' => $fieldHandle,          // :35 — field in the key
            'params' => $baseParams,          // :36 — bounds callers leave `from` inside
        ]));
    }
```

**Callers.** Counts: `src/Http/Livewire/Traits/HandleFieldValuesQuery.php:66-69` passes `$this->collection, $baseParams` (no `from`). Bounds: `src/Http/Livewire/Traits/HandleFieldBounds.php:244-248`:
```php
    protected function computeFieldBounds(array $baseParams, string $fieldHandle): array
    {
        $values = app(CountQueryPool::class)->getFieldValues($baseParams['from'], $baseParams, $fieldHandle)   // :246
            ->flatten()
            ->filter(fn ($value) => $value !== null && $value !== '');

        if (($this->statamic_field['type'] ?? null) === 'date') {                 // :250
            $values = $values->map(function ($value) {
                try {
                    return Carbon::parse($value)->timestamp;                          // :253
                } catch (\Throwable $exception) {
                    return null;
                }
            })->filter(fn ($value) => $value !== null);
```
`$baseParams['from']` comes from `src/Support/RangeBoundsQuery.php:73`: `'params' => array_merge($query['params'], ['from' => $collections])`.

**Sorted count query.** `src/Support/CountEntries.php:14-40`:
```php
    public function pluck(string $column): Collection
    {
        try {
            $query = $this->query();                       // :17 — Entries::query(), default sort applied

            if ($limit = $this->params->int('limit')) {    // :19-25 — limit/offset only
                $query->limit($limit);
            }

            if ($offset = $this->params->int('offset')) {
                $query->offset($offset);
            }
            ...
            if ($query instanceof EloquentQueryBuilder && ! $this->isMultisiteCollection()) {   // :32
                return $this->eloquentPluck($query, $column);
            }

            return $query->pluck($column);                 // :36
        } catch (NoResultsExpected $exception) {
            return collect();
        }
    }
```

**Statamic seams relied on (vendor, Statamic 6.7.1).** `Statamic\Facades\Stache::store('entries')` is an `EntriesStore extends AggregateStore`; `AggregateStore::getItemValues($keys, $valueIndex, $keyIndex)` (`Stache/Stores/AggregateStore.php:68-78`) is public, splits `collection::id` keys per child store and `flatMap`s the per-store index reads; `AggregateStore::store($handle)` (`:17`) returns the `CollectionEntriesStore`, whose public `paths()` (`Stache/Stores/Store.php:298`) is the id → path index that is already loaded for every query. `Statamic\Query\Builder::reorder()` (`Query/Builder.php:104`) clears `orderBys`; `EloquentQueryBuilder::reorder()` (`Query/EloquentQueryBuilder.php:494`) forwards to Eloquent. The Stache `Value` index stores `(new ResolveValue)($entry, $field)` (`Stache/Indexes/Value.php:16-19`), which for entries ends in `Entry::value()` with origin fallback (`Data/HasOrigin.php:49-61`), so localized entries' inherited values are already in the index.

## The change

Files in scope: `src/Support/CountEntries.php`, `src/Support/CountQueryPool.php`, `src/Http/Livewire/Traits/HandleFieldBounds.php`, `tests/Feature/CountEntriesTest.php`, `tests/Feature/HandleFieldBoundsTest.php`, new `tests/Feature/CountQueryPoolTest.php`. Nothing else — no views, no config, no `HandleFieldValuesQuery.php`, no `RangeBoundsQuery.php`.

1. **`src/Support/CountEntries.php` — unsorted count query.** Extract a protected `countQuery()` that calls `$this->query()`, reads `limit`/`offset` from the params and, when neither is set, returns `$query->reorder()`; when either is set it applies them and keeps the ordering (they window the sorted set). `pluck()` uses `countQuery()` instead of its inline limit/offset block; the Eloquent fast path and the `NoResultsExpected` catch stay as they are. Rejected variant: unconditional `reorder()` — with `limit`/`offset` it would change which rows are counted (section 10).
2. **`src/Support/CountEntries.php` — key set and index read.** Add public `resolvedKeys(): ?Collection`: builds `countQuery()` (returning `collect()` on `NoResultsExpected`), returns `null` unless the builder is a `Statamic\Stache\Query\EntryQueryBuilder`, then plucks `id`. With one collection the keys are `"{$handle}::{$id}"`. With several, it builds an id → handle map from each collection's `Stache::store('entries')->store($handle)->paths()->keys()` and returns `null` if any id is unowned (so the caller falls back). Add public static `valuesForKeys(Collection $keys, string $column)` that returns `collect()` for an empty set and otherwise `Stache::store('entries')->getItemValues($keys, $column, null)->values()`, which is exactly what `Builder::pluck()` does once it has the keys. Rejected variant: a keyed `pluck('collectionHandle', 'id')` to learn each id's collection — it renumbers numeric ids (section 10).
3. **`src/Support/CountQueryPool.php` — share the key set.** At the top of `getFieldValues()`, `unset($baseParams['from'])` (the `$collection` argument wins the merge anyway, so a `from` inside the params may not split the caches). Make `cacheKey()`'s `$fieldHandle` nullable and add a `$keySetCache` keyed by `cacheKey($collection, $baseParams, null)`. On a field-cache miss: resolve (or reuse) the key set via `resolvedKeys()`; when it is `null` (Eloquent, or an unowned id) call `$entries->pluck($fieldHandle)` as today, otherwise `CountEntries::valuesForKeys($keys, $fieldHandle)`. The field-values cache stays, so repeated calls for the same field are still served from memory.
4. **`src/Http/Livewire/Traits/HandleFieldBounds.php` — date shortcut.** In `computeFieldBounds()`'s date branch, return `$value->getTimestamp()` when `$value instanceof \DateTimeInterface`, before the existing `Carbon::parse` fallback (which still serves Eloquent's strings). Nothing else in the trait changes; `boundsQueryRevisions` and the stale-event logic are untouched. Rejected variant: `strtotime()` — not value-identical to `Carbon::parse` (section 10).
5. **Tests** — section 7.

Reference implementation: `plans/perf-2026-10/reference/008/008-counts-pool-keyset-and-reorder.diff` (apply from the addon root with `git apply` or `patch -p1`; it was generated against the working tree at review time). The three final test files are also in `plans/perf-2026-10/reference/008/tests/Feature/` as full copies, byte-identical to the diff's result.

## Tests to add

New `tests/Feature/CountQueryPoolTest.php` (28 tests; fixtures: `cars`, `trucks`, a dated `news` collection, a `brands` taxonomy, a draft entry). Every value assertion compares the pool against an independent oracle that hydrates the entries through Statamic's own `Entries` tag and resolves each value with `ResolveValue`, i.e. what the index holds.
- `fields_sharing_base_params_share_one_filter_pass` — four fields with the same base params cost one Stache query (counted via `QueryExecuted` under `statamic.system.fake_sql_queries`); different base params cost another; an identical call costs none; values equal the oracle.
- `a_from_in_the_params_neither_splits_the_pool_nor_overrides_the_collection` — a bounds-style call (`from` inside the params) and a counts-style call share the key set; a foreign `from` (`trucks`, `cars|trucks`) cannot override the `$collection` argument.
- `pooled_values_equal_the_hydrated_query_values` (data provider, 21 cases) — single collection with array / null / Carbon date values; `a|b`, array and `not_from` multi-collection; `from="*"`; multiselect scope; taxonomy condition; draft and `any` status; dated collection hiding future, `show_future`, `any`; `sort=random`; explicit sort; `limit` and `offset` keep the sorted subset; no matches; `show_past=false` + `show_future=false` (NoResultsExpected). Each case also checks `CountEntries::pluck()` directly.
- `the_fixtures_exercise_the_cases` — guards the fixtures themselves (the limited subsets are the right entries, drafts/future entries really are excluded, null values and 6 multi-collection titles appear).
- `resolved_keys_are_collection_prefixed_for_every_queried_collection` — keys are `cars::…` and `trucks::…` for `cars|trucks`; empty set for an impossible query; `valuesForKeys(empty)` is empty.
- `numeric_entry_ids_resolve_through_the_key_set` — entries with ids `100`/`200` resolve to `nums::100`/`nums::200` and the right values, alone and mixed with `cars`.
- `localized_entries_keep_inherited_values_across_sites` — multi-site `bikes` with `fr` localizations: `fr` inherits `red` from its origin, overrides to `green`; `site=fr`/`en`/`*`/unset and a current-site switch all equal the oracle.
- `resolved_keys_are_not_attempted_on_an_eloquent_query` — an `EloquentQueryBuilder` mock receives `reorder()` and never `pluck`/`get`; `resolvedKeys()` is `null`.

`tests/Feature/CountEntriesTest.php` (+2):
- `it_drops_the_ordering_unless_limit_or_offset_select_the_rows` — `countQuery()` has no `orderBys` for plain and `sort=title:desc` params, keeps them with `limit` or `offset`, and applies the limit.
- `it_plucks_the_same_values_with_and_without_a_sort_and_keeps_limited_subsets_ordered` — `sort=random`, `sort=title:desc`, and limited/offset subsets pluck the expected values.

`tests/Feature/HandleFieldBoundsTest.php` (+1):
- `date_bounds_are_identical_whether_the_values_arrive_as_carbon_or_strings` — the same `fieldMin`/`fieldMax`/`dynamicMin`/`dynamicMax` for strings (Eloquent), Carbon in the app timezone (Stache index), Carbon in other timezones, `DateTimeImmutable`, and a mix with `null`/`''`/junk.

Existing tests that change, and why that is legitimate: the three Eloquent mock tests in `tests/Feature/CountEntriesTest.php` (`it_uses_the_eloquent_fast_path_for_non_multisite_queries` :117, `it_falls_back_to_the_standard_pluck_for_multisite_eloquent_queries` :136, `it_preserves_inherited_values_when_a_later_queried_collection_is_multisite` :156) gain one line, `$builder->shouldReceive('reorder')->once()->withNoArgs()->andReturnSelf();`. The mocked builder now receives the `reorder()` call that `countQuery()` makes before plucking; what each test asserts (fast path taken or skipped, column plucked, values preserved) is unchanged. Note `CountEntriesTest.php` already declares `namespace Tests\Feature;` in the repo; leave it, it is not this plan's concern.

## Verification

| Purpose | Command | Expected on success |
|---|---|---|
| Apply | `git apply plans/perf-2026-10/reference/008/008-counts-pool-keyset-and-reorder.diff` | exit 0; `git status` shows exactly the 6 in-scope files |
| New pool tests | `vendor/bin/phpunit --filter CountQueryPoolTest` | `OK (28 tests, …)` |
| Count entries | `vendor/bin/phpunit --filter CountEntriesTest` | `OK (9 tests, …)` |
| Bounds | `vendor/bin/phpunit --filter HandleFieldBoundsTest` | `OK (63 tests, …)` (data providers expand the 47+1 methods) |
| Full suite | `vendor/bin/phpunit` | `OK (437 tests, 1383 assertions)` on the tree at review state (406 before + 31 new); if other plans landed first, 437 plus their tests |
| Format | `vendor/bin/pint --dirty --format agent` | exit 0, no files changed (the diff is already Pint-clean) |

Bench re-run (one command each side; N=5,000 takes about 2 min of fixture setup plus 1 min; use `BENCH_N=2000` for a quick look). Run it **before** applying the diff for your own baseline, then after:
```
mkdir -p tests/Bench && cp plans/perf-2026-10/reference/008/bench/CountsPoolBenchTest.php tests/Bench/
BENCH_N=5000 vendor/bin/phpunit tests/Bench/CountsPoolBenchTest.php 2> bench-after.txt
rm -rf tests/Bench   # phpunit.xml sweeps ./tests, so remove it before any full-suite run
```
Expected in `bench-after.txt` versus `reference/008/bench/BEFORE-N5000-run1.txt` / `AFTER-N5000-run1.txt`: `ON p0` 536 → ~65 ms, `ON p1` 191 → ~44 ms, `ON p2` 124–132 → ~46 ms (absolute ms within ±30 % of the reference on another machine); `stacheQueries=8` for all three before, exactly `1`, `2`, `3` after; the pool call log shows two calls above 5 ms and six at 1–2 ms; the three `RESULT p0/p1/p2` lines must be byte-identical between your before and after runs (and match the reference files when the app timezone is UTC); `C4 … (min/max identical: yes)`.

Browser check (optional, demo app at the `/advanced` page with both flags on in `config/statamic-livewire-filters.php`): tick a checkbox, watch the third Livewire POST (the 8-listener fan-out) in the network tab; its server time drops and every count badge and slider range stays the same as before the change. With both flags off nothing in the request changes.

## STOP conditions

- The "Current state" excerpts do not match the live code — in particular `CountQueryPool::cacheKey(string|array, array, string)` at `:29`, the limit/offset block at `CountEntries.php:19-25`, or `$baseParams['from']` at `HandleFieldBounds.php:246`.
- `git apply` rejects any hunk.
- `AggregateStore::getItemValues()` or `Store::paths()` is no longer public, or `Stache::store('entries')->store($handle)` no longer returns a store with `paths()`, on the installed Statamic (check `vendor/statamic/cms/src/Stache/Stores/`).
- `fields_sharing_base_params_share_one_filter_pass` fails on the query count: Statamic's fake query logging (`statamic.system.fake_sql_queries`) changed and the sharing can no longer be proven.
- Any pre-existing test fails after the diff — report it, do not edit it. The only legitimate edits to existing tests are the three `reorder()` expectations above.
- The full suite reports fewer than 437 tests on the review-state tree.
- In the bench, any `RESULT` line differs between your before and after runs, or `stacheQueries` after is not `1`/`2`/`3`.

## What verification refuted

Do not "simplify" into any of these; each was built and observed to break.

1. **Reviewer C's FIX-B prototype (`NoSortCountEntries::resolvedKeys` returning `collect()` when `collections->count() !== 1`, and its pool treating an empty key set as "no values").** For a multi-collection tag (`from="a|b"`, an array, `not_from`, or `*`) every count was silently `0` — no exception, green suite, wrong numbers. The shipped `resolvedKeys()` maps every collection and returns `null` (fallback to `pluck()`) rather than an empty set when it cannot; the `multi collection …` and `all collections` provider cases guard it.
2. **Keyed Stache pluck to learn each id's collection (`pluck('collectionHandle', 'id')`).** `AggregateStore::getItemValues` `flatMap`s the per-store results (`AggregateStore.php:77`); `flatMap` collapses with `array_merge`, which renumbers integer-like keys, so numeric entry ids (`100`, `200`) became `0`, `1`, … and resolved to the wrong or no entries. Map ids through each store's `paths()` index instead; `numeric_entry_ids_resolve_through_the_key_set` guards it.
3. **`strtotime()` instead of `Carbon::parse` in the date branch.** Not value-identical: `Carbon::parse` accepts an int `1700000000` and returns "now" for `''`/`null`, while `strtotime` returns `false` or cannot take an int (table in `reference/008/bench/c4-strtotime-vs-carbon.txt`). Only the `DateTimeInterface::getTimestamp()` shortcut is identical for every input, and it alone gives the whole 3.5 → 0.2 ms; `date_bounds_are_identical_whether_the_values_arrive_as_carbon_or_strings` guards it.
4. **Unconditional `reorder()`.** With `limit`/`offset` the ordering decides which rows are counted; `it_drops_the_ordering_unless_limit_or_offset_select_the_rows` and the `limit keeps the sorted subset` / `offset keeps the sorted subset` cases guard it. (These params only reach the count query as trusted tag params.)
5. **PERF-06's `Closure::bind` into a protected Stache method.** Not a breakage but a coupling to internals with no Eloquent path; the public `getItemValues()` seam is what Statamic's own `pluck()` calls, so there is no second code path to drift.

Caveats recorded, not fixed here: a query scope that itself calls `->limit()` would now limit an unsorted key set (edge; flag to the maintainer if a user reports it); `sort=random` leaves the Stache `randomize` flag on (`reorder()` clears `orderBys` only), which is a cheap shuffle with identical values, while Eloquent's `reorder()` drops `RAND()` too; counts and bounds share a key set only when both pass the same collection value (single-collection tags: the count trait passes the filter's `collection` string, the bounds trait the token's `collections`); the Eloquent driver is covered by mocks only — its real behaviour is the current per-field `pluck()` plus a dropped `ORDER BY`.

## Compatibility & docs

- **Published views**: none change. The counts shape `statamic_field['counts']` and the `range-bounds-updated` payload are unchanged.
- **Config flags**: no new flags. Both features stay opt-in and off by default; when off, the listeners return before touching the pool, so nothing changes.
- **Livewire 3 vs 4**: no Livewire API involved; `CountQueryPool` is a scoped container service.
- **Stache vs Eloquent**: Stache is the measured path. On Eloquent `resolvedKeys()` returns `null` and the pool falls back to today's per-field `pluck()` (Eloquent JSON fast path and multisite fallback intact); the only Eloquent change is `reorder()` on the count query, which drops the unused `ORDER BY`. Untested on a real Eloquent driver — say so in the PR.
- **Multi-site**: the Value index stores `Entry::value()` (origin fallback), so inherited values are indexed; keys are `collection::id` per localized entry; covered by `localized_entries_keep_inherited_values_across_sites` for `site=fr/en/*`, unset, and a current-site switch.
- **Changelog line** (docs repo `changelog.md`, "Changed" under the next version): "Faceted counts and dynamic range bounds now resolve the matching entries once per request and read each field straight from the Stache index, instead of running one sorted query per filter. With 8 filters on a 5,000-entry collection the counts request drops from about 540 ms to 65 ms; counts and bounds are unchanged. On the Eloquent driver only the unused `ORDER BY` is dropped."
- **Docs page**: `installation.md` in `/Volumes/1TB/Sites/livewirefilters-docs` — the `enable_filter_values_count` row (line 34) says the feature "performs a query for each filter on the page"; change to one filter pass per distinct filter state per request, with the remaining cost being the index read per field. `enable_dynamic_range_bounds` has no row yet (the branch is unreleased); when it is added, carry the same note.
