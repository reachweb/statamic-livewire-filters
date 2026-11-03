# Plan 013: Skip the `entries-updated` dispatch on page-change requests when count and active filters did not change

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving to the
> next step. If anything in the "STOP conditions" section occurs, stop and
> report — do not improvise. When done, update the status row for this plan
> in `plans/perf-2026-10/README.md`.
>
> **Drift check (run first)**: `git diff --stat 8096a0b..HEAD -- src/Http/Livewire/LivewireCollection.php src/Http/Livewire/Traits/WithPagination.php tests/Feature/EntriesUpdatedDispatchTest.php`
> This plan was written on the uncommitted `feature/dynamic-range-bounds` working tree
> (file list in `plans/perf-2026-10/reference/working-tree-status-at-review.txt`). If
> either source file changed beyond that branch landing (Plan 009 and Plan 014 both touch
> `LivewireCollection.php`; 014 also touches `WithPagination.php`), compare the "Current
> state" excerpts against the live code before proceeding; on a mismatch, STOP.

## Status

- **Priority**: P3
- **Effort**: S
- **Risk**: LOW
- **Depends on**: Plan 009. Read this first: 009 replaces LfCount's server listener with an Alpine listener on the browser event, so on the stock setup the `entries-updated` dispatch no longer costs a POST and this plan saves **nothing** there. It still matters for any *other* server listener a site has published on `entries-updated` (a kept `#[On('entries-updated')]` in a customised LfCount, a custom component) — `rendered()`'s dispatch costs one POST per such listener on every render. Apply after 009; if after 009 the maintainer has no such listener, mark this plan REJECTED ("moot after 009") rather than landing dead weight.
- **Category**: perf
- **Planned at**: commit `8096a0b` + uncommitted dynamic-range-bounds work, 2026-10-21
- **Implements / refines**: perf review 2026-10 finding #7 (verifier V5, finding A3, page-change-only variant). Refines PERF-05 in `plans/README.md` (LfCount via browser events, which is Plan 009's route): where 009 removes the listener, this plan removes the dispatch on the one request type where it is provably redundant. Reviewer A's broader variant was refuted (see below).

## Why this matters

`LivewireCollection::rendered()` dispatches `entries-updated` with `count` and `active` after **every** render (`src/Http/Livewire/LivewireCollection.php:270-273`). `LfCount` listens to it on the server (`src/Http/Livewire/LfCount.php:14-18`), so every collection render is followed by a second Livewire POST that hydrates LfCount, sets `$count`, re-renders the count view and returns 618 B. A numbered-page click (`gotoPage`/`nextPage`/`previousPage`, bundled view `resources/views/livewire/ui/pagination.blade.php:25,49,66`) and a `loadMore` cannot change the total or the active-filter count, so that POST re-renders the same number. The fix flags the request as "page change only" in the pagination actions, compares `[entriesCount, activeFilters]` before and after `render()`, and skips the dispatch only when both hold. Everything else keeps dispatching, because published views use `entries-updated` as the "entries are on the page" signal (see "What verification refuted").

All numbers: headless browser on the demo app (40 cars, Stache, Livewire 4.3.1 / Statamic 6.21, `php artisan serve`, raw bytes, no gzip), `/advanced` with `enable_filter_values_count` + `enable_dynamic_range_bounds` on unless stated; request counts and bytes are deterministic, TTFB ±15 ms. Captures in `reference/013/evidence/`.

| Interaction | Before | After |
|---|---|---|
| `/advanced` gotoPage 2 | 2 POSTs: collection 50,508 B / 73 ms + lf-count 618 B / 15 ms | 1 POST: collection 50,520 B / 63 ms |
| `/advanced` gotoPage 3 | 2 POSTs: 50,680 B / 69 ms + 618 B / 16 ms | 1 POST: 50,606 B / 58 ms |
| `/advanced` gotoPage 2, counts + bounds **off** | 2 POSTs (same shape; lf-count 618 B / 15 ms) | 1 POST |
| `/infinite-scroll` loadMore ×5 | 1 POST each (that page has no LfCount) | 1 POST each — nothing to save |

Saved per page click: one POST of ~618 B down / 544 B up and ~15 ms server time plus a full request round trip of browser latency. Filter, sort and clear interactions are byte-for-byte unchanged.

## Current state

**The dispatch.** `src/Http/Livewire/LivewireCollection.php:270-273`:
```php
    public function rendered()
    {
        $this->dispatch('entries-updated', count: $this->entriesCount, active: $this->activeFilters);
    }
```
`render()` (`:251-261`) sets `$this->entriesCount = $this->countAllEntries($entries)` and `$this->activeFilters = $this->getParamsCount()` after `$this->entries()`; both are `#[Locked]` public props (`:27-31`), so their previous values are available from the snapshot at the start of a request.

