# Plan 011: Slim filter snapshots and resolve term/entry options from Stache indexes

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving to the
> next step. If anything in the "STOP conditions" section occurs, stop and
> report — do not improvise. When done, update the status row for this plan
> in `plans/perf-2026-10/README.md`.
>
> **Drift check (run first)**: `git diff --stat 8096a0b..HEAD -- src/Http/Livewire/LfCheckboxFilter.php src/Http/Livewire/LfRadioFilter.php src/Http/Livewire/LfDateFilter.php src/Http/Livewire/LfSort.php src/Http/Livewire/LfTags.php src/Http/Livewire/Traits/HandleFieldOptions.php src/Http/Livewire/Traits/HandleEntriesCount.php src/Http/Livewire/Traits/HandleStatamicQueries.php tests/Feature/LfCheckboxFilterTest.php tests/Feature/HandleEntriesCountTest.php`
> This plan was written on the uncommitted `feature/dynamic-range-bounds` working tree
> (file list in `plans/perf-2026-10/reference/working-tree-status-at-review.txt`). If any
> of these files changed beyond that branch landing, compare the "Current state" excerpts
> against the live code before proceeding; on a mismatch, STOP.

## Status

- **Priority**: P2
- **Effort**: M
- **Risk**: LOW-MED
- **Depends on**: none. Its hunks in `HandleEntriesCount.php` and the `Lf*` `#[Computed]` attributes sit next to Plan 010's and Plan 009's, so apply this plan **before** them (see the folder README for the order).
- **Category**: perf
- **Planned at**: commit `8096a0b` + uncommitted dynamic-range-bounds work, 2026-10-21
- **Implements / refines**: supersedes **PERF-03** in `plans/README.md` (the "needs payload measurement" is now measured); this review's finding #4 (F2, F3, F4 — verifier V1). F4 is rejected, see "What verification refuted".

## Why this matters

Every filter component carries its whole `statamic_field` array in the Livewire snapshot, and `HandleFieldOptions` always adds a `counts` key to it: one `null` per option, even when `enable_filter_values_count` is off and nobody will ever fill it. For an entries field with 1,000 options that key alone is 44 KB that rides along in every request and response of that component. Separately, five computed properties are declared `#[Computed(persist: true)]`. On Livewire 4.2.1 `BaseComputed::handleMagicGet()` turns every `$this->filterOptions` access into `Cache::remember('lw_computed.{componentId}.filterOptions', 3600)`, so each page view writes one cache entry per filter with a fresh component id, and the file driver never prunes them; the demo site's `storage/framework/cache/data` held about 160 day-old `lw_computed` files. For the four filters the persisted value is a copy of an already-hydrated `#[Locked]` property, so the cache buys nothing. For `LfTags::statamicFields` it was the only cross-request cache, which is why `persist` cannot simply be dropped: `getTaxonomyTerms()` and `getCollectionEntries()` hydrate every term and entry with `get()` just to read titles, 185 ms for 1,000 entries. Reading titles through the Stache `title`/`id`/`origin` indexes with `pluck()` removes that cost without any cache to invalidate.

All numbers below: testbench on the addon's vendor (Livewire 4.2.1 / Statamic 6.7.1), Stache driver, file cache store, 500 terms / 1,000 entries, Blink flushed before every page view. Output files are in `plans/perf-2026-10/reference/011/bench/`.

**F2 — snapshot bytes with `enable_filter_values_count` off** (`bench-BASE.txt` vs `bench-PLAN011.txt`):

| Component | Before | After |
|---|---|---|
| checkbox, entries field (1,000) | 97,619 B (`counts` key 44,015 B) | 53,594 B |
| checkbox, terms field (500) | 20,899 B | 12,482 B |
| select, blueprint options (5) | 821 B | 731 B |
| pooled `params-updated` request, 7 filters | 134,238 B | 78,466 B |
| same request with counts **on** | 134,238 B | 134,238 B (unchanged) |

