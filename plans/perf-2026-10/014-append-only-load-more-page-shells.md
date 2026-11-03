# Plan 014: Append-only load more for the Antlers collection view via keyed `wire:ignore` page shells (alternative to Plan 006)

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving to the
> next step. If anything in the "STOP conditions" section occurs, stop and
> report — do not improvise. When done, update the status row for this plan
> in `plans/perf-2026-10/README.md`.
>
> **Drift check (run first)**: `git diff --stat 8096a0b..HEAD -- src/Http/Livewire/LivewireCollection.php src/Http/Livewire/Traits/WithPagination.php src/Http/Livewire/Traits/HandleParams.php src/Http/Livewire/Traits/GenerateParams.php resources/views/livewire/livewire-collection.antlers.html tests/Feature/InfiniteScrollAppendTest.php`
> This plan was written on the uncommitted `feature/dynamic-range-bounds` working tree
> (file list in `plans/perf-2026-10/reference/working-tree-status-at-review.txt`). Plans
> 009 and 013 also touch `LivewireCollection.php`/`WithPagination.php`/`HandleParams.php`.
> If any file changed beyond that branch landing, compare the "Current state" excerpts
> against the live code before proceeding; on a mismatch, STOP. **Also check
> `plans/README.md`: if Plan 006 is DONE or IN PROGRESS, mark this plan REJECTED and stop.**

## Status

- **Priority**: P2
- **Effort**: M
- **Risk**: MED
- **Depends on**: a maintainer decision between this plan and Plan 006. Read `plans/006-islands-append-load-more.md` and the "Islands evaluation" section of `plans/README.md` first. 006 reaches the same goal with Livewire 4 islands (`@island … mode: 'append'`): Blade-only wrapper view, requires Livewire ≥ 4.4.4 (a `composer.lock` change), adds an `entry_partial` tag param and a new view file, and already covers the Blade route. This plan is the Antlers-compatible route on Livewire 3 and 4: no new view, no version floor, the stock Antlers view and any published Antlers view work with a small markup change. Both change the load-more contract for published views, so **pick one**; if 006 lands first, this plan is REJECTED.
- **Category**: perf
- **Planned at**: commit `8096a0b` + uncommitted dynamic-range-bounds work, 2026-10-21
- **Implements / refines**: perf review 2026-10 finding #5 (verifier V5, finding A1: reviewer A's page-shells prototype plus the two mitigations verification demanded). Alternative to Plan 006 in `plans/README.md`. Does not touch the second load-more request (LfCount's `entries-updated` listener): that is PERF-05 → Plan 009 / Plan 013.

## Why this matters

`loadMore()` grows `$paginate` by `initialPaginate` and stays on page 1, so the k-th click runs the entries query for k pages, hydrates k·per_page entries, renders them all through the view and sends them all back for Livewire to morph against a DOM that already holds k−1 pages of them. In the demo that is ≈1.65 KB raw and ≈1.3 ms of server time per already-loaded card per click; at N = 3,000 the fixed ≈38 ms per request is Statamic's title multisort of the filtered key set (core, untouched here). This plan keeps `paginate` fixed, fetches only the newest page with `limit`/`offset`, and emits the pages the client already holds as empty `wire:key`'d `wire:ignore` shells so Livewire's morph leaves their DOM alone. A per-click recount plus a "heal" re-render when the total changed keep it correct when entries are published or unpublished between clicks.

**Testbench** (addon vendor: Livewire 4.2.1 / Statamic 6.7.1, Stache, array cache, N = 3,000, `paginate="12"`, stock view, warm indexes, no addon config flags). Before = verifier run on the pristine tree (`reference/014/bench/before-grow-N3000.txt`); after = plan-writer run of this plan's diff with the recount (`after-append-with-recount-N3000.txt`); the prototype without recount is in `after-prototype-no-recount-N3000.txt`.