**The listener.** `src/Http/Livewire/LfCount.php:14-18`: `#[On('entries-updated')] public function updatedCount($count) { $this->count = $count; }`.

**Pagination actions.** `src/Http/Livewire/Traits/WithPagination.php:7` uses `\Livewire\WithPagination` unchanged (`gotoPage($page, $pageName = 'page')`, `nextPage($pageName = 'page')`, `previousPage($pageName = 'page')` in `vendor/livewire/livewire/src/Features/SupportPagination/HandlesPagination.php:24-34`). `loadMore()` (`:25-34`) calls `markCollectionStateChanged()` and grows `$this->paginate`.

**Every non-page change goes through `resetPagination()`** (`LivewireCollection.php:211-221`, first line `$this->markCollectionStateChanged();`): `filterUpdated` (`:159`), `sortUpdated` (`:186`), `clearFilter` (`Traits/HandleParams.php:203`), `clearOptionParam` (`:289`), `clearAllFilterParams` (`:607`, `:614`). `markCollectionStateChanged()` itself (`HandleParams.php:751-756`) cannot serve as the "non-page change" flag because `loadMore` (`WithPagination.php:31`) and `dispatchParamsUpdated()` (`HandleParams.php:780`) call it too.

**Published consumers of the event.** Demo `resources/views/vendor/statamic-livewire-filters/livewire/animations.antlers.html:4-11` starts a loading state on `filter-updated` and ends it on `entries-updated`; `examples/advanced.antlers.html:16,62` (and basic, preset) read `count`/`active` from the browser event; public docs `advanced/transitions-animations.md:47` describe `entries-updated` as "fired once the entries have been retrieved and are prepared for display".

## Commands you will need

| Purpose | Command | Expected on success |
|---|---|---|
| New tests | `vendor/bin/phpunit --filter EntriesUpdatedDispatchTest` | `OK (8 tests, 34 assertions)` |
| Collection tests | `vendor/bin/phpunit --filter LivewireCollectionComponentTest` | all pass, file unchanged |
| LfCount tests | `vendor/bin/phpunit --filter LfCountTest` | all pass (the listener itself is untouched) |
| Full suite | `vendor/bin/phpunit` | `OK (414 tests, …)` on the review-time tree |
| Format | `vendor/bin/pint --dirty --format agent` | `{"result":"pass"}` |

## Scope

**In scope** (the only files you should modify):
- `src/Http/Livewire/LivewireCollection.php` (three flags, `resetPagination()`, `render()`, `rendered()`)
- `src/Http/Livewire/Traits/WithPagination.php` (action overrides, `loadMore()` flag)
- `tests/Feature/EntriesUpdatedDispatchTest.php` (new)

**Out of scope** (do NOT touch):
- `src/Http/Livewire/LfCount.php` and `resources/views/livewire/ui/count.blade.php`: Plan 009's territory.
- `markCollectionStateChanged()` / `skipRenderIfCollectionStateUnchanged()` in `HandleParams.php`: the render-skip machinery is unrelated to the dispatch and must keep its current semantics.
- The `entries-updated` payload shape (`count`, `active`): published views read both.
- Any skip outside the four pagination actions.

## The change

1. **`src/Http/Livewire/LivewireCollection.php`** — add three request-scoped (non-persisted, `protected bool`) flags after `$scrollTo`: `$pageChangeRequested = false` (set by the pagination actions), `$nonPageChangeRequested = false` (set by `resetPagination()`, i.e. every filter/sort/clear path, whatever the call order inside a pooled request), `$entriesStateChanged = true` (computed in `render()`).
2. Same file, `resetPagination()`: set `$this->nonPageChangeRequested = true` right after `markCollectionStateChanged()`.
3. Same file, `render()`: capture `$previous = [$this->entriesCount, $this->activeFilters]` before `entries()`; after the two assignments set `$this->entriesStateChanged = $previous !== [$this->entriesCount, $this->activeFilters]`. On the first render `entriesCount` is `null`, so this is always `true` at mount.
4. Same file, `rendered()`: `if ($this->pageChangeRequested && ! $this->nonPageChangeRequested && ! $this->entriesStateChanged) { return; }` before the dispatch, with a docblock stating why only page changes may skip.
5. **`src/Http/Livewire/Traits/WithPagination.php`** — alias Livewire's `gotoPage`, `nextPage`, `previousPage` as `protected livewireGotoPage/livewireNextPage/livewirePreviousPage` in the `use` block, and define public methods with the same signatures that set `$this->pageChangeRequested = true` and call the alias. In `loadMore()` set `$this->pageChangeRequested = true` after `markCollectionStateChanged()`; the page-size growth is unchanged.
6. **`tests/Feature/EntriesUpdatedDispatchTest.php`** — new file from `reference/013/tests/Feature/`.