With counts on the array is still materialised, so the demo site (counts on) sees no byte change from F2.

**F3 — persisted computed properties, 8-filter page view** (`bench-BASE-rerun.txt` vs `bench-PLAN011.txt`):

| Measure | Before | After |
|---|---|---|
| `lw_computed` cache writes per page view | 5 (79,078 B) | 0 |
| cache files added per page view / per 20 page views | +6 / +100 | +1 (not an `lw_computed` key) / +0 |
| `set('selected')` on the entries(1000) checkbox | 14.5 ms | 12.9 ms |
| page view mount+render, 8 filters | 262 ms | 71 ms |

**LfTags `tags-updated`, requests #1 / #2 / #3 / #4** (Blink flushed between them):

| Variant | #1 | #2 | #3 | #4 | persisted bytes |
|---|---|---|---|---|---|
| Before (persist on, `get()`) | 243.6 ms | 2.9 | 2.5 | 2.4 | 134,193 B per component |
| persist off, still `get()` — **do not ship** | 256.8 ms | 202.5 | 198.6 | 214.9 | 0 |
| This plan (persist off + pluck) | 54.0 ms | 7.2 | 7.0 | 6.9 | 0 |

Later requests cost about 4.5 ms more than the persisted read did, the first request costs 190 ms less, and nothing is written to the cache store.

**PLUCK — per page view mount cost** (`bench-BASE-rerun.txt` vs `bench-PLAN011.txt`):

| Mount | Before | After |
|---|---|---|
| checkbox, terms (500) | 34 ms | 5 ms |
| checkbox, entries (1,000) | 176 ms | 13 ms |
| 8-filter page view | 215 ms | 22 ms |

Output identity: single site, 3 sites with partial localizations and fallback titles, origin vs own entry ids, `title_format` collections, terms without a title, and every content-change attack (quiet saves, deletes, unpublish, a dated entry whose date passes) return exactly what the `get()` path returned (`multisite-PLAN011.txt`, and the `StacheIndexOptionsTest` cases below).

Demo site observation (headless browser on the demo app, Livewire 4.3.1 / Statamic 6.21, `php artisan serve`): with counts on the per-request bytes do not change; the gain there is the page-view mount cost and the end of the `lw_computed` file growth. Livewire 4.3.1 adds a per-request memo in front of the persisted read, so the writes are the part that matters on that version.

## Current state

**Counts array always materialised.** `src/Http/Livewire/Traits/HandleFieldOptions.php`:
- `addCountsArrayToConfig()` (`:72-80`) unconditionally merges `'counts' => array_fill_keys(array_keys($field->get('options')), null)` into the field config. It is called from `IsLivewireFilter::processFieldByType()` (`src/Http/Livewire/Traits/IsLivewireFilter.php:68`) for blueprint-option fields.
- `addCustomOptionsToConfig()` (`:87-99`) and `setFieldOptionsAndCounts()` (`:101-112`) each build their own `'counts' => array_fill_keys(...)` inline. The latter serves terms, entries and dictionary fields (`:20`, `:30`, `:50`); the former serves the `options` tag param (`IsLivewireFilter.php:53`).
- `initiateField()` then stores `$field->toArray()` into the `#[Locked] statamic_field` property (`IsLivewireFilter.php:47`), which is what the snapshot carries.

**The counts accessor.** `src/Http/Livewire/Traits/HandleEntriesCount.php:13-25`:
```php
    #[Computed]
    public function counts()
    {
        if (isset($this->statamic_field['counts']) && is_array($this->statamic_field['counts'])) {
            return $this->statamic_field['counts'];
        }

        if ($this->options !== null && is_array($this->options)) {
            return array_fill_keys(array_keys($this->options), null);
        }

        return [];
    }
```
`updateCounts()` (`:37-51`) already exits when the feature is off, and `updateCountsWithBatchQuery()` (`:55`) rebuilds `statamic_field['counts']` from `statamic_field['options']` on every count run, so the on-path never depends on the mount-time array. Stock views only read `$this->counts` (`resources/views/livewire/filters/lf-checkbox.blade.php:17`, `lf-checkbox-advanced.blade.php:5` seeds Alpine with `counts: @js($this->counts)`); nothing in `resources/views` or `src` reads `statamic_field['counts']` except this trait.

