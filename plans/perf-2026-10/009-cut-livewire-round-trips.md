# Plan 009: Cut Livewire round trips: client-side filter dispatch, zero-request page load, relay-free clear-all, separate bounds event

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving to the
> next step. If anything in the "STOP conditions" section occurs, stop and
> report — do not improvise. When done, update the status row for this plan
> in `plans/perf-2026-10/README.md`.
>
> **Drift check (run first)**: `git diff --stat 8096a0b..HEAD -- src/Http/Livewire/LfCheckboxFilter.php src/Http/Livewire/LfCount.php src/Http/Livewire/LfDateFilter.php src/Http/Livewire/LfDualRangeFilter.php src/Http/Livewire/LfRadioFilter.php src/Http/Livewire/LfRangeFilter.php src/Http/Livewire/LfTextFilter.php src/Http/Livewire/LfToggleFilter.php src/Http/Livewire/LivewireCollection.php src/Http/Livewire/Traits/HandleFieldBounds.php src/Http/Livewire/Traits/HandleParams.php src/Http/Livewire/Traits/IsLivewireFilter.php resources/views/livewire/filters/lf-checkbox.blade.php resources/views/livewire/ui/count.blade.php tests/Feature/FieldValuesQueryScopeAliasTest.php tests/Feature/HandleFieldBoundsTest.php tests/Feature/LfCountTest.php tests/Feature/LivewireCollectionComponentTest.php`
> This plan was written on the uncommitted `feature/dynamic-range-bounds` working tree
> (file list in `plans/perf-2026-10/reference/working-tree-status-at-review.txt`). If any
> of those files changed beyond that branch landing, compare the "Current state" excerpts
> against the live code before proceeding; on a mismatch, STOP.

## Status

- **Priority**: P1
- **Effort**: L
- **Risk**: MED
- **Depends on**: none. Apply after Plans 010 and 008 to minimise conflicts in the `Lf*` components (see the folder README).
- **Category**: perf
- **Planned at**: commit `8096a0b` + uncommitted dynamic-range-bounds work, 2026-10-21
- **Implements / refines**: PERF-04 (browser-side `filter-updated` dispatch) and PERF-05 (LfCount without a server listener) from `plans/README.md`; partially addresses PERF-08 (the post-load request disappears only on the default config; with counts/bounds on it stays and is left to a later plan). This review's finding IDs: V4-T1 (clear-all relay), V4-T2 (client dispatch), V4-T3 (`bounds-updated`), V4-T4 (page load).

## Why this matters

Every filter interaction is three **serial** POSTs: (1) the filter's `wire:model.live` commit runs `updatedSelected()`, which dispatches `filter-updated` to the collection; (2) the collection applies it, re-queries, renders, and dispatches `params-updated` (counts + an encrypted bounds token), `tags-updated` and `entries-updated`; (3) every listener (each filter, LfTags, LfCount) hydrates and re-renders in one fan-out POST. Each POST pays 110–120 ms of fixed framework boot on Herd (measured by the islands evaluation in `plans/README.md`), so removing a hop beats shrinking bytes. Three more waste points: `clearAll()` sweeps every runtime param itself, yet all eight filters still relay `clear-filter`, costing a third POST that renders nothing (`rendered=false`); the bounds token rides on `params-updated` to every counts listener although only range filters can decode it; and on the default config the only reason a page load makes a POST at all is LfCount (`entries-updated` listener) or LfTags (mount-time `tags-updated` with nothing to show).

All numbers: headless browser on the demo app (40 cars, Stache, Livewire 4.3.1 / Statamic 6.21, `php artisan serve`, raw bytes, no gzip), single runs; request counts and bytes are deterministic, server time is the sum of TTFBs (±15 ms noise). "counts on" = `enable_filter_values_count` + `enable_dynamic_range_bounds` + `custom_query_string`; "default" = both off. Captures in `reference/009/bench/captures/`.

| /advanced, counts on | Before (POSTs · up · server) | After all four steps |
|---|---|---|
| Page load (8 components) | 1 · 10,843 B · 72 ms | 1 · 8,396 B · 32 ms (7 components, no LfCount) |
| Tick checkbox | 3 (1→1→8) · 14,511 B · 125 ms | 2 · 11,482 B · 79 ms |
| Range slider | 3 · 15,150 B · 112 ms | 3 · 11,727 B · 97 ms (view not converted, see Step 1 follow-up) |
| Clear all (collection button) | 3 (1→10→1; hop 3 = 8 no-op relays) · 17,098 B · 110 ms | 2 · 12,063 B · 86 ms |
| /preset LfTags "Clear all" (tag-authored brand) | 4 · 16,990 B · 122 ms, state correct | 4 · 15,374 B · 117 ms, state correct |