Reference implementation: `plans/perf-2026-10/reference/013/013-skip-entries-updated-on-page-change.diff` (apply from the addon root with `git apply` or `patch -p1`; it was generated against the working tree at review time). If Plan 014 has landed, its `loadMore()` has two branches; put the flag before the branch.

Rejected variants: reviewer A's unconditional "skip whenever count and active are unchanged" (refuted in the browser, section below); reusing `collectionStateChangedThisRequest` as the non-page flag (set by `loadMore` and `dispatchParamsUpdated` too, so a load more would never skip and a clear with a redundant relay would).

## Tests to add

`tests/Feature/EntriesUpdatedDispatchTest.php` (namespace `Reach\StatamicLivewireFilters\Tests\Feature`, class `EntriesUpdatedDispatchTest`). Fixture: `clothes` collection with three entries. Livewire's `assertDispatched`/`assertNotDispatched` inspect the **last** request's effects, so each `call()`/`dispatch()` is asserted on its own.

| Test | Proves |
|---|---|
| `entries_updated_is_dispatched_on_the_first_render` | Mount always dispatches (`count: 3, active: 0`). |
| `entries_updated_is_dispatched_when_a_filter_change_leaves_count_and_active_unchanged` | "Shir" → "Shirt" (3 → 3, active 1 → 1) still dispatches. The animations-view guard. |
| `entries_updated_is_dispatched_when_switching_between_equal_count_values` | `is Red Shirt` → `is Black Shirt` (1 → 1) still dispatches and the HTML changed. |
| `a_sort_change_is_dispatched_even_when_the_count_is_unchanged` | Sort goes through `resetPagination()` → dispatch. |
| `a_page_change_that_changes_nothing_skips_entries_updated` | `gotoPage 2`, `previousPage`, `nextPage` each render the new page and dispatch nothing. **Fails on current code.** |
| `a_load_more_that_changes_nothing_skips_entries_updated` | `loadMore` grows to 4 and dispatches nothing. **Fails on current code.** |
| `a_page_change_still_dispatches_when_the_total_changed_in_between` | An entry published between two requests makes the page click dispatch `count: 4`. |
| `a_filter_change_in_the_request_after_a_page_change_is_dispatched` | Flags are request-scoped: the next request's filter dispatches (its intermediate `assertNotDispatched` makes it **fail on current code** too). |

Existing tests: none change. Suite 406 → 414. Verifier evidence on the refuted variant: `reference/013/evidence/verifier-tests-on-reviewer-A-prototype.txt` (the two "still dispatches" tests fail there) vs `verifier-tests-on-current-code.txt`.

## Verification