**Persisted computed properties** (all five are `#[Computed(persist: true)]`):
- `src/Http/Livewire/LfCheckboxFilter.php:26` `filterOptions()` and `src/Http/Livewire/LfRadioFilter.php:22` `filterOptions()` (`LfSelectFilter` extends `LfRadioFilter`). Both return `$this->options` or `$this->statamic_field['options']`, i.e. a Locked property that is already in the snapshot. `rules()` calls `$this->filterOptions()` as a method (`LfCheckboxFilter.php:63`, `LfRadioFilter.php:52`), which bypasses the attribute.
- `src/Http/Livewire/LfDateFilter.php:30` `filterOptions()` — two keys of `statamic_field`.
- `src/Http/Livewire/LfSort.php:35` `sortOptions()` — a Collection built from `getFieldNames()`.
- `src/Http/Livewire/LfTags.php:42` `statamicFields()` — resolves the blueprint and every terms/entries/dictionary field's options on each request; this is the one place persist was doing real work.

**Option resolution.** `src/Http/Livewire/Traits/HandleStatamicQueries.php`:
- `getTaxonomyTerms()` (`:15-32`): `Blink::once(...)` around `$taxonomy->queryTerms()->get()->unique(fn ($term) => $term->inDefaultLocale()->slug())->mapWithKeys(... $default->slug() => ($term->in($siteHandle) ?? $default)->title())`. `Term::in()` always returns a `LocalizedTerm` and `LocalizedTerm::title()` is `value('title') ?? slug()`, where `value()` falls back to the default locale, so an unlocalized term shows its default-locale title and a site the taxonomy is not configured for shows default-locale titles.
- `getCollectionEntries()` (`:34-63`): `Blink::once(...)` around `Entry::query()->where('collection', ...)->where('site', $siteHandle)->whereStatus('published')->get()->mapWithKeys(...)`, keyed by `$entry->origin()->id()` when `use_origin_id_for_entries_field` is on and the entry has an origin, else `$entry->id()`; value `$entry->title`.
- The custom-sort path `IsSortable::getTaxonomyTermsSortedBy()` (`src/Http/Livewire/Traits/IsSortable.php:100-121`) runs its own `queryTerms()->orderBy(...)->get()` and is **not** touched by this plan.

**Stache facts this plan relies on** (statamic/cms 6.7.1): `Statamic\Stache\Query\Builder::pluck($column, $key)` resolves the same key set as `get()` and reads `store->getItemValues(keys, column, key)` from the indexes, preserving key order, without hydrating items. The terms store keys items `{locale}::{slug}` with one item per taxonomy site; its `title` index value is `ResolveValue($item, 'title')`, which calls `LocalizedTerm::title()`, and its `id` index value is `taxonomy::default-slug`. `where('site', x)` on terms therefore selects the localization for site `x` and returns nothing for a site the taxonomy is not configured for. The entries store's `title` and `origin` indexes resolve through `Entry::value()`, so inherited titles and `title_format` titles come out as `$entry->title` would.

**Existing assertions that pin the old shape:** `tests/Feature/LfCheckboxFilterTest.php:655-658` (`it_uses_custom_options_when_provided`) and `tests/Feature/HandleEntriesCountTest.php:173-175` (`it_ignores_a_params_updated_dispatch_when_the_counts_feature_is_disabled`) assert `$statamic_field['counts'] === [... => null]`.

## The change

Land the three parts together: part 2 alone regresses `LfTags` (table above).