| Default config | Before | After |
|---|---|---|
| /advanced load | 1 POST (LfCount only, 546 B) | **0 POSTs** |
| /basic load | 1 POST (LfTags only, 734 B) | **0 POSTs** |
| /preset load | 1 POST (preset-params + tags) | 1 POST, 8,186 B (still needed) |
| Tick checkbox | 3 · 2,979 B · 84 ms | **1 · 2,411 B · 60 ms** |
| Clear all | 3 | 2 |

Per-step: Step 1 alone, tick 3→2 POSTs (125→82 ms counts on; 84→57 ms default). Step 3 alone, clear-all 3→2 (14,319 B, 91 ms). Step 4 alone, load 10,843→8,828 B (−19 %), fan-out POST 12,092→9,517 B (−21 %).

## Current state

**Filter → collection relay.** `src/Http/Livewire/LfCheckboxFilter.php:38-51` `updatedSelected()` validates when `validate_filter_values` is on, then `$this->dispatch('filter-updated', field:, condition:, payload: $this->selected, modifier:)->to(LivewireCollection::class)`; `clearOption()` (`:67-76`) reuses it. `resources/views/livewire/filters/lf-checkbox.blade.php:5-11` binds each `<input type="checkbox">` with `wire:model.live="selected"` and nothing else. The collection accepts the event at `src/Http/Livewire/LivewireCollection.php:149-181` (`#[On('filter-updated')] filterUpdated(...)`) and hardens params in `src/Http/Livewire/Traits/GenerateParams.php:29-36` (`effectiveQueryParams()`: `allowed_filters` + `BlockedQueryParams`).

**Clear-all.** `LivewireCollection.php:223-227`:
```php
    public function clearAll()
    {
        $this->clearAllFilterParams();
        $this->dispatch('clear-all-filters');
    }
```
`src/Http/Livewire/Traits/HandleParams.php:596-624` `clearAllFilterParams()` unsets every removable key and calls `dispatchParamsUpdated()`; `isClearAllRemovableParamKey()` (`:626-637`) keeps `sort`/`query_scope`/`page_name` and every key in `$clearAllExemptParamKeys`, which `captureClearAllExemptParams()` (`:666-684`) fills with tag-authored and init-hook-authored condition keys — exempt *because* the managing filter's relay clears them. Every filter has `#[On('clear-all-filters')] clear()` that resets its value and calls `clearFilters()` (`src/Http/Livewire/Traits/IsLivewireFilter.php:90-99`, dispatches `clear-filter` to the collection). `clearFilter()` (`HandleParams.php:190-227`) short-circuits with `skipRenderIfCollectionStateUnchanged()` when the key is already gone, so hop 3 is pure boot cost. Range filters additionally call `rememberBoundsSelection([])` (`src/Http/Livewire/Traits/HandleFieldBounds.php:147-156`, bumps `boundsSelectionRevision`); `LfRangeFilter.php:67-73`, `LfDualRangeFilter.php:100-114`.

**Fan-out.** `HandleParams.php:778-802` `dispatchParamsUpdated()` dispatches `params-updated` with `[$params]` plus `RangeBoundsQuery::encode(...)` when bounds are on, then `tags-updated` to LfTags unconditionally. Range filters listen with `#[On('params-updated')] updateBounds($params, $boundsQuery = null)` (`HandleFieldBounds.php:49-50`); `boundsSelectionParams()` (`:158-176`) returns `$params` when `pendingBoundsSelection` is null, else the pending selection until the collection acknowledges the revision. `LivewireCollection::mount()` (`:72-103`) ends with `$this->dispatchParamsUpdated()` (`:102`). `LfTags::updateTags()` (`src/Http/Livewire/LfTags.php:64-77`) keeps only `:` keys and `query_scope*`, never `sort`; it mounts with `tags = collect()`.

