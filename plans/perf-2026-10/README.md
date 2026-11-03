# Performance plans — October 2026 review

Generated on 2026-10-21 from a multi-agent performance review of the working tree
on `feature/dynamic-range-bounds` (HEAD `8096a0b` plus the uncommitted
dynamic-range-bounds work; the exact file list is in
`reference/working-tree-status-at-review.txt`). Numbering continues the main
tracker in `plans/README.md` (001–007), so "implement plan 010" is unambiguous.

Each plan is self-contained: the plan file, a reference diff generated against
the working tree at review time (`git apply` / `patch -p1` from the addon root),
the regression tests to add, and the bench or browser evidence, all under
`reference/NNN/`. Nothing outside this folder was changed by the review.

Baseline at time of review: `vendor/bin/phpunit` → **OK (406 tests, 1268 assertions)**, ~49 s.
Style gate: `vendor/bin/pint --dirty --format agent`.

## How to use with Claude Code

Ask: "Implement plan 008 from plans/perf-2026-10". The executor instructions at
the top of each plan tell it to run the drift check first, follow the steps, run
the verification commands, and update the status row below. Land one plan per
branch/PR. The diffs were cut against the same pristine tree, so after the first
plan lands later diffs may need hunks re-applied by hand in the files listed
under "Conflict hotspots" — the plan text, not the diff, is the contract.

## Execution order & status

| Plan | Title | Priority | Effort | Risk | Depends on | Status |
|------|-------|----------|--------|------|------------|--------|
| 008 | Share resolved key sets and drop the sort in faceted count and bounds queries | P1 | M | MED | — | DONE |
| 011 | Slim filter snapshots and resolve term/entry options from Stache indexes | P2 | M | LOW-MED | — | DONE |
| 010 | Skip renders nobody uses: `wire:ignore` roots, disabled-feature listeners, unchanged counts/bounds, foreign clear-option | P1 | M | LOW-MED | — | DONE |
| 009 | Cut Livewire round trips: client-side filter dispatch, zero-request page load, relay-free clear-all, separate bounds event | P1 | L | MED | apply after 010 and 008 | TODO |
| 012 | Multiselect scope: one Stache index pass for all selected values | P2 | S | LOW | — | TODO |
| 013 | Skip the `entries-updated` dispatch on page-change requests when nothing changed | P3 | S | LOW | 009 | TODO |
| 014 | Append-only load more for the Antlers view via keyed `wire:ignore` page shells (alternative to Plan 006) | P2 | M | MED | decision vs Plan 006 | TODO |
| 015 | Document Stache index declarations for filtered and sorted fields | P3 | S | LOW | — | TODO |
| 016 | Facet engine: register facets with the collection and batch their values per filter state (Eloquent) | P2 | M | MED | 008, 010 | DONE |

Status values: TODO | IN PROGRESS | DONE | BLOCKED (one-line reason) | REJECTED (one-line rationale)

## Why this order

1. **008** is isolated (`src/Support/*` plus one small hunk in `HandleFieldBounds`)
   and is the largest server-time win for sites with counts or bounds enabled.
2. **011** touches `HandleFieldOptions`, `HandleStatamicQueries`, `HandleEntriesCount::counts()`
   and the `#[Computed]` attributes on five components. Land it before 010/009 so
   their larger hunks in the same components apply on top of it.
3. **010** adds the request-scoped render guard to `IsLivewireFilter` and the
   `skipRender()` paths in every `Lf*` component. 009 edits the same components'
   `clear()`/`updatedSelected()` methods, so 010 first keeps 009's conflicts to
   whitespace-level.
4. **009** is the biggest user-visible latency change (one hop fewer per
   interaction, no page-load request on the default config). It has four
   independent steps that can land one at a time.
5. **012–015** are independent small items; 013 is nearly moot once 009's
   Alpine-driven `LfCount` lands, and 014 competes with Plan 006 — pick one.

## Conflict hotspots

Files touched by more than one plan (every diff was cut against the pristine tree):