1. **`src/Http/Livewire/Traits/HandleFieldOptions.php` — materialise `counts` only when the feature is on.** `addCountsArrayToConfig()` returns the field untouched unless `config('statamic-livewire-filters.enable_filter_values_count')` is true. `addCustomOptionsToConfig()` and `setFieldOptionsAndCounts()` set only `'options'` and then delegate to `addCountsArrayToConfig()`, so there is one place that decides. With the flag on, the config is byte-identical to today.

2. **`src/Http/Livewire/Traits/HandleEntriesCount.php` — `counts()` keeps its shape.** Add a third branch after the `$this->options` one: when `statamic_field['options']` is an array, return `array_fill_keys(array_keys(...), null)`. Every view and Alpine seed keeps receiving the null-filled map exactly as before.

3. **Drop `persist: true`** from the `#[Computed]` attribute on `LfCheckboxFilter::filterOptions`, `LfRadioFilter::filterOptions` (covers `LfSelectFilter`), `LfDateFilter::filterOptions`, `LfSort::sortOptions` and `LfTags::statamicFields`. Plain `#[Computed]` memoizes within the request on Livewire 3 and 4. Rejected variant: keeping `persist` only on `LfTags` — it still writes a 134 KB file per LfTags component per page view and leaves the Laravel 13 `serializable_classes` problem (section "What verification refuted").