**Count.** `src/Http/Livewire/LfCount.php:14-18` `#[On('entries-updated')] updatedCount($count)`; `resources/views/livewire/ui/count.blade.php:1-7` renders `@if ($count > 0) {{ $count }} {{ trans_choice(...) }}`. `LivewireCollection::rendered()` (`:270-273`) dispatches `entries-updated` with `count`/`active`, which Livewire also fires as a window DOM event.

**Livewire facts relied on.** (1) Pooling: in the demo's `vendor/livewire/livewire/dist/livewire.js` (4.3.1) `fireActionInstance` → `messageBus.messageBuffer(message, () => sendMessages())`; messages of different components raised in the same 5 ms window travel in one POST. Verified: the view's `$wire.$dispatchTo(...)` landed in the same POST as the `$commit` (checkbox message first, collection second) and the collection applied the new value. (2) Listeners: `SupportEvents::call()` invokes `$method(...$params)` positionally and throws `EventHandlerDoesNotExist` when a `__dispatch` names an event missing from `getListeners()`.

## The change

Four independent steps. Each has its own diff generated against the pristine working tree; `combined.diff` is all four. Land in the order below with `patch -p1` (later steps hit earlier ones' offsets in `LfCheckboxFilter.php` and `HandleParams.php`; delete the `.orig` backups `patch` leaves: `find src -name '*.orig' -delete`) or `git apply -C1`. Plain `git apply` works for `combined.diff` or for any single step on the pristine tree, but refuses Step 4 after Step 2.

### Step 1 — client-side `filter-updated` dispatch (checkbox) · V4-T2

`resources/views/livewire/filters/lf-checkbox.blade.php`: add to the checkbox input an `x-on:change` that sets `$wire.syncedByView = true` and calls `$wire.$dispatchTo('livewire-collection', 'filter-updated', {field, condition, payload, modifier})`, with `payload` read from the checked inputs inside the component root (`$el.closest('[wire\\:id]')`). `src/Http/Livewire/LfCheckboxFilter.php`: add `public bool $syncedByView = false;`; in `updatedSelected()` keep the validation, then if `$syncedByView` is set reset it and return, else call a new `protected dispatchFilterUpdated()` holding the old dispatch; `clearOption()` calls `dispatchFilterUpdated()` directly (tags must still relay). The flag travels in the same commit as `selected`, so a published view without it keeps the server relay unchanged.

Rejected: dropping the server dispatch outright (breaks every published older view, see refutation c).

**Follow-up (not in this plan's diff):** apply the identical pattern to `lf-checkbox-advanced`, `lf-radio`, `lf-select`, `lf-select-advanced`, `lf-toggle`, `lf-text`, `lf-date`, `lf-range` and `lf-dual-range` views and components, each with its own `syncedByView` flag and an `an_unflagged_commit_still_relays` test. Range/dual-range must also carry `boundsSelection` from `rememberBoundsSelection()` — that needs a server round trip for the revision, so design it before converting.

Reference implementation: `plans/perf-2026-10/reference/009/step1-client-dispatch-checkbox.diff` (apply from the addon root with `git apply` or `patch -p1`; it was generated against the working tree at review time).

### Step 2 — zero-request page load on the default config · V4-T4

`src/Http/Livewire/LivewireCollection.php:102`: call `dispatchParamsUpdated(initial: true)`. `src/Http/Livewire/Traits/HandleParams.php`: give `dispatchParamsUpdated()` a `bool $initial = false` parameter and return before the `tags-updated` dispatch when `$initial && ! $this->hasTaggableParams()`; add `hasTaggableParams()` mirroring the `LfTags::updateTags()` filter (a `:` key or a `query_scope*` key, never `sort`). `src/Http/Livewire/LfCount.php`: replace the `#[On]` attribute with `getListeners()` returning `['entries-updated' => 'updatedCount']` only when `countViewIsCustomized()` (custom `view=`, or the resolved `statamic-livewire-filters::livewire.ui.count` path is outside the addon's `resources/views`); keep `updatedCount($count)`. `resources/views/livewire/ui/count.blade.php`: Alpine `x-data` seeded with the server count and both plural forms, `x-on:entries-updated.window` updating it, `x-text` output, with the server-rendered text kept inside the span as the no-JS first paint. `tests/Feature/LfCountTest.php` adapts (see Tests).

Reference implementation: `plans/perf-2026-10/reference/009/step2-zero-request-page-load.diff` (same application notes).

### Step 3 — relay-free clear-all · V4-T1

`src/Http/Livewire/LivewireCollection.php`: `clearAll()` dispatches `clear-all-filters` with one **positional** argument, `keptConditionParamKeys()` = the `:` keys still in `$params` after the sweep (tag/init-hook-authored). `src/Http/Livewire/Traits/IsLivewireFilter.php`: add `protected clearAllNeedsRelay(?array $keptParamKeys): bool` — true for a bare event (`null`: LfTags `clearAll()`, user code) or when one of the filter's own `getParamKey()` keys is listed. Checkbox/radio/text/date/toggle `clear(?array $keptParamKeys = null)`: reset the value, relay only if needed. `LfRangeFilter::clear()` / `LfDualRangeFilter::clear()`: when no relay is needed set `pendingBoundsSelection = []` when bounds are enabled (else `null`) **without** calling `rememberBoundsSelection()` (no revision bump, since no acknowledgement will come); dual-range still dispatches `dual-range-preset-values`.

Rejected: reviewer D's no-relay `clearOnClearAll()` (refutation a), "relay only when the filter held a value" (b), `pendingBoundsSelection = null` (d). A named argument instead of positional would throw in third-party listeners (Livewire spreads `...$params`).

Reference implementation: `plans/perf-2026-10/reference/009/step3-clear-all-kept-keys.diff`.

### Step 4 — separate `bounds-updated` event · V4-T3

`src/Http/Livewire/Traits/HandleParams.php` `dispatchParamsUpdated()`: dispatch `params-updated` with `[$params]` only when counts are enabled, and `bounds-updated` with `[$params, RangeBoundsQuery::encode(...)]` only when bounds are enabled. `src/Http/Livewire/Traits/HandleFieldBounds.php:49`: `#[On('bounds-updated')]`. Tests: 62 mechanical renames in `HandleFieldBoundsTest.php`, 2 in `FieldValuesQueryScopeAliasTest.php`, and the two `LivewireCollectionComponentTest` cases that enable only bounds (`it_dispatches_the_params_updated_event_when_only_dynamic_range_bounds_is_enabled`, `it_dispatches_params_updated_on_mount_when_dynamic_range_bounds_is_enabled`) now assert `bounds-updated`.

Reference implementation: `plans/perf-2026-10/reference/009/step4-bounds-updated-event.diff`. Combined: `plans/perf-2026-10/reference/009/combined.diff`.

## Tests to add

Copy from `plans/perf-2026-10/reference/009/tests/Feature/` (namespace `Reach\StatamicLivewireFilters\Tests\Feature`, class == file).

`ClientDispatchedCheckboxTest.php` (Step 1, 6 tests):
- `a_commit_flagged_by_the_view_skips_the_server_relay_and_resets_the_flag` — flagged `set('selected')` dispatches nothing and clears the flag.
- `an_unflagged_commit_still_relays_for_views_without_the_client_dispatch` — the compat path.
- `the_flag_only_covers_one_commit` — a later unflagged commit relays again.
- `validation_still_runs_on_a_flagged_commit` — invalid option → `assertHasErrors('selected')`, nothing dispatched.
- `the_bundled_view_dispatches_to_the_collection_from_the_browser` — the view contains the flag and the `$dispatchTo`.
- `clearing_an_option_from_tags_still_relays` — `clear-option` still dispatches `filter-updated`.

`ClearAllRelayTest.php` (Step 3, 9 tests; forwards whatever the filter dispatches to a real collection):
- `clear_all_clears_a_tag_authored_param_that_a_filter_manages` (2 paths: collection button, LfTags bare event) — the /preset flow; params end `[]`, `entriesCount` 2.
- `clear_all_clears_an_init_hook_authored_param_that_a_filter_manages` — hook-authored exemption, exactly one relay.
- `clear_all_from_the_collection_needs_no_relay_for_runtime_params` — the saved hop: zero `clear-filter` relays.
- `the_per_filter_clear_button_still_relays`.
- `a_stale_active_bounds_event_after_clear_all_does_not_snap_back` (4 cases: single/dual × older/newer event first) — then a fresh selection still acknowledges (`pendingBoundsSelection` null). Its `boundsEventName()` helper reads `bounds-updated` when present, else `params-updated`, so the file is green with or without Step 4.

Existing tests that change, and why that is legitimate: `LfCountTest` — the bundled view no longer has a server listener, so `dispatch('entries-updated', n)` is replaced by `set('count', n)` (Livewire calls `updatedCount()` as the `$count` lifecycle hook, same code path) plus an assertion that the view carries the Alpine hook and that a custom `view` restores the listener; Step 4's renames assert the new event name for the same behaviour. No test is removed.

## Verification

| Step | Command | Expected |
|---|---|---|
| 0 | `vendor/bin/phpunit` before any change | `OK (406 tests, 1268 assertions)` (if other plans landed first, record your baseline B) |
| 1 | `vendor/bin/phpunit --filter "ClientDispatchedCheckboxTest|LfCheckboxFilterTest"` | all pass; full suite `OK (B+6)` |
| 2 | `vendor/bin/phpunit --filter "LfCountTest|LivewireCollectionComponentTest|LfTagsTest"` | all pass; full suite `OK (B)` |
| 3 | `vendor/bin/phpunit --filter "ClearAllRelayTest|LfRangeFilterTest|LfDualRangeFilterTest|HandleFieldBoundsTest"` | all pass; full suite `OK (B+15)` with Steps 1–3 |
| 4 | `vendor/bin/phpunit --filter "HandleFieldBoundsTest|FieldValuesQueryScopeAliasTest|LivewireCollectionComponentTest"` | all pass; `grep -rn "'params-updated'" src/Http/Livewire/Traits/HandleFieldBounds.php` → no match |
| all | `vendor/bin/phpunit` | `OK (421 tests, 1373 assertions)` on this baseline (B+15), ~60 s |
| all | `vendor/bin/pint --dirty --format agent` | `{"result":"pass"}` |

Per-step suite counts measured on clean copies: Step 1 alone 412, Step 2 alone 406, Step 3 alone 415, Step 4 alone 406, all four 421.

**Browser check (demo app at https://livewirefilters-demos.test, which symlinks this addon).** Open the network tab filtered to `livewire/update`. With the demo's counts-on config: load `/advanced` → 1 POST without an `lf-count` component; tick "Cabrio" → **2** POSTs, the first carrying two components (`lf-checkbox-filter` with `syncedByView: true`, then `livewire-collection`), URL and count text updated; "Clear all" → **2** POSTs, the second with no `clear-filter` dispatches; `/preset` → LfTags "Clear all" → 4 POSTs and the brand chips, the checkbox and the entries all reset. Then set both count flags to `false` in the demo's `config/statamic-livewire-filters.php`: load `/advanced` and `/basic` → **0** POSTs, count text still "40 cars found."; tick → **1** POST; count text becomes "6 cars found.". Tolerance: request counts and component lists are exact; bytes ±5 %, times ±15 ms.

**Harness re-run (optional, needs the `playwriter` CLI).** `reference/009/bench/harness/` holds the capture harness (`lwcap.mjs`, `s*.js`, `b*.js`, `p1_preset_clearall.js`, `t2_tick.js`) and `serve-demo.sh <demo-app-copy> <addon-dir> 8102`, which re-points a demo copy's vendor symlink at an addon checkout and serves it; `app-config-counts-on.php` is the counts-on config. Expected outputs are `bench/captures/baseline-*.txt` (pristine) and `after-combined.txt`.

## STOP conditions

- The drift check shows changes, or `vendor/bin/phpunit` fails before you start.
- After Step 1, ticking a checkbox on `/advanced` still makes 3 POSTs, or the first POST carries only one component: message pooling did not happen — report the installed `livewire/livewire` version.
- After Step 1, a copy of the **pre-change** `lf-checkbox.blade.php` published to `resources/views/vendor/statamic-livewire-filters/livewire/filters/` stops filtering (the naive variant's symptom).
- After Step 2, the bundled count view does not update on filter change, or a published pre-change `count.blade.php` loses its POST / stops updating.
- After Step 3, `/preset` LfTags "Clear all" leaves the brand chips or `taxonomy:car_brand:any` in `$wire.params` while the checkbox shows nothing selected (reviewer D's symptom), or `/advanced` clear-all still makes 3 POSTs.
- `EventHandlerDoesNotExist` in `storage/logs` during the browser check on a freshly loaded page.
- Any existing test other than the four files named in "Tests to add" fails.
- `countViewIsCustomized()` returns `true` for the bundled view on this machine (the demo publishes only `livewire-collection.antlers.html`; if `view()->getFinder()` resolves the count view outside `resources/views`, the install differs from the review's).

## What verification refuted

- **(a) Reviewer D's `clearOnClearAll()` (no relay at all).** `/advanced` clear-all did drop to 2 POSTs, but on `/preset` (tag-authored `taxonomy:car_brand:any="toyota|hyundai|vw"` plus a checkbox on `car_brand`) LfTags "Clear all" left the param in the collection, the 3 brand chips and 6 entries unchanged while the checkbox showed nothing selected (`bench/captures/after-t1-D.txt`). Mechanism: `clearAllFilterParams()` exempts tag/hook-authored keys (`HandleParams.php:626-637`, `:666-684`) precisely because the managing filter's relay removes them; D's variant failed 8 existing tests that assert the relay, and adapting them would have hidden this. Do not reintroduce a relay-free clear.
- **(b) "Relay only if the filter had a value."** Correct, but clear-all with at least one active filter still produces the third POST (1 relay, 1,172 B, 15 ms; `after-t1-active.txt`), i.e. no hop saved in the normal case.
- **(c) Naive Step 1 (server dispatch removed, no flag).** A published pre-change `lf-checkbox.blade.php` stops filtering entirely, and four existing checkbox tests fail for the same reason — hence `syncedByView`. The safe variant was re-checked with the old view published: still filters, 2 POSTs (`breakit-combined.txt` (a)).
- **(d) `pendingBoundsSelection = null` on a no-relay clear.** `boundsSelectionParams()` then trusts the event's params, so an older `bounds-updated` still carrying the cleared selection re-activates the range and snaps the slider back. With `[]` and no revision bump, stale events stay inactive until the next real selection acknowledges; `a_stale_active_bounds_event_after_clear_all_does_not_snap_back` covers both orders.
- Not refuted but deliberately not done: keeping a `params-updated` listener on range filters for backward compatibility would put them back into every counts fan-out.

## Compatibility & docs

- **`validate_filter_values`** becomes a UI-only check for the client-dispatched views: the collection already accepted a browser-originated `__dispatch('filter-updated')` (any page script can call `Livewire.dispatchTo`), so this adds no surface; `allowed_filters`/`blocked_query_params` gating in `effectiveQueryParams()` is untouched. Document it on the config table (`installation.md`, the `validate_filter_values` row).
- **Published views.** Old `lf-checkbox.blade.php`: still filters via the server relay (3 POSTs). Old `count.blade.php`: `countViewIsCustomized()` keeps the server listener, so the load POST and updates remain. The new checkbox view reads *all* checked `input[type=checkbox]` inside the component root — a published view that nests unrelated checkboxes there must scope the selector.
- **Upgrade / static cache.** Step 2 removes a listener from LfCount and Step 4 renames the range filters' listener. A page rendered by the previous version (static cache, open tab) still routes `entries-updated` to `lf-count` and `params-updated` to range filters; the new server answers `EventHandlerDoesNotExist` (HTTP 500) on that page's first interaction. Clear the static cache on deploy; open tabs recover on reload. Publishing or deleting `count.blade.php` has the same effect on pages rendered before the change. Changelog line (docs repo `changelog.md`): "Filters: the checkbox view dispatches to the collection from the browser (one request fewer); page load makes no Livewire request on the default config; clear-all no longer relays per filter; range filters now listen to `bounds-updated` instead of `params-updated`. Clear static caches after upgrading. `validate_filter_values` no longer gates the bundled checkbox view's dispatch."
- **Third-party listeners.** `clear-all-filters` now carries one positional array; PHP ignores the extra argument for listeners declared without it. `params-updated` no longer carries the bounds token as a second argument; custom listeners must use `bounds-updated` for it.
- **Livewire versions.** Proven on 4.3.1 (demo, pooling) and 4.2.1 (suite). `marcorieser/statamic-livewire ^5` also allows Livewire ^3.6.4, where `$wire.$dispatchTo` exists and commit pooling exists but was not measured; without pooling the two messages become two parallel POSTs, which is still correct (the collection payload is built from the DOM, the flag rides in the checkbox commit).
- **Eloquent / multi-site.** No query path changes; untested, falls back to current behaviour by construction.
- **Docs pages to edit** (sibling repo `livewirefilters-docs`): `installation.md` (config row), `advanced/tips-performance.md` ("Displaying Total Entries / Active Filters": the bundled `lf-count` now updates from the browser event; published older views keep the listener), `advanced/transitions-animations.md` (events list: add `bounds-updated`, note `params-updated` carries params only).