| File | Plans |
|---|---|
| `src/Http/Livewire/LfCheckboxFilter.php`, `LfRadioFilter.php`, `LfDateFilter.php` | 010, 009, 011 |
| `src/Http/Livewire/LfRangeFilter.php`, `LfDualRangeFilter.php`, `LfTextFilter.php`, `LfToggleFilter.php` | 010, 009 |
| `src/Http/Livewire/Traits/HandleFieldBounds.php` | 008, 010, 009 |
| `src/Http/Livewire/Traits/HandleEntriesCount.php` | 010, 011 |
| `src/Http/Livewire/Traits/IsLivewireFilter.php` | 010, 009 |
| `src/Http/Livewire/Traits/HandleParams.php` | 009, 014 |
| `src/Http/Livewire/LivewireCollection.php` | 009, 013, 014 |
| `src/Http/Livewire/Traits/WithPagination.php` | 013, 014 |
| `tests/Feature/HandleFieldBoundsTest.php` | 008, 009 |
| `tests/Feature/LivewireCollectionComponentTest.php` | 009, 014 |

## Stacking test (what to expect when landing in order)

The reference diffs were stacked on a fresh copy of the review-time tree in the
recommended order with `patch -p1`:

| Step | Diff | Result |
|---|---|---|
| 1 | 008 `reference/008/008-counts-pool-keyset-and-reorder.diff` | applies cleanly |
| 2 | 011 `reference/011/011-slim-snapshots-and-index-options.diff` | applies cleanly |
| 3 | 010 `reference/010/tier1-skip-wasted-renders.diff` | 1 hunk rejected in `tests/Feature/HandleEntriesCountTest.php` (011 changed the neighbouring assertion) — re-apply by hand |
| 4 | 009 `reference/009/combined.diff` | hunks rejected in all seven `Lf*Filter.php` components (010 edited the same `clear()` / `updatedSelected()` / `clearOption()` methods) — merge by hand, keeping 010's render guards and 009's relay/flag logic |
| 5 | 012 `reference/012/012-multiselect-overlaps.diff` | applies cleanly |
| 6 | 013 `reference/013/013-skip-entries-updated-on-page-change.diff` | applies cleanly |

The rejected hunks were then merged by hand on that stacked copy and the full
suite run on the result: **OK (501 tests, 1757 assertions)** — the six plans
coexist with no behavioural conflict. The merged tree is captured as
`reference/stacked-008-011-010-009-012-013.diff` (45 files; `git apply --check`
passes on the review-time tree). It is a reference for executors, not a
shortcut: land the plans one at a time.

Merge rules when landing 009 after 010 (the only non-trivial conflicts):

1. In every `clear()`, keep 010's `markFilterStateChanged()` right after the
   value reset, before 009's `clearAllNeedsRelay()` gate.
2. In the checkbox `updatedSelected()`, mark the state change before 009's
   `syncedByView` early return (a flagged commit still changed `selected`).
3. In the checkbox `clearOption()` owning branch, 009 replaces the
   `updatedSelected()` call with `dispatchFilterUpdated()`, so add an explicit
   `markFilterStateChanged()` there and keep 010's trailing
   `skipRenderIfFilterStateUnchanged()`.
4. In the range/dual-range no-relay branch, set `pendingBoundsSelection`
   directly; never call `rememberBoundsSelection()`.
5. Tests: anything dispatching to a range/dual-range filter uses
   `bounds-updated` after 009 step 4; counts-off assertions check that the
   `counts` key is absent and that `counts()` falls back (011); non-rendering
   paths assert via `tap()`/`get()`, not `assertViewHas` (010).

`reference/010/optional-tier2-plain-view-selection-skip.diff` is cut against
tier 1, so it only applies after tier 1 (by design). 014 competes with Plan 006
and overlaps 009/013 in `LivewireCollection.php`, `HandleParams.php` and
`WithPagination.php`; land it last if at all.

## Relationship to the findings already in `plans/README.md`