4. **`src/Http/Livewire/Traits/HandleStatamicQueries.php` — read titles from the indexes on Stache.**
   - `getTaxonomyTerms()`: when `$taxonomy->queryTerms()` is a `Statamic\Stache\Query\TermQueryBuilder`, pick `$indexSite = $taxonomy->sites()->contains($siteHandle) ? $siteHandle : $taxonomy->sites()->first()`, then `->where('site', $indexSite)->pluck('title', 'id')->mapWithKeys(fn ($title, $id) => [Str::after($id, '::') => $title])`. The site guard reproduces the legacy fallback for a site the taxonomy is not configured for (the verifier's draft lacked it and returned no options there). Otherwise fall through to the current `get()` mapping unchanged.
   - `getCollectionEntries()`: when `Entry::query()` is a `Statamic\Stache\Query\EntryQueryBuilder`, build the same `where('collection')->where('site')->whereStatus('published')` query and `pluck('title', 'id')`; with `use_origin_id_for_entries_field` on, also `pluck('origin', 'id')` and re-key each title by `$origins[$id] ?: $id`. Otherwise fall through to the current `get()` mapping unchanged.
   - Import `Illuminate\Support\Str` and the two builder classes; keep the Blink keys as they are.

5. **Update the two existing assertions** listed under "Current state" to assert the key is absent from the snapshot and that `counts()` still returns the null-filled map (see "Tests to add").

6. Add the two test files from `plans/perf-2026-10/reference/011/tests/Feature/`.

Reference implementation: `plans/perf-2026-10/reference/011/011-slim-snapshots-and-index-options.diff` (apply from the addon root with `git apply` or `patch -p1`; it was generated against the working tree at review time and contains steps 1–6).

## Tests to add

Both files use namespace `Reach\StatamicLivewireFilters\Tests\Feature`, class name == file name, and build their content in `setUp()`/helpers with the Stache facades, like the sibling tests. Four of them fail on the unpatched tree (marked ✗); the rest are regression guards that pass before and after and must keep passing.

`tests/Feature/FilterSnapshotAndOptionsTest.php` (12 tests; includes a small `EvaluationCountingCheckboxFilter` helper component, precedent: `FieldValuesQueryScopeAliasTest.php`):
- ✗ `counts_fall_back_to_the_null_filled_shape_for_every_option_source_when_counts_are_disabled` — for blueprint options, terms, entries and custom options: `counts()` and the magic `$this->counts` equal the null-filled option keys, the snapshot has no `counts` key, and the rendered HTML shows no count badges.
- `advanced_views_seed_alpine_with_null_counts_when_counts_are_disabled` — `lf-checkbox-advanced` and `lf-select-advanced` still emit `counts: JSON.parse('{"red":null,...}')`.
- `counts_are_still_materialized_and_dispatched_when_the_feature_is_enabled` — with the flag on the mount-time array is present and `params-updated` yields real counts for checkbox, select and radio-with-custom-options.
- ✗ `enabling_counts_after_the_page_was_rendered_without_them_still_computes_counts_on_update` — a component mounted with counts off still produces counts once the flag is on (`updateCountsWithBatchQuery` rebuilds the array).
- `lf_tags_resolves_option_labels_regardless_of_the_counts_flag` — term, entry and select labels on tags are correct with the flag off and on.
- `filter_options_is_evaluated_once_per_request_without_persist` — 1 evaluation at mount+render, memoized on repeated access, 3 after an update (`rules()` call + render).
- ✗ `no_lw_computed_cache_entries_are_written_on_mount_or_update` — a cache `KeyWritten` spy sees no `lw_computed.*` key across all five components.
- ✗ `lf_tags_produces_identical_tags_on_later_requests_without_a_persisted_cache` — two `tags-updated` dispatches with Blink flushed between them give identical tags with no `lw_computed` write or hit.
- `content_changes_are_visible_on_the_next_request_without_any_invalidation` — `saveQuietly()` term create/rename, term delete, quiet unpublish and entry delete are all visible on the next mount. This is the guard against re-introducing a persistent cache.
- `sorted_and_unsorted_term_options_agree_after_a_quiet_title_edit` — the `sort="order:asc"` path and the default path show the same renamed title.
- `localized_term_titles_do_not_leak_between_sites` — two sites, partial localization; each site sees its own titles, unlocalized terms fall back to the default locale.
- `origin_id_flag_selects_origin_or_localized_entry_ids_per_site` — `use_origin_id_for_entries_field` true/false on the localized site, and the default site unaffected.

`tests/Feature/StacheIndexOptionsTest.php` (5 tests) proves the pluck path returns **exactly** the legacy `get()` mapping, which each test recomputes inline from the old code (order-sensitive `assertSame`):
- `the_stache_query_builders_are_the_ones_exercised_by_this_suite` — the fast path, not the fallback, is what the suite covers.
- `single_site_term_and_entry_options_match_the_legacy_mapping_in_value_and_order` — includes a term without a title (slug fallback) and a draft entry (excluded), both id modes.
- `partially_localized_sites_match_the_legacy_mapping_per_site_for_terms_and_both_entry_id_modes` — 3 sites; es fully localized, de partially (fallback titles); localized entries with origins, an es-only entry without origin, a draft localization (excluded), a localization without its own title (inherits).
- `a_taxonomy_not_configured_for_the_current_site_falls_back_to_its_default_locale_like_before` — fails on the unguarded pluck (`[]` instead of `['red' => 'Red']`).
- `title_format_collections_match_the_legacy_mapping`.

**Existing tests that change (legitimately):** the two assertions at `LfCheckboxFilterTest.php:655-658` and `HandleEntriesCountTest.php:173-175` pinned an implementation detail (a null array in the snapshot). They now assert `! array_key_exists('counts', $statamic_field)` and, via `->tap(...)`, that `$component->instance()->counts()` still returns the same null-filled map, which is the behaviour the views depend on. No test is removed.

## Verification

Run from the addon root, in this order.

1. `vendor/bin/phpunit tests/Feature/FilterSnapshotAndOptionsTest.php tests/Feature/StacheIndexOptionsTest.php` → `OK (17 tests, 86 assertions)`. (Optional proof: on a copy of the unpatched tree with only these two files added, the run reports exactly 4 failures, the ✗ tests above.)
2. `vendor/bin/phpunit tests/Feature/LfCheckboxFilterTest.php tests/Feature/HandleEntriesCountTest.php tests/Feature/LfTagsTest.php tests/Feature/MultiSiteTest.php tests/Feature/LfSortTest.php tests/Feature/LfDateFilterTest.php tests/Feature/LfRadioFilterTest.php tests/Feature/LfSelectFilterTest.php` → `OK (109 tests, ...)`.
3. `vendor/bin/phpunit` → `OK (423 tests, 1356 assertions)` on the review-time tree (406 + 17). If other plans landed first, expect their count plus 17.
4. `vendor/bin/pint --dirty --format agent` → `{"result":"pass"}` (the diff is already Pint-formatted).
5. `grep -rn "persist: true" src/` → no matches. `grep -n "'counts' =>" src/Http/Livewire/Traits/HandleFieldOptions.php` → exactly one match, inside `addCountsArrayToConfig()`.
6. Bench (one command per run, see `plans/perf-2026-10/reference/011/bench/HOW-TO-RERUN.txt`): copy the two `*BenchTest.php` files into `tests/Bench/`, run them with `BENCH_TAG=AFTER BENCH_RESULTS=/tmp/plan011-bench.txt`, then **delete `tests/Bench/`**. Expect, within ±20 % on timings and exactly on bytes/counts: `flag=off checkbox entries(1000) snapshot 53594 B | ... counts key no`; `counts/bounds off: 7 filters | request 78466 B`; `page view #1 (8 filters): lw_computed writes=0`; `20 more page views: cache files +0`; `tags-updated #1..#4: ~54 / ~7 / ~7 / ~7 ms`; `entries(1000) mount #1..#4: ~13 ms` each; `8-filter page view: ~22 ms`; every `(b)`–`(f)` invalidation line equal to its "fresh would be" value. Compare against `bench-BASE-rerun.txt`.
7. Browser check (demo app, no tooling required): open `/basic` (it has `lf-tags`), pick a term and an entry filter and confirm the tag chips show titles. Open `/advanced`, reload three times, then list `storage/framework/cache/data` for files containing `lw_computed`: no new ones appear (before the change each reload added one per filter). In the network tab, the `wire:snapshot` of a checkbox filter on a site with counts off no longer contains `"counts"`; on the demo (counts on) it is unchanged.

## STOP conditions

- `vendor/bin/phpunit tests/Feature/StacheIndexOptionsTest.php` fails on any identity assertion: the index values differ from `get()` on the installed Statamic version. Do not patch the expectation; report the diff.
- `the_stache_query_builders_are_the_ones_exercised_by_this_suite` fails: the test suite no longer runs on the Stache driver and the fast path is untested.
- `grep -rn "statamic_field\['counts'\]\|statamic_field\.counts" resources/ src/` finds a reader outside `HandleEntriesCount.php` (a view or trait added since review relies on the array).
- The full suite count after the change is not the baseline + 17, or any test outside the two edited ones fails.
- `LfTags` `tags-updated #2..#4` in the bench is above 30 ms: the pluck path is not being hit (check the `instanceof` guards against the installed class names).
- A `(b)`–`(f)` invalidation line in the bench output shows a stale value.

## What verification refuted

1. **Reviewer B's `FieldOptionsCache` (F4)** — a persistent `Cache::remember` of the resolved options keyed by site + taxonomy/collection + origin flag, with a generation counter bumped on `TermSaved`/`TermDeleted`/`EntrySaved`/`EntryDeleted` and a 3600 s TTL, plus a `cache_field_options` config flag and ServiceProvider listeners. The gain reproduced (8-filter page view 220 → 16 ms, `bench-F2-F3-F4-rejected.txt`) but it is rejected as default-on: it misses `saveQuietly()` (used by `statamic:migrate-dates-to-utc`, importers and seeders), manual YAML edits picked up by the Stache watcher, `please stache:refresh|warm|clear`, `EntryScheduleReached`, and plain time passing for dated collections (`whereStatus('published')` compares against `now()`; in `bench-F2-F3-F4-rejected-rerun.txt` attacks `(b)` and `(f)` carry a `<- STALE` marker and `(c)`/`(e)` print the stale value next to the fresh one; `(f)` still shows 1 option 30 minutes after the entry's date passed). `IsSortable::getTaxonomyTermsSortedBy()` bypasses it, so two filters on one taxonomy disagree during the stale window (the verifier proved this with a regression test on the F4 copy; `sorted_and_unsorted_term_options_agree_after_a_quiet_title_edit` keeps that property true here). The pluck path gets 90–95 % of the gain with nothing to invalidate. Keep F4 at most as a future opt-in for Eloquent installs; do not add `src/Support/FieldOptionsCache.php`, the ServiceProvider listeners or the config keys.
2. **"Persist has zero cross-request benefit" (reviewer B)** — true for the four filters, wrong for `LfTags`: B's bench did not flush Blink between dispatches. Dropping `persist` alone makes every `tags-updated` request pay ~200 ms at 500 terms / 1,000 entries (table in "Why this matters"). Hence F3 ships only together with the pluck change.
3. **The verifier's unguarded pluck** (`where('site', Site::current())` with no taxonomy-site check) — returns an empty option list when the current site is not among the taxonomy's sites, where the old code returned default-locale titles. Caught by `a_taxonomy_not_configured_for_the_current_site_falls_back_to_its_default_locale_like_before`; fixed by the `$indexSite` guard.
4. **Reviewer B's F1** (skip the re-render for `-advanced` views by view name) belongs to Plan 010 and is not part of this plan.
5. **Keeping the null `counts` array but trimming it elsewhere** was not pursued: the array is the only variable-size part of the snapshot that carries no information, and `counts()` already had a fallback shape for custom options.

