# Plan 016: Facet engine — register facets with the collection and batch their values per filter state

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving to the
> next step. If anything in the "STOP conditions" section occurs, stop and
> report — do not improvise. When done, update the status row for this plan
> in `plans/perf-2026-10/README.md`.
>
> **Drift check (run first)**: `git diff --stat 75fac26..HEAD -- src/Support/CountEntries.php src/Support/CountQueryPool.php src/Support/RangeBoundsQuery.php src/Http/Livewire/Traits/HandleFieldValuesQuery.php src/Http/Livewire/Traits/HandleEntriesCount.php src/Http/Livewire/Traits/HandleFieldBounds.php src/Http/Livewire/Traits/HandleParams.php src/Http/Livewire/Traits/IsLivewireFilter.php src/Http/Livewire/LivewireCollection.php config/config.php`
> Written against `impr/performance` at `75fac26` (plans 008, 010 and 011 landed).

## Status

- **Priority**: P2
- **Effort**: M
- **Risk**: MED
- **Depends on**: 008 (shared key sets), 010 (render guards)
- **Category**: perf
- **Planned at**: `impr/performance` @ `75fac26`, 2026-11-03
- **Implements / refines**: the "collection-side facet engine" proposal (below). The proposal is reviewed first; this plan keeps its registration and batching idea and rejects moving the computation into `LivewireCollection`.

## The proposal under review

> Today each count-enabled filter runs its own pluck in a pooled Livewire request after a params-updated event (HandleEntriesCount, HandleFieldBounds, CountQueryPool), so N filters mean N component round-trips, N snapshots and N key-resolution passes, plus the encrypted token handshake. Move the computation into LivewireCollection: after entries() resolves, compute the base key set once (Stache: ->pluck('id') is index-only; Eloquent: select id plus the facet columns), compute per-active-filter variants (base minus that filter), count option values and min/max for every registered facet field in PHP, and dispatch one facets-updated event with {field => {counts, min, max}}. Filters register their field, options and type with the collection at mount via a register-facet event and become stateless renderers of the payload; keep dispatching counts-updated and range-bounds-updated for custom views for one major version.

## Review findings

All Stache numbers: demo app (Statamic 6.21, Livewire 4.3.1, Laravel 13), the `/advanced` filter set (8 filters: 6 counts + 2 bounds, both features on), replayed in-process by `php artisan lf:benchmark-facets` (demo repo), which delivers dispatched events the way the browser does, reloads every Stache index from the cache store between requests, and counts Stache queries through `statamic.system.fake_sql_queries`.

1. **"N component round-trips" does not hold.** Livewire 4 bundles every message raised in the same tick into one POST (`sendMessages()` in `livewire.esm.js`; non-isolated components share a `MessageRequest`). The fan-out is one HTTP request carrying N messages, as the 2026-10 README already recorded.
2. **"N key-resolution passes" no longer holds on the Stache.** Plan 008 shares the resolved key set per (collections, base params) inside the request-scoped `CountQueryPool`, and drops the sort. One interaction now costs 1 + (number of active filters) filter passes, which is exactly what the proposed engine would compute (base set plus one variant per active filter). Measured on `impr/performance` @ `75fac26`, N=5,000: the fan-out runs 2–4 Stache queries for 7 listeners; the rest are index reads (1.5–2 ms each).
3. **Moving the computation into the collection request saves no server time on the Stache.** Keeping the collection request's warm Stache indexes into the fan-out request (`--simulate-engine`, the upper bound of what computing facets next to `entries()` could save) changed the per-interaction total by −8…+9 ms on 233–301 ms medians, i.e. noise. The facet passes cost the same wherever they run; running them in the collection request only delays the entries render by their cost (~60 ms at N=5,000).
4. **"Stateless renderers" do not remove the fan-out request.** Every bundled view and every published view prints counts and bounds from server state (`$this->counts[$value]`, `$dynamicMin`), and the range filters' selection state machine (`pendingBoundsSelection`, revisions, `isAtClearValue()`) needs the bounds server-side. A `facets-updated` payload would still need one Livewire message per filter to re-render, so the N snapshots stay. Removing them means rewriting every view to Alpine — a breaking change of its own (plan 009 territory).
5. **A client-delivered facet payload would reopen the 4.6.1 hole unless it is signed.** Facet specs that reach the collection through a `register-facet` event are client input. If the collection computes and returns counts for whatever spec it is sent, a forged spec reveals value counts of fields no filter exposes. Values must only ever reach a filter whose own `#[Locked]` field asked for them.
6. **`register-facet` at mount would add a request to every page load** (the event is delivered after load, to the collection) unless it rides in the same bundled POST as the initial `params-updated` fan-out, or is registered server-side while the page renders.
7. **Where the proposal does hold: the Eloquent driver.** Plan 008 falls back to one SQL query per field on Eloquent (`resolvedKeys()` returns `null`), and to one full hydration per field on multi-site Eloquent collections (`EloquentQueryBuilder::pluck()` is `get()` + `ResolveValue`). A query that knows every facet field up front can select them all at once. SQLite, 7 facet fields, `seats >= 5`: 7 per-field queries 65.9 ms vs one 7-column query 16.9 ms at 5,000 rows; 301.7 ms vs 72.1 ms at 20,000 rows. That needs the field list before the first listener runs — which is what registration provides.