1. Copy the test in and run it on the **unchanged** code: `vendor/bin/phpunit --filter EntriesUpdatedDispatchTest` → 3 failures (`a_page_change_that_changes_nothing_skips_entries_updated`, `a_load_more_that_changes_nothing_skips_entries_updated`, `a_filter_change_in_the_request_after_a_page_change_is_dispatched`), 5 passes.
2. Apply the change. `vendor/bin/phpunit --filter EntriesUpdatedDispatchTest` → `OK (8 tests, 34 assertions)`.
3. `vendor/bin/phpunit --filter LivewireCollectionComponentTest` → all pass (pagination and infinite-scroll tests exercise the overridden actions).
4. `vendor/bin/phpunit` → `OK (414 tests, 1302 assertions)` on the review-time tree (406 + 8; add other landed plans' tests).
5. `vendor/bin/pint --dirty --format agent` → `{"result":"pass"}`.
6. Browser check (demo app, which symlinks this addon): open `/advanced` with the network tab filtered to `livewire/update`; click page 2. Expect **one** POST (the collection) and no follow-up POST whose payload calls `__dispatch("entries-updated", …)` on `lf-count`; the "40 cars found." text is unchanged. Then open `/animations`, type `Aud`, then `Audi` in the text filter: after each keystroke the two Audi cards must be visible (not stuck transparent), proving the filter path still dispatches. Finally on `/advanced` tick a brand: the count text updates (filter path unchanged).

## Done criteria

ALL must hold:

- [ ] `rendered()` skips the dispatch only when `pageChangeRequested && ! nonPageChangeRequested && ! entriesStateChanged`; `resetPagination()` sets `nonPageChangeRequested`; `render()` computes `entriesStateChanged` from the pre-render `[entriesCount, activeFilters]`.
- [ ] `gotoPage`, `nextPage`, `previousPage` and `loadMore` are the only places that set `pageChangeRequested`.
- [ ] The three tests listed in Verification step 1 were seen failing before the change; all eight pass after.
- [ ] `vendor/bin/phpunit` exits 0 (414 tests on the review-time tree) and `vendor/bin/pint --dirty --format agent` exits 0.
- [ ] Browser check done: `/advanced` page click = 1 POST, `/animations` "Aud" → "Audi" cards visible.
- [ ] Only the three in-scope files are modified; changelog line and docs note handed over; `plans/perf-2026-10/README.md` status row updated (or REJECTED with the 009 rationale).

## STOP conditions

- Step 1 shows fewer than 3 failures: the current code already skips somewhere (Plan 009 or 014 may have changed `rendered()`); re-read the live `rendered()` and report.
- After the change any of the five "still dispatches" tests fails, or the `/animations` check leaves cards transparent: the guard is wider than a page change. Do not loosen the tests.
- The bundled pagination view no longer calls `gotoPage`/`nextPage`/`previousPage` (e.g. it calls `setPage`): extend the overrides to that method or STOP.
- Livewire's `HandlesPagination` signatures differ from `gotoPage($page, $pageName = 'page')`, `nextPage($pageName = 'page')`, `previousPage($pageName = 'page')` on the installed version: mirror them exactly.
- `LivewireCollectionComponentTest` has a failure after the change.
- After Plan 009 there is no server listener on `entries-updated` in the project and no published one: update the status row to REJECTED (moot) instead of landing.

## What verification refuted

- **Reviewer A's unconditional skip** (`rendered()` returns whenever `[count, active]` is unchanged, no request-type check). Browser repro on the demo's published `animations` view (`reference/013/evidence/browser-animations-entries-updated-skip.txt`, page template in `animations-example-with-text-filter.antlers.html`): typing `Aud` (2 cars) dispatched normally; typing `Audi` (still 2 cars, still 1 active filter) produced a response with `dispatches=[params-updated,tags-updated,update-url]` and **no** `entries-updated`; the view's `loading` flag never reset and both cards stayed at `opacity-0` after 2 s. The same happens for a same-count select/radio switch and for slider moves that leave the count unchanged. The two test changes reviewer A needed for a green suite were that symptom, not test drift. The public docs document `entries-updated` as the "entries are prepared for display" signal, so it is a published contract for every non-page change.
- **Confirmed safe and kept**: the page-change-only variant (this plan), measured as 2 → 1 POSTs on `/advanced` page clicks with counts on and off, and no change on `/infinite-scroll`.

## Compatibility & docs

- **Published views**: a view that starts a loading state on a *pagination click* and ends it on `entries-updated` would now hang on page clicks that change nothing. The bundled and demo views do not (they key off `filter-updated`, and pagination uses `wire:loading`). Document the exception: use `wire:loading wire:target="gotoPage, nextPage, previousPage, loadMore"` for pagination loading states.
- **Config flags**: none. Behaviour identical with counts/bounds on or off (the count listener and the entries count are unrelated).
- **Livewire 3 vs 4**: trait method aliasing and `rendered()` exist on both; the signatures above are the same in Livewire 3.6 and 4.x. Verified on 4.2.1 (testbench) and 4.3.1 (browser); Livewire 3 not run.
- **Stache vs Eloquent**: not involved.
- **Multi-site**: not involved.
- **Changelog line**: "`entries-updated` is no longer dispatched after a page change (numbered pages or load more) that leaves the entry count and active-filter count unchanged, saving the LfCount round trip. Filters, sorts and clears always dispatch it. If a custom view ends a loading state on this event for pagination clicks, switch it to `wire:loading`."
- **Public docs** (`/Volumes/1TB/Sites/livewirefilters-docs`, read-only here): `advanced/transitions-animations.md:47` — add the page-change exception to the `entries-updated` bullet; `advanced/tips-performance.md:15` ("fires the `entries-updated` event after filtering is done") already reads correctly.

## Maintenance notes

- The three flags are plain `protected` properties, so Livewire neither dehydrates nor hydrates them: they are reset on every request by construction. Do not make them public or `#[Locked]`.
- If a new pagination entry point is added later (for example a `setPage()` call from a custom view), it must set `pageChangeRequested` too, or it simply keeps dispatching — the failure mode is a wasted POST, never a missed update.
- If Plan 014 lands, `loadMore()` sets the flag before branching into append/grow; both branches are page changes. A heal in append mode changes `entriesCount`, so `entriesStateChanged` is true and the dispatch happens, which is what LfCount needs.
- Reviewer check: grep `rendered()` for exactly one early return and confirm no test in `EntriesUpdatedDispatchTest` was weakened to `assertNotDispatched` on a filter, sort or clear.