## Compatibility & docs

- **Published views.** The stock views read only `$this->counts` and are unchanged. A hand-written view that reads `$statamic_field['counts'][$value]` directly would hit an undefined key once counts are off; `$this->counts[$value]` keeps working. State this in the changelog.
- **Config flags.** `enable_filter_values_count` on → snapshot and `counts-updated` payloads byte-identical to today. `use_origin_id_for_entries_field` is honoured on the pluck path through the `origin` index and tested in both modes. No new config keys.
- **Livewire 3 vs 4.** `#[Computed]` without `persist` exists on both and memoizes per request on both. On Livewire 4.3.1 (demo app) the per-request memo already hides the reads; the writes and file growth stop on every version. Removing `persist` also sidesteps Laravel 13's `cache.serializable_classes`, which would turn the persisted `LfSort` `Collection` into `__PHP_Incomplete_Class` on 4.3.1.
- **Stache vs Eloquent.** The pluck path is guarded by `instanceof` on the Stache builders; the Eloquent driver (`statamic/eloquent-driver` builders) falls through to the current `get()` mapping, untested here but byte-for-byte the old code.
- **Multi-site.** Covered by both test files: localized titles stay per site, fallback to the default locale for unlocalized terms and for sites the taxonomy is not configured for, origin vs own ids per site.
- **Custom term sort.** `IsSortable::getTaxonomyTermsSortedBy()` still hydrates with `get()`; it is consistent with the pluck path because neither caches across requests. A follow-up could use `orderBy()->pluck()` there.
- **Changelog line (docs repo `changelog.md`, "Changed"):** "Filter snapshots no longer carry a placeholder `counts` array when `enable_filter_values_count` is off (up to 45 % smaller requests for large option sets); `$this->counts` in views is unchanged, but custom views reading `$statamic_field['counts']` directly must switch to `$this->counts`. Computed option properties are no longer persisted to the cache store, and term/entry option labels are read from the Stache indexes instead of hydrating every term/entry on each page view."
- **Docs pages to touch** (sibling repo `/Volumes/1TB/Sites/livewirefilters-docs`, read-only for this plan): `changelog.md`; `installation.md` config table rows for `enable_filter_values_count` (line 34) and `use_origin_id_for_entries_field` (line 33) need no wording change; `advanced/tips-performance.md` (line 57 documents the origin-id flag) may mention that option labels are now index reads. The custom-views guidance lives in `installation.md` "Publish the views" (line 50).