| Existing ID | Covered by | Note |
|---|---|---|
| PERF-01 (faceted counts do O(filters) scans) | 008 | Confirmed and decomposed: pool keying + default sort. |
| PERF-03 (full `statamic_field` round-trips) | 011 | Payload now measured; the `counts` array is the bulk of it when counts are off. |
| PERF-04 (dispatch from the browser) | 009 | Same idea; this plan adds the `syncedByView` compat flag so published older views keep working. |
| PERF-05 (LfCount via browser events) | 009 | Same idea, plus the mount-time `tags-updated` skip. |
| PERF-06 (one shared facet query) | 008 | Uses the public `getItemValues()` seam instead of `Closure::bind`; has an Eloquent fallback. |
| PERF-07 (`skipRender()` for `wire:ignore` views and disabled listeners) | 010 | Same idea; detection by rendered root tag rather than view name. The "unchanged plain views" variant PERF-07 dropped is 010's optional tier 2, which pays off only at hundreds of options. |
| PERF-08 (initial counts/bounds in a post-load request) | 009 (partly) | Removed only on the default config; with counts/bounds on it remains. |
| Plan 006 (islands append-only load more) | 014 | Alternative route for Antlers views on Livewire 3 and 4; pick one. |
| DIRECTION-04 (all-in-one component) | — | Out of scope here. |

## How the review was done

- Four reviewers with distinct lenses: the collection query path; per-filter
  mount and snapshot cost; faceted counts and dynamic bounds; HTTP round trips
  and rendering. Each worked in its own copy of the addon and prototyped fixes.
- Five adversarial verifiers then re-measured every finding independently in
  fresh copies, applied the fixes, tried to break them (config permutations,
  published views, Livewire pooling orders, multi-site, stale events), added
  regression tests, and ran the full suite on each patched copy.
- Browser numbers were captured with headless Chrome against disposable copies
  of the demo app served by `php artisan serve` with the patched addon symlinked
  in. Byte counts are raw (no gzip); server times are lower than under Herd/TLS.

Four reviewer prototypes were refuted or replaced during verification. Each plan
records them under "What verification refuted" so a later executor does not
simplify back into a broken variant. In short: the count-pool prototype returned
zero counts for multi-collection tags; dynamic listener removal 500s every open
page after a config change; removing the clear-all relay broke tag-authored
presets; skipping `entries-updated` whenever the count is unchanged froze the
animations view; a persistent options cache had unfixable staleness holes.

## Measurement caveats

- Testbench benches ran on the addon's own vendor (Livewire 4.2.1, Statamic
  6.7.1, Laravel 12.55). Browser demos ran on the demo app (Livewire 4.3.1,
  Statamic 6.21, Laravel 13.16). Both use the Stache driver.
- Not measured: the Eloquent driver (not installed anywhere; every Eloquent path
  in these plans falls back to current behaviour), Livewire 3 (allowed by
  `marcorieser/statamic-livewire ^5`), multi-site in the browser.
- Bench tests under `reference/NNN/bench/` are meant to be copied into
  `tests/Bench/` of a scratch copy and run by file path; they are not part of the
  suite. A bare `vendor/bin/phpunit` sweeps `tests/Bench` in if it exists, so
  delete it before taking the suite count.

## Checked and not a problem

Measured and rejected as findings, so nobody re-checks them: one Statamic query
per render (no double fetch or count); Stache `paginate` slices keys before
hydrating; the addon's own per-request plumbing (`generateParams`,
`BlockedQueryParams::strip`, `Parameters::make`, hooks, the `collect()` passes in
`HandleParams`) totals under 0.1 ms; middleware, nocache and dehydrate listeners
cost microseconds with the URL features off; `RangeBoundsQuery` encryption is
25 µs per interaction (only its size matters, see 009); `rules()`/`Rule::in` with
1,000 options adds ~3 ms per update; Blink keys are well-formed and hit across
filters within a request; `initiateField` runs only at mount; the fan-out is one
pooled POST, not N; browser-only events cost no requests; snapshot size at demo
scale (≤12 options) is ~7.7 KB per page.