| Request | Before: ms · entries hydrated · HTML bytes | After (append + recount) | Prototype, no recount |
|---|---|---|---|
| mount | 44.4 · 21 · 3,783 | 41.6 · 23 · 4,362 | 41.5 · 24 · 4,335 |
| loadMore #1 | 45.5 · 33 · 3,224 | 40.7 · 23 · 2,482 | 38.7 · 22 · 2,482 |
| loadMore #3 | 49.7 · 57 · 5,885 | 41.5 · 23 · 2,634 | 40.0 · 22 · 2,634 |
| loadMore #5 | 53.5 · 81 · 8,547 | 41.3 · 23 · 2,786 | 39.9 · 22 · 2,786 |
| filter after 5 loads | 16.2 · 22 · 1,898 | 14.6 · 24 · 2,406 | 15.9 · 25 · 2,406 |

The recount is one unfiltered Stache count here (≈1.5 ms); with an active multiselect filter it is 4–11 ms (Plan 012's `count()` column). Cold-index rows (first request after `stache:clear`, 420–440 ms) are identical in both modes and belong to Plan 015.

**Browser** (demo app `/infinite-scroll`: 40 cars, `paginate="6"`, published card view with Glide images, Livewire 4.3.1 / Statamic 6.21, `php artisan serve`, raw bytes, no gzip; verifier captures of the prototype in `reference/014/evidence/browser-infinite-scroll-{before,after}.txt`; the recount adds well under a millisecond at 40 entries):

| Click | Before: bytes down · TTFB · cards in DOM | After |
|---|---|---|
| #1 | 22,120 B · 74 ms · 12 (unique 12) | 12,240 B · 62 ms · 12 (unique 12) |
| #3 | 42,031 B · 106 ms · 24 | 12,373 B · 53 ms · 24 |
| #5 | 62,107 B · 113 ms · 36 | 12,507 B · 67 ms · 36 |

Filter, sort and clear-all after several loads are unchanged in cost; they re-render page 1 under a new generation (`browser-breakit-filter-sort-clear.txt`). An exact multiple of the page size (`paginate="8"`, 40 cars) hides the button after click 4 without an empty fifth request (`browser-exact-multiple-paginate8.txt`). Back/forward (the URL handler reloads on popstate either way), `scrollTo`, `lazy` and `nocache` were exercised and unaffected.

## Current state

**Load more grows the page size.** `src/Http/Livewire/Traits/WithPagination.php:25-34`:
```php
    public function loadMore(): void
    {
        if (! $this->infiniteScroll || ! $this->hasMorePages) {
            return;
        }

        $this->markCollectionStateChanged();

        $this->paginate = (int) $this->paginate + (int) $this->initialPaginate;   // line 33
    }
```
`withPagination()` (`:9-23`) returns `entries`, `links`, `pagination_total`, `infinite_scroll` and, for infinite scroll, `has_more_pages` (also stored in `$this->hasMorePages`).

**Rendering.** `src/Http/Livewire/LivewireCollection.php:235-249` `entries()` runs `new Entries($this->generateParams())`, the `livewire-fetched-entries` hook, `updateCustomQueryStringUrl()`, then `withPagination()`. `render()` (`:251-261`) spreads that array into the view with `->with([...$entries])`. `resetPagination()` (`:211-221`) resets `$paginate` to `$initialPaginate` and the page on every filter/sort/clear path. Locked props `$paginate`, `$infiniteScroll`, `$initialPaginate`, `$hasMorePages` are at `:51-61`; `mount()` sets `$initialPaginate` at `:82` and disables infinite scroll without a numeric page size at `:84-86`.

**Params.** `src/Http/Livewire/Traits/GenerateParams.php:16-23` merges the locked `from`/`paginate` last; `removeBlockedQueryParams()` (`:38-48`) strips `blocked_query_params` — which include `limit`, `offset`, `paginate` (`config/config.php:51-53`) — from client state before that merge, so a server-side override merged after it is not stripped. `src/Http/Livewire/Traits/HandleParams.php:129-134` `extractInfiniteScroll()` reads the tag param with `filter_var(..., FILTER_VALIDATE_BOOLEAN)`; `rejectTagOnlyParams()` (`:419-425`) already lists `infinite_scroll`, so URL state can never set it. `paginationIsReset()` (`:730-741`) treats infinite scroll as reset only when `paginate == initialPaginate`.

**Stock view.** `resources/views/livewire/livewire-collection.antlers.html:1-17`: an `aria-live` wrapper with `{{ entries }}<div wire:key="{{ id }}">{{ title }}</div>{{ /entries }}`, then `{{ livewire-filters:load_more }}` or `{{ links }}`. The load-more tag (`src/Tags/LivewireFilters.php:41-52`) reads `has_more_pages` from the context.

**Docs statement that changes.** `README.md:55` and public docs `usage/livewire-collection-tag.md:138`: "Each load grows the page size by the initial `paginate` value".

## Scope

**In scope** (the only files you should modify):
- `src/Http/Livewire/LivewireCollection.php`, `src/Http/Livewire/Traits/WithPagination.php`, `src/Http/Livewire/Traits/HandleParams.php`, `src/Http/Livewire/Traits/GenerateParams.php`
- `resources/views/livewire/livewire-collection.antlers.html`
- `tests/Feature/InfiniteScrollAppendTest.php` (new)
- `README.md:55` (one sentence) and the demo page/view (outside this repo)

**Out of scope** (do NOT touch):
- `resources/views/livewire/ui/load-more.antlers.html` and `src/Tags/LivewireFilters::loadMore()`: the trigger is identical in both modes.
- `tests/Feature/LivewireCollectionComponentTest.php`: its infinite-scroll tests pin the default mode and must pass unchanged.
- `rejectTagOnlyParams()`: `infinite_scroll` is already listed; nothing to add.
- Livewire islands, `@island`, `renderIsland()`, Blade wrapper views: Plan 006.
- The second load-more POST from LfCount: Plans 009/013.

## The change

Design decisions, in one line each: **opt-in** via the tag value `infinite_scroll="append"` (the boolean keeps today's behaviour; a config flag would be wrong because the view markup changes per view; the word "islands" is reserved for Plan 006's Livewire islands); **recount every request** instead of reusing the locked total; **heal** when the total differs from the previous request's `entriesCount` (re-send every loaded page under a new generation, which replaces the client's shells); shells carry `wire:ignore` from their first render; `loaded_count`, `per_page` and `append_pages` are exposed to the view in **both** infinite-scroll modes so one template can serve either.