Conclusion: keep **registration** and **batching**, drop **compute in the collection** and **stateless renderers**. The collection learns the facets, and sends their specs inside the encrypted query it already dispatches. The first listener in the fan-out batches every registered facet that shares its filter state. Filters, views, events and the bounds state machine don't change.

## The change

Behind `enable_facet_engine` (default `false`). With it off, nothing new runs. The listeners call `CountQueryPool::getFieldValues()` exactly as today, the token carries no facets and no filter registers.

1. **Facet spec.** `['field' => <resolved field handle>, 'scope' => <modifier when condition is query_scope, else null>]`. That pair is everything `removeCurrentFieldFromParams()` reads, so it determines the base params. Add a support class `FacetSpec` that holds the param-stripping logic now in `HandleFieldValuesQuery::removeCurrentFieldFromParams()` and the collection lookup in `collectionsWithField()` as static methods, with the trait delegating (no behaviour change).
2. **Registration** (`HandleFieldValuesQuery`, called at the end of `IsLivewireFilter::mountIsLivewireFilter()` when the component composes the trait, the engine is on, and the trait's own feature — counts or bounds — is on):
   - server-side: `CountQueryPool::registerFacet($collection, $spec)` into the request-scoped pool, so a collection mounted later in the same request (sidebar before results, one `{{ nocache }}` render) starts with the spec in its first token;
   - client-side: `register-facet` to `LivewireCollection`, only when a collection has already mounted in this request (it missed the server-side registration). The event is raised during mount, so it travels in the page-load POST that already carries the initial fan-out — no extra request.
3. **Collection** (`LivewireCollection`, `HandleParams`): `#[Locked] public array $facets`; at mount it takes the pool's registered specs whose collection it queries; `#[On('register-facet')] registerFacet($field, $scope, $collection)` validates (handle regex `^[A-Za-z0-9_-]+$`, string-or-null scope, collection queried by this tag, at most 64 specs), deduplicates and `skipRenderIfCollectionStateUnchanged()`. `dispatchParamsUpdated()` passes `$facets` to `RangeBoundsQuery::encode()` when the engine is on.
4. **Token** (`RangeBoundsQuery`): optional `facets` list of `[field, scope]` pairs, validated on decode like `selections`; `decodeForCollection()` returns it.
5. **Batching** (`CountQueryPool::prefetch($params, $specs)`, `CountEntries::pluckMany($columns)`): each listener calls `prefetch()` with the token's specs, plus its own when the token lacks it, before `getFieldValues()`. The pool memoizes per (params, specs), so listeners sharing a token prefetch once. It maps every spec to the same (collections, base params, field) cache key `getFieldValues()` would use. Specs whose field no queried collection has are skipped. It groups by (collections, base params) and fills the field cache for groups of two or more uncached fields:
   - Stache: return before any work (008's shared key set already reads each field from its index; walking the specs per listener cost 10–30 ms for nothing in the first measurement);
   - Eloquent, single-site: one `select data->f1 as facet_0, …` on the unordered base query, values decoded exactly like `eloquentPluck()`; on `QueryException`, the per-field fallback;
   - Eloquent, multi-site: one `get()` and `ResolveValue` per field — what `EloquentQueryBuilder::pluck()` does per call;
   - any other builder: no-op.
   Values only reach filters through `getFieldValues()` with their own locked field, so a forged spec can only cost work, bounded by the cap.
6. **Not changed**: views, `counts-updated`, `range-bounds-updated`, `params-updated` payload shape (the token is opaque), the bounds revision logic, 008's Stache path.

Rejected variants: computing facets in `LivewireCollection::render()` (finding 3 — same cost, delays entries); a `facets-updated` payload (findings 4, 5); `register-facet` from every filter on every page load (an extra collection message per page load in layouts where server-side registration already covers it); selecting every blueprint field on Eloquent without registration (cost grows with blueprint size); fetching the whole `data` column once per group (unbounded with large Bard fields).

## Tests to add

- `CountQueryPoolTest`: prefetched values equal `getFieldValues()` for the same keys on the Stache (no-op path) and leave the query count unchanged; prefetch is memoized per token; specs for fields outside the queried collections are skipped; an Eloquent builder receives one multi-column select for a group (mocked `toBase()`), with JSON arrays decoded and the per-field fallback on `QueryException`.
- `FacetEngineTest`: with the flag on, a filter mounted before the collection is in the collection's first token; one mounted after it dispatches `register-facet`; `registerFacet()` rejects bad handles, foreign collections, the 65th spec, and does not render; with the flag off no filter registers and the token carries no `facets`; counts and bounds are identical with the flag on and off across interactions.
- `HandleEntriesCountTest` / `HandleFieldBoundsTest`: unchanged and green with the flag off; both also run with the flag on through a subclass that enables it, so every scenario is routed through `prefetch()`.

## Verification

| Purpose | Command | Expected |
|---|---|---|
| Suite | `vendor/bin/phpunit` | all green |
| Style | `vendor/bin/pint --dirty` | no changes after the run |
| Stache bench (demo) | `php artisan lf:benchmark-facets --entries=5000 --mode=both` | identical queries per step; medians within noise |
| Eloquent bench (demo copy with `statamic/eloquent-driver`) | same command | fan-out SQL queries per step drop from 7 to the number of groups; medians drop |
| Browser | `/advanced` with no static cache, `half` and `full` (+ `{{ nocache }}`) | page load makes the same number of POSTs as with the flag off; counts and slider bounds identical flag on/off |

## Results

Implemented on `perf/016-facet-engine` (from `impr/performance` @ `75fac26`). Found on the way and fixed in its own commit: `CountEntries::eloquentPluck()` plucked `data->field` without an alias, so on the Eloquent driver every faceted count and bounds update threw `Undefined property: stdClass::$data->field` (on `master` too).

Suite: `OK (670 tests, 2333 assertions)`; `HandleEntriesCountTest` and `HandleFieldBoundsTest` also run with the engine on (`*WithFacetEngineTest`).

`php artisan lf:benchmark-facets` (demo; requests / Livewire messages / queries / median of 5 runs):

| Step | Eloquent 5,000, off | Eloquent 5,000, on | Stache 5,000, off | Stache 5,000, on |
|---|---|---|---|---|
| page load | 2 / 7 / 11 q / 264 ms | 2 / 7 / 5 q / 199 ms | 4 q / 310–347 ms | 4 q / 315–342 ms |
| check a brand | 3 / 9 / 10 q / 235 ms | 3 / 9 / 5 q / 192 ms | 4 q / 247–291 ms | 4 q / 252–263 ms |
| pick a transmission | 3 / 9 / 10 q / 204 ms | 3 / 9 / 6 q / 169 ms | 5 q / 268–274 ms | 5 q / 261 ms |
| move the seats slider | 3 / 9 / 8 q / 118 ms | 3 / 9 / 5 q / 88 ms | 6 q / 298–327 ms | 6 q / 310–315 ms |
| clear all | 3 / 11 / 10 q / 231 ms | 3 / 11 / 4 q / 173 ms | 3 q / 288–296 ms | 3 q / 303–316 ms |

Query counts include Statamic's fake SQL for Stache asset lookups. Stache ranges are two alternating on/off runs, i.e. noise. Counts and bounds are identical on/off after every step in both drivers.

Browser (headless Chrome, `/advanced`, 5 interactions, engine off vs on): no static cache, `half`, and `full` (served from `public/static` by a rewrite router, filters and collection in `{{ nocache }}`) on Stache/40 cars, Eloquent/40 cars, and a temporary page with the collection before the filters (`register-facet` path). Every combination: the same POST count per step (page load 1, or 2 with `full`), identical counts, slider bounds and entries. The collection's first token carried all 7 facets under every strategy; on the reversed page the event registered them without an extra request. Eloquent/5,000, no cache, sum of TTFBs, median of 3: page load 179 → 120 ms, check a brand 341 → 274 ms, pick a transmission 300 → 260 ms, move the seats slider 207 → 184 ms, uncheck the brand 213 → 158 ms.

Not covered: the Eloquent `prefetch()` grouping is exercised by the demo benchmarks and the real-SQLite `pluckMany()` test, not by an addon test on a real Eloquent repository (the addon's dev dependencies don't include the driver); multi-site Eloquent; Livewire 3.

## STOP conditions

- Any count or bound differs between flag on and off in any bench step or browser check.
- The page load makes more POSTs with the flag on than off.
- `RangeBoundsQuery` tokens without `facets` (custom dispatchers, tests) stop working.

## Compatibility & docs

- New config flag `enable_facet_engine`, default `false`; only meaningful with counts or bounds on, and only pays off on the Eloquent driver.
- No view, event or payload changes; custom views keep `counts-updated` and `range-bounds-updated`.
- Lazy collections and filters rendered in a request without the collection don't register. Their facets are computed per filter, as today.