1. **`src/Http/Livewire/Traits/GenerateParams.php`** — `generateParams(array $overrides = [])`: after the existing merge, apply the overrides; a `null` value removes the key (`'paginate' => null`). Do not use `array_filter` over the whole array as the prototype did — that would also drop any legitimately null tag param.
2. **`src/Http/Livewire/Traits/HandleParams.php`** — `extractInfiniteScroll()`: pull the value; `$this->appendPages = is_string($value) && strtolower(trim($value)) === 'append'`; `$this->infiniteScroll = $this->appendPages || filter_var($value, FILTER_VALIDATE_BOOLEAN)`. `paginationIsReset()`: the infinite-scroll early return also fires when `$this->loadedPages !== 1`, so a clear-all with nothing to remove still resets the loaded pages.
3. **`src/Http/Livewire/LivewireCollection.php`** — three `#[Locked]` props after `$hasMorePages`: `bool $appendPages = false`, `int $loadedPages = 1`, `int $pageGeneration = 0`. In `resetPagination()`'s infinite-scroll branch also set `loadedPages = 1` and `pageGeneration++`. In `entries()` return `$this->appendModeEntries()` first when `$this->infiniteScroll && $this->appendPages`. Add `protected function appendModeEntries(): array`: `$perPage = (int) $this->initialPaginate`; `$total = (new Entries($this->generateParams(['paginate' => null])))->count()`; `$healed = $this->loadedPages > 1 && is_numeric($this->entriesCount) && (int) $this->entriesCount !== $total` (on a load-more request `entriesCount` still holds the previous request's total); if healed, `pageGeneration++`; `$offset = $healed ? 0 : ($this->loadedPages - 1) * $perPage`, `$limit = $healed ? $this->loadedPages * $perPage : $perPage`; fetch with `generateParams(['paginate' => null, 'limit' => $limit, 'offset' => $offset > 0 ? $offset : null])`, run the `livewire-fetched-entries` hook and `updateCustomQueryStringUrl()` exactly as `entries()` does; `$loadedCount = $offset + $entries->count()`; `$this->hasMorePages = $loadedCount < $total`; return `entries`, `links` (`''`), `pagination_total` (`$total`), `infinite_scroll` (true), `has_more_pages`, `loaded_count`, `per_page`, `append_pages` (true), `previous_pages` (`range(1, loadedPages - 1)` or `[]`), `current_page` (`loadedPages`), `page_generation`.
4. **`src/Http/Livewire/Traits/WithPagination.php`** — `withPagination()`: inside the infinite-scroll branch also set `loaded_count` (`count($paginator->items())`), `per_page` (`(int) $this->initialPaginate`), `append_pages` (false). `loadMore()`: after `markCollectionStateChanged()`, `if ($this->appendPages) { $this->loadedPages++; return; }`, else the existing growth line.
5. **`resources/views/livewire/livewire-collection.antlers.html`** — inside the `aria-live` wrapper: `{{ if append_pages }}` → `{{ previous_pages }}<div wire:key="lf-page-{{ page_generation }}-{{ value }}" wire:ignore></div>{{ /previous_pages }}` then `<div wire:key="lf-page-{{ page_generation }}-{{ current_page }}" wire:ignore>` wrapping the existing `{{ entries }}` loop; `{{ else }}` the existing loop at its original indentation (grow mode output stays byte-identical apart from two line breaks). The heal response is simply a current shell holding every loaded page plus empty earlier shells, so the client's old-generation shells are removed by the key change.
6. **`tests/Feature/InfiniteScrollAppendTest.php`** — new file from `reference/014/tests/Feature/`.
7. **Docs and demo** — see "Compatibility & docs"; the exact site-owner view change is `reference/014/infinite-scroll.antlers.html.before` → `.after` (the `.after` file is the verifier's; keep its shells, `class="contents"` and `loaded_count`, and set `infinite_scroll="append"` on the page's tag).

Reference implementation: `plans/perf-2026-10/reference/014/014-append-only-load-more.diff` (apply from the addon root with `git apply` or `patch -p1`; it was generated against the working tree at review time). Reviewer A's prototype as verified is `reference/014/evidence/`'s subject; the differences from it are the recount, the heal, the opt-in and the explicit override semantics in step 1.

## Tests to add

`tests/Feature/InfiniteScrollAppendTest.php` (namespace `Reach\StatamicLivewireFilters\Tests\Feature`, class `InfiniteScrollAppendTest`). Fixture: `clothes` with titles B…F, `paginate => 2`, `infinite_scroll => 'append'`. Helpers parse the response into `wire:key="lf-page-{gen}-{n}"` shells and model the client DOM under keyed morph + `wire:ignore` (existing shell keeps its content, new shell takes the response's, missing shell is removed).

| Test | Proves |
|---|---|
| `append_mode_is_opt_in_and_the_boolean_flag_keeps_growing_the_page_size` | `true` → `paginate` 4, no shells; `append` → `paginate` stays 2, `loadedPages` 2, shells `lf-page-0-1` (empty) + `lf-page-0-2` (D, E). |
| `append_mode_cannot_be_enabled_from_url_state` | `params[infinite_scroll]=append` in the query string is rejected (tag-only). |
| `load_more_renders_only_the_newest_page_with_the_right_offset` | Responses carry B,C / D,E / F; the `offset` override survives `blocked_query_params`; `hasMorePages` ends false. |
| `has_more_pages_is_false_once_an_exact_multiple_of_the_page_size_is_loaded` | 6 entries → no empty fourth request. |
| `the_view_receives_loaded_count_and_per_page_in_both_modes` | `viewData` has `loaded_count`/`per_page`/`append_pages`/`pagination_total` in append and grow mode. |
| `filter_sort_and_clear_all_bump_the_generation_and_reset_to_page_one` | Each path resets `loadedPages` and increments `pageGeneration`; the new page 1 is rendered. |
| `clear_all_without_active_filters_still_resets_the_loaded_pages` | The `paginationIsReset()` change. |
| `entries_count_reflects_an_entry_published_between_load_more_clicks` | The recount: 5 → 6. (Fails on the no-recount prototype: `verifier-tests-on-prototype-no-recount.txt`.) |
| `load_more_never_leaves_a_duplicate_entry_in_the_client_dom` | Insert A before the offset → heal: generation 1, one shell with A…F, no duplicate, `hasMorePages` false. (Duplicate `E` on the prototype.) |
| `an_entry_unpublished_between_clicks_does_not_leave_a_gap_in_the_client_dom` | Delete C → DOM is exactly B, D, E, F. |
| `a_steady_result_set_keeps_appending_after_a_heal` | After a heal the next click appends only the next page again. |

**Existing tests: none change.** The verifier's default-on prototype broke the 10 `assertSet('paginate', n)` growth assertions in `LivewireCollectionComponentTest.php:1266-1646`; because this plan is opt-in those tests now pin the default mode and must stay green. Suite 406 → 417.

## Verification

1. Apply the diff. `vendor/bin/phpunit --filter InfiniteScrollAppendTest` → `OK (11 tests, 71 assertions)`.
2. `vendor/bin/phpunit --filter LivewireCollectionComponentTest` → all pass, unchanged file.
3. `vendor/bin/phpunit` → `OK (417 tests, 1339 assertions)` on the review-time tree.
4. `vendor/bin/pint --dirty --format agent` → `{"result":"pass"}`.
5. Bench (~2.5 min): copy `reference/014/bench/CollectionBenchTest.php` to `tests/Bench/`; run `BENCH_N=3000 vendor/bin/phpunit --filter it_benchmarks_infinite_scroll_load_more_growth tests/Bench/CollectionBenchTest.php` (grow, the "before") and again with `BENCH_INFINITE_SCROLL=append` prefixed; read `.bench-results-array-it_benchmarks_infinite_scroll_load_more_growth.txt` after each. Warm rows: grow `loadMore #5` ≈ 50–56 ms, `item gets 81`, html ≈ 8.5 KB; append `loadMore #1…#5` ≈ 40–43 ms each, `item gets 23`, html 2.5–2.8 KB, `entries rendered 12` (the bench excludes shells from the count; the "!! expected 12, counted 13" line at mount is the wrapper and appears in both modes). ±25 % on ms is noise; hydrated entries and bytes are deterministic. Delete `tests/Bench/`, `.bench-results-array-*.txt`, `.bench-sample.html`, `tests/__fixtures__/dev-null-bench-array/` afterwards.
6. Browser (demo app, which symlinks this addon): on the `/infinite-scroll` page template set `infinite_scroll="append"` and replace the view with the `.after` reference. Click load more five times with the network tab on `livewire/update`: each response ≈12 KB (not growing), cards 12/18/24/30/36 all unique, DOM shells `lf-page-0-1…n`, "Showing 36 of 40 cars". Tick a brand: shells become `lf-page-1-1`, old cards gone. Sort: `lf-page-2-1`, order flipped. Clear all: `lf-page-3-1`, 6 cards, sort kept. Then publish a 41st car in the CP, click load more: the response carries every loaded card again, "of 41", no duplicate card.

## Done criteria

ALL must hold:

- [ ] `infinite_scroll="true"` behaves exactly as before (`paginate` grows; no `lf-page-` shells; every existing test green).
- [ ] `infinite_scroll="append"` keeps `paginate` fixed, bumps `loadedPages`, renders one page per load-more response inside a `wire:ignore` shell, and lists earlier pages as empty keyed shells.
- [ ] Every append-mode request recounts the total; a changed total bumps `pageGeneration` and re-sends all loaded pages.
- [ ] `loaded_count`, `per_page`, `append_pages` reach the view in both modes; `pagination_total` is the recounted total.
- [ ] `grep -n "whereJsonOverlaps\|renderIsland\|@island" src/ resources/` finds nothing from this plan (no islands, no unrelated hunks).
- [ ] `vendor/bin/phpunit` exits 0 (417 tests on the review-time tree); `vendor/bin/pint --dirty --format agent` exits 0; bench shows flat hydration (`item gets 23`) and ≈2.5–2.8 KB per append response.
- [ ] README sentence, changelog line and docs text handed over; demo `/infinite-scroll` ported; `plans/perf-2026-10/README.md` status row updated (and Plan 006's row annotated with the decision).

## STOP conditions

- `plans/README.md` shows Plan 006 DONE or IN PROGRESS → REJECTED, stop.
- Any infinite-scroll test in `LivewireCollectionComponentTest` fails: the grow path changed, which this plan must not do.
- `load_more_renders_only_the_newest_page_with_the_right_offset` renders B,C on the second request: the `offset` override is being stripped — the merge order in `generateParams()` changed. Report.
- `Statamic\Tags\Collection\Entries` has no `count()` (`vendor/statamic/cms/src/Tags/Collection/Entries.php:56` today).
- The rendered HTML's shells lack `wire:ignore` on the first render (grep the test output for `wire:ignore>`): the morph would re-render them on the next request and the whole point is lost.
- Suite count differs from 406 + 11 + other landed plans' tests.

## What verification refuted

Do **not** implement these simplifications; each was tried and observed to break:

- **Reusing the locked total between clicks (no recount).** Reviewer A's `$total = loadedPages > 1 ? (int) $this->entriesCount : count()`. With an entry published between clicks the view kept "of 40" while 41 existed, no `entries-updated` was sent, and the next page repeated a card (`browser-offset-drift-stale-count.txt`: `cards=12 unique=11`, duplicate `wire:key`). The current grow design never does this because it re-renders everything. Hence the per-click recount **and** the heal; the recount alone would fix the number but still duplicate the card.
- **`limit = perPage + 1` to avoid a count.** Correct observation (Stache `paginate()` counts the already-filtered keys, so there is no cheaper middle ground), but it cannot detect drift; the plan derives `has_more_pages` from the recounted total instead.
- **Default-on.** It changes the published-view contract: `{{ entries | length }}` shows only the newest page (hence `loaded_count`), `{{ if (entries|length) > 0 }}`'s else-branch would drop the shells (test `pagination_total` instead), grid wrappers need `class="contents"` on every shell, the README's "grows the page size" statement becomes false, and 10 existing tests assert the growth. Shipping opt-in keeps every existing site byte-identical.
- **Not refuted, verified fine**: shells survive load-more and are replaced on filter (gen 0 → 1), sort (gen 2, order flipped) and LfTags clear-all (gen 3, page 1, sort persists); exact multiples; `offset` in `blocked_query_params` (override merged after the strip); back/forward, `scrollTo`, `lazy`, `nocache`.

## Compatibility & docs

- **Published views (append mode only)**: the view must emit the shells exactly as the stock view does (`wire:key="lf-page-{{ page_generation }}-{{ value }}"` + `wire:ignore` for `previous_pages`, the same for the current page wrapping the loop); use `{{ loaded_count }}`/`{{ pagination_total }}` instead of `{{ entries | length }}`; guard the empty state with `{{ if pagination_total > 0 }}`; add `class="contents"` to the shells inside CSS grids/flex rows; keep `wire:key="{{ id }}"` on each entry. Because earlier pages are `wire:ignore`, server-side changes to already-loaded cards (edited titles, `wire:transition`) only show after a filter/sort/clear or a heal. Grow mode (`infinite_scroll="true"`) is unchanged; it only gains the three extra view variables.
- **Config flags**: none added. `custom_query_string`, `enable_query_string`, counts and bounds are independent of the mode (verified in the browser with counts + bounds on).
- **Livewire 3 vs 4**: `wire:key` + `wire:ignore` morph semantics are the same; verified on 4.2.1 (testbench) and 4.3.1 (browser), Livewire 3 not run.
- **Stache vs Eloquent**: `limit`/`offset`/`count()` go through the generic query builder; Eloquent untested and uses the same code path (no Stache-only API is used).
- **Multi-site**: the offset query uses the same hardened params as `entries()`, so site scoping is unchanged.
- **Changelog line**: "New `infinite_scroll="append"` mode: load more sends only the newest page and keeps the earlier pages on the client (≈80 % fewer bytes after a few loads); the total is recounted on every click and the list repairs itself if entries change in between. Views used in this mode need the page shells and `loaded_count` (see docs). `infinite_scroll="true"` is unchanged."
- **Public docs** (`/Volumes/1TB/Sites/livewirefilters-docs`, read-only here): `usage/livewire-collection-tag.md` "Infinite scroll (load more)" (`:118-163`): add the `append` value, the view contract above, and change `:138` to describe both modes; `README.md:55` likewise. Demo: port `/infinite-scroll` to `append` using the `.after` reference view.
