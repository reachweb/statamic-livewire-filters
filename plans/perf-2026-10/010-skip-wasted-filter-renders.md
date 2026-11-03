# Plan 010: Skip renders nobody uses: wire:ignore-rooted views, disabled-feature listeners, unchanged counts/bounds, foreign clear-option

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving to the
> next step. If anything in the "STOP conditions" section occurs, stop and
> report — do not improvise. When done, update the status row for this plan
> in `plans/perf-2026-10/README.md`.
>
> **Drift check (run first)**: `git diff --stat 8096a0b..HEAD -- src/Http/Livewire/LfCheckboxFilter.php src/Http/Livewire/LfDateFilter.php src/Http/Livewire/LfDualRangeFilter.php src/Http/Livewire/LfRadioFilter.php src/Http/Livewire/LfRangeFilter.php src/Http/Livewire/LfTextFilter.php src/Http/Livewire/LfToggleFilter.php src/Http/Livewire/Traits/HandleEntriesCount.php src/Http/Livewire/Traits/HandleFieldBounds.php src/Http/Livewire/Traits/IsLivewireFilter.php tests/Feature/HandleEntriesCountTest.php resources/views/livewire/filters/lf-checkbox.blade.php resources/views/livewire/filters/lf-radio.blade.php`
> This plan was written on the uncommitted `feature/dynamic-range-bounds` working tree
> (file list in `plans/perf-2026-10/reference/working-tree-status-at-review.txt`), so
> `LfDualRangeFilter`, `LfRangeFilter`, `HandleFieldBounds` and `IsLivewireFilter` will show
> as changed once that work is committed. That is expected. Compare the "Current state"
> excerpts against the live code before proceeding; on a mismatch, treat it as a STOP condition.

## Status

- **Priority**: P1
- **Effort**: M
- **Risk**: LOW-MED
- **Depends on**: none. Recommended first among the plans that edit the `Lf*` filter components (see the folder README); later `Lf*` plans rebase on this one.
- **Category**: perf
- **Planned at**: commit `8096a0b` + uncommitted dynamic-range-bounds work, 2026-10-21
- **Implements / refines**: PERF-07 in `plans/README.md` (verifier V3 findings R1 "listeners fire for a disabled feature", R2 "html the browser discards or does not need", D#4 "foreign clear-option renders every filter"; review report finding #3). Tier 1 below does **not** include the "skip unchanged plain views" variant PERF-07 warns about; a gated form of it is the optional tier 2 appendix.

## Why this matters

Every filter is its own Livewire component, and every `params-updated`, `clear-option` and `clear-all-filters` event the collection dispatches is POSTed to all of them in one pooled request. Each handler ends in a full Blade render that rides back in `effects.html`, even when the browser throws it away or nothing in it changed. Three cases waste bytes: (R2) the advanced checkbox/select views put a bare `wire:ignore` on their root, and Livewire's morph then skips the whole subtree (`vendor/livewire/livewire/dist/livewire.js:13701` `if (el.__livewire_ignore === true) return skip()`), so an 8.6 KB render of a 40-term dropdown is discarded on every event; (R1) `updateCounts`/`updateBounds` return early when their feature is off, but still render, so with counts on and bounds off the range and dual-range filters re-render 4.4 KB per event for nothing, and when counts come back identical the checkbox/radio/select views are re-rendered unchanged; (D#4) `clearOption` on a tag of **another** field is a no-op for 8 of 9 filter types yet renders all of them. The snapshot (`$wire` data, `wire:model` inputs) is merged **before** the morph (`livewire.js:5483` `mergeNewSnapshot` precedes `:5491` `invokeOnMorph`) and a missing `html` effect only skips the morph (`livewire.js:13755-13756`), so `skipRender()` leaves data and dispatched events intact. Rendering time itself is noise (about 4 ms for 10 filters); the win is bytes on the wire and client morph work.

Measured on the demo app (headless browser, Livewire 4.3.1 / Statamic 6.21, Stache, 40 `cars` entries, `php artisan serve`, raw bytes, no gzip, counts and bounds **on** unless stated). `/advanced` has 8 filters + lf-count + collection.

| Interaction (`/advanced`) | POST | Before | Tier 1 |
|---|---|---|---|
| Page load (initial counts + bounds) | fan-out | 30,784 B down | 21,861 B |
| Tick plain checkbox | fan-out, 8 components | 31,348 down / 12,092 up | 17,058 down / 12,266 up (−46 %) |
| Pick a brand in the advanced checkbox | own `$commit` | 10,570 B (8,646 B html discarded) | 1,498 B |
| Pick a brand in the advanced checkbox | fan-out | 31,358 B | 22,436 B |
| Tick plain checkbox, **counts on / bounds off** (R1) | fan-out | 8,447 up / 31,069 down (range 1,206 + dual 3,211 B html for a disabled feature) | 8,621 up / 12,143 down |

`/basic`, remove a tag chip (D#4, counts on): hop 2 (clear-option to 6 components) 69,383 → 64,523 B (text/radio/select/date skipped); hop 3 (params-updated + tags-updated) 17,066 → 8,213 B.

Testbench bench (`reference/010/bench/`, addon vendor: Livewire 4.2.1 / Statamic 6.7.1, Stache, 40 entries, 500 taxonomy terms; html bytes rendered back per interaction, counts + bounds on):

| Component, interaction | Before | Tier 1 |
|---|---|---|
| advanced checkbox (500 terms): params-updated / set selected / clear-all | 41,659 / 43,159 / 43,159 | 0 / 0 / 0 |
| mixed config fan-out, 7 listening filters, counts=1 bounds=0 | 54,505 | 8,435 |
| same, counts=0 bounds=1 | 54,835 | 4,411 |
| same, both off | 54,835 | 0 |
| foreign `clear-option`, 9 filter types | 57,756 | 0 |
| plain 8-option checkbox: set selected (unchanged in tier 1) | 5,583 | 5,583 |

Cost: the new `clientRendered` prop adds 23 B to every filter snapshot (e.g. plain checkbox 905 → 928 B).

## Current state

**No render gating in the filter trait.** `src/Http/Livewire/Traits/IsLivewireFilter.php` declares the locked identity props (`:12-28`), `mountIsLivewireFilter()` (`:30-38`) and `clearFilters()` (`:90-99`). It has no `hydrate*`/`rendered*` hooks and no request-scoped state flag.

**Counts listener renders on every path.** `src/Http/Livewire/Traits/HandleEntriesCount.php:37-51`:
```php
    #[On('params-updated')]
    public function updateCounts($params)
    {
        if (! config('statamic-livewire-filters.enable_filter_values_count')) {
            return;                                   // :40-42 — returns, still renders
        }
        ...
        $this->updateCountsWithBatchQuery($baseParams, $fieldHandle);   // :48
        $this->dispatch('counts-updated', $this->counts());             // :50
    }
```

**Bounds listener likewise.** `src/Http/Livewire/Traits/HandleFieldBounds.php:49-67` has three early returns (`:52-54` feature off, `:60-62` query not authenticated, `:65-67` stale revision), none of which skips the render; `:127-144` writes `dynamicMin`/`dynamicMax`/`boundsDisabled` and dispatches `range-bounds-updated`.

**Foreign clear-option renders.** `src/Http/Livewire/LfCheckboxFilter.php:67-76`:
```php
    #[On('clear-option')]
    public function clearOption($tag)
    {
        if ($tag['field'] === $this->field) {
            if (in_array($tag['value'], $this->selected)) { ... $this->updatedSelected(); }
        }                                             // another field's tag: no-op, full render
    }
```
`updatedSelected()` (`:38-51`), `clear()` (`:53-58`) and `setPresetValues()` (`:78-84`) are the only mutation paths. Same shape in `LfDateFilter:45-51`, `LfRadioFilter:63-69` (inherited by `LfSelectFilter`), `LfTextFilter:36-42`, `LfToggleFilter:42-49`, `LfRangeFilter:75-84`, `LfDualRangeFilter:116-136`. `public $view` is an **unlocked** public prop on every component except the dual range (`LfCheckboxFilter.php:15`).

**Client-rendered views.** `resources/views/livewire/filters/lf-checkbox-advanced.blade.php:1-57` is one `<div x-data="{...}" ... wire:model.live="selected" wire:ignore class="...">` root; the bare `wire:ignore` is at `:55`. `lf-select-advanced.blade.php:48` is the same. `lf-dual-range.blade.php:74` has `wire:ignore` only on an inner slider `<div>`, so that view is **not** client-rendered and must keep rendering when bounds change.

**The pattern to mirror.** `src/Http/Livewire/Traits/HandleParams.php:16` keeps a request-scoped `$collectionStateChangedThisRequest`; `markCollectionStateChanged()` (`:751-756`) sets it and resets the Livewire store's `skipRender`, `skipRenderIfCollectionStateUnchanged()` (`:758-763`) skips when nothing changed. Both are already used at `:196`, `:283`, `:605`.

**Livewire facts this plan relies on** (verified in the addon's vendor 4.2.1; the demo runs 4.3.1):
- Trait lifecycle hooks `hydrate<Trait>()` and `rendered<Trait>($view, $html)` are called by `vendor/livewire/livewire/src/Features/SupportLifecycleHooks/SupportLifecycleHooks.php:52` and `:150`.
- `Component::skipRender()` sets `store($this)->set('skipRender', true)` (`Component.php:79-86`); `forceRender()` (`:88-91`) only blocks *later* `skipRender()` calls, never undoes one, and does not exist in Livewire 3.6. Hence the store reset in `markFilterStateChanged()`.
- The listener list is emitted as an effect only when the component mounts (`SupportEvents.php:50`; 4.3.1 also after a lazy hydrate, `:57-63`). `__dispatch` of an event not in that list throws `EventHandlerDoesNotExist` (`SupportEvents.php:17-24`). This is why listeners must stay static (see "What verification refuted").

## The change

1. **`src/Http/Livewire/Traits/IsLivewireFilter.php`** — add `#[Locked] public bool $clientRendered = false;` and a request-scoped `protected bool $filterStateChangedThisRequest = false;`. Add `renderedIsLivewireFilter($view, $html)`: when the flag is not yet set, parse the **root start tag** of the rendered html once with `htmlRootHasWireIgnore()` (quoted attribute values such as `x-data="{...}"` are opaque; only an attribute whose exact name is `wire:ignore` counts; `wire:ignore.self`/`.children` do not) and set `clientRendered = true`. Add `hydrateIsLivewireFilter()`: if `clientRendered`, `skipRender()` for the whole request. Add `markFilterStateChanged()` (sets the flag and, unless client-rendered, `\Livewire\store($this)->set('skipRender', false)` so a real change clears a skip set earlier in the same pooled request) and `skipRenderIfFilterStateUnchanged()` (`skipRender()` unless the flag is set).
2. **`src/Http/Livewire/Traits/HandleEntriesCount.php`** — in `updateCounts()`, call `skipRenderIfFilterStateUnchanged()` on the feature-off early return. Snapshot `$this->statamic_field['counts']` before `updateCountsWithBatchQuery()`; afterwards `skipRenderIfFilterStateUnchanged()` if identical (`===`), else `markFilterStateChanged()`. Keep dispatching `counts-updated` in both cases (the Alpine views update from that browser event).
3. **`src/Http/Livewire/Traits/HandleFieldBounds.php`** — the same on all three early returns of `updateBounds()`. Add `boundsViewState(): array` returning `[dynamicMin, dynamicMax, boundsDisabled, selectionEventPayload()]` (everything the range views read server-side; revision/extrema bookkeeping is not rendered), capture it before the computation and compare after; still dispatch `range-bounds-updated`.
4. **The seven components** `LfCheckboxFilter`, `LfDateFilter`, `LfDualRangeFilter`, `LfRadioFilter` (covers `LfSelectFilter`), `LfRangeFilter`, `LfTextFilter`, `LfToggleFilter` — call `markFilterStateChanged()` in every handler that mutates the selection (`updatedSelected`/`updatedSelectedMin`/`updatedSelectedMax`, `clear`, the owning branch of `clearOption`, `setPresetValues` where it writes — dual range and toggle mark unconditionally because they always dispatch/compute). In `clearOption`, make the owning branch `return` and end the method with `skipRenderIfFilterStateUnchanged()` for every other field's tag. No event payload, param key or dispatch target changes.
5. **`tests/Feature/HandleEntriesCountTest.php`** — two `assertViewHas('statamic_field', ...)` assertions become `assertSet('statamic_field.counts', ...)` (`:173-175` feature-off path, now also asserting no `html` effect; `:225-227` repeated identical counts after a second `params-updated`), because a no-op no longer renders a view to assert on. The values asserted are unchanged.

Reference implementation: `plans/perf-2026-10/reference/010/tier1-skip-wasted-renders.diff` (apply from the addon root with `git apply` or `patch -p1`; it was generated against the working tree at review time; 991 lines including the new tests and fixtures below).

Rejected alternatives (details in "What verification refuted"): keying the skip on the view *name* (unsafe, `view` is public and views get published/renamed) and making `getListeners()` depend on config (500s on already-rendered pages).

## Tests to add

Copies with final class names are in `plans/perf-2026-10/reference/010/tests/` (namespace `Reach\StatamicLivewireFilters\Tests\Feature`, class == file name).

**`tests/Feature/RenderWasteRegressionTest.php`** (17 tests, counts + bounds on, `cars` blueprint with checkbox/select/radio/integer/date fields, 3 entries). Each asserts on the presence or absence of the `html` key in `$component->effects`:
- `params_updated_is_ignored_without_a_render_when_counts_are_disabled` / `..._when_bounds_are_disabled` — feature off: listener still registered in `effects['listeners']`, no `counts-updated`/`range-bounds-updated`, no html.
- `a_page_rendered_while_a_feature_was_enabled_keeps_working_after_it_is_disabled` — a component mounted with counts on keeps answering `params-updated` after the config flips off (no exception, no html, old counts kept).
- `a_bare_wire_ignore_root_marks_the_filter_as_client_rendered` — true for `lf-checkbox-advanced` and `lf-select-advanced`, false for the eight plain views.
- `wire_ignore_self_children_or_a_quoted_wire_ignore_do_not_count_as_client_rendered` — fixtures with `wire:ignore.self`, `wire:ignore.children` and `'wire:ignore'` inside `x-data`/a quoted attribute still render on selection; a custom view with a bare root `wire:ignore` is detected.
- `a_client_rendered_filter_skips_the_html_on_every_path_but_keeps_data_and_events_in_sync` — counts update, selection, own clear-option, clear-all, preset-params, `$refresh`: no html, but `selected`/counts are set, events dispatched, and the dehydrated snapshot carries the new `selected`.
- `a_client_rendered_filter_still_reports_validation_errors` — the error bag fills (such views cannot display it; pre-existing).
- `a_published_view_that_reads_selected_server_side_renders_on_every_path` — fixture `render-waste-published/livewire/filters/lf-checkbox.blade.php` prepended to the namespace: selection, own clear-option, preset-params, clear-all and `@error` all render.
- `unchanged_counts_skip_the_render_and_changed_counts_render`, `unchanged_bounds_skip_the_render_and_changed_bounds_render` — first update renders, identical result skips, changed result renders (bounds: same bounds from different params still skip).
- `clear_option_for_another_field_skips_the_render_for_every_filter_type` (9 types, no dispatch either) and `clear_option_for_the_owning_field_still_renders_and_clears` (checkbox, radio, range, dual).
- Pooled-request order tests via `->update(calls: [...])`: foreign clear-option **then** real counts update renders; real counts update **then** foreign clear-option renders; `updates: ['selected' => ...]` travelling with a foreign clear-option renders; only no-ops skip; `params-updated` + `clear-all-filters` renders once with the cleared selection.

**`tests/Feature/LazyClientRenderedFilterTest.php`** (1 test) — a `lazy` advanced checkbox: the placeholder is not detected (`clientRendered` false), the `__lazyLoad` render is sent and sets the flag, the next `params-updated` is skipped.

**Fixtures** — `tests/__fixtures__/views/render-waste/livewire/filters/` (`lf-checkbox-custom-ignore`, `lf-checkbox-custom-plain`, `lf-checkbox-ignore-children`, `lf-checkbox-ignore-self`, `lf-checkbox-quoted-ignore`) and `tests/__fixtures__/views/render-waste-published/livewire/filters/lf-checkbox.blade.php`.

**Existing test changed**: `HandleEntriesCountTest` (step 5 above). Legitimate because the two assertions inspected the rendered view's data on paths that, by design, no longer render; the same data is now asserted on the component.

## Verification

| Purpose | Command | Expected |
|---|---|---|
| Regression cluster | `vendor/bin/phpunit --filter RenderWasteRegressionTest` | `OK (17 tests)` |
| Lazy detection | `vendor/bin/phpunit --filter LazyClientRenderedFilterTest` | `OK (1 test)` |
| Changed existing test | `vendor/bin/phpunit --filter HandleEntriesCountTest` | `OK (9 tests)` |
| Full suite | `vendor/bin/phpunit` | `OK (424 tests, 1401 assertions)` — baseline is 406 |
| Format | `vendor/bin/pint --dirty --format agent` | `{"result":"pass"}` |

Bench (byte columns are deterministic; the `server ms` column is noise): before applying the diff run
`BENCH_TAG=verify-before vendor/bin/phpunit plans/perf-2026-10/reference/010/bench/RenderWasteBenchTest.php`
and after it `BENCH_TAG=verify-after ...` (same command). Each writes `render-waste-<tag>.md` next to the bench file; compare the html-B columns with `reference/010/bench/render-waste-before.md` and `render-waste-after-tier1.md` — they must match exactly (`skipped` where the reference says `skipped`, identical byte counts elsewhere). Delete the two generated files afterwards. The bench takes about 4 s.

Browser check (optional, describe in words): on the demo app `/advanced` with counts and bounds on, open the network tab and tick a plain checkbox: the third `POST /livewire/update` (the 8-component fan-out) is about 17 KB instead of 31 KB, and in its JSON the two `lf-checkbox-filter` components with the advanced view have no `effects.html`. Pick a brand in the advanced dropdown: its own `$commit` response is about 1.5 KB instead of 10.6 KB, and the selected labels still update. On `/basic` remove a tag chip: the second POST shows no `effects.html` for the text, radio, select and date components, while the checkbox that owned the chip still renders.

## STOP conditions

- `a_bare_wire_ignore_root_marks_the_filter_as_client_rendered` fails with `clientRendered` false for `lf-checkbox-advanced`: either the trait hooks are not being invoked (name must be exactly `renderedIsLivewireFilter`) or the stock view no longer has a bare `wire:ignore` on its root (`lf-checkbox-advanced.blade.php:55`). Re-measure before continuing.
- Any pooled-order test fails: the installed Livewire no longer lets `store($this)->set('skipRender', false)` undo an earlier `skipRender()`. Do not substitute `forceRender()`.
- Any existing test other than the two assertions in `HandleEntriesCountTest` fails after the diff — report which view/assertion depended on a no-op render.
- The full suite count is not 424 before you add anything of your own.
- `git status` shows changes outside the files listed in the drift check plus the new test files and fixtures, or `composer.lock` changed.

## What verification refuted

Do **not** implement these, even if they look simpler:

- **Reviewer B: key the skip on the view name** (`str_ends_with($this->view, '-advanced')`). `view` is an unlocked public prop and views are published and renamed; a renamed advanced view would start rendering again and a custom view that happens to end in `-advanced` would silently stop. The adopted design detects the actual rendered root once and caches the result in a `#[Locked]` prop, so published copies of the advanced views keep working and custom views with a bare `wire:ignore` root are picked up too.
- **Reviewer D: config-dependent `getListeners()`** (`addon-r1dyn`, `reference/010/bench/render-waste-rejected-dynamic-listeners.md`). It saves only ~2.2 KB of upload per fan-out over tier 1 (6,358 vs 8,621 B up) because it drops the components from the POST, but the listener list is baked into the page at mount. After a config flip, every already-open or statically cached page keeps dispatching `params-updated` to a component that no longer lists it: `EventHandlerDoesNotExist`, HTTP 500 with a 1.4 MB error body on every interaction, all counts and bounds lost. In the suite, 2 existing + 2 new tests error. Tier 1 keeps the listener and makes the handler a free no-op instead (`a_page_rendered_while_a_feature_was_enabled_keeps_working_after_it_is_disabled`).
- **PERF-07's "skip unchanged plain views" variant, ungated.** Skipping the selection render for every plain view broke 2 HTML-count tests (published/custom views read `$selected` server-side) for ~1 KB saved at 8 options. The optional tier 2 below keeps only a form gated to the addon's own stock files, where it is worth 310 KB per click at 500 options.
- **Skipping the render whenever `counts-updated`/`range-bounds-updated` is dispatched** (view-name agnostic but blind): plain views print the counts server-side (`lf-checkbox.blade.php:17-19`), so they must render when the counts array changes; hence the before/after comparison rather than a blanket skip.

## Compatibility & docs

- **Published views.** Detection reads the rendered html, not the file, so a published copy of `lf-checkbox-advanced`/`lf-select-advanced` is still client-rendered, and a published plain view that reads `$selected` or `@error` server-side still renders on every mutation path (tested). A custom view with a bare `wire:ignore` root stops receiving html after its first render; it must drive its UI from `$wire`/browser events, which is what `wire:ignore` already implies.
- **Config flags.** `enable_filter_values_count` / `enable_dynamic_range_bounds` off: listeners stay registered (no 500 on pages rendered under another config), handlers return without a render. `validate_filter_values`: validation still runs and fills the error bag; client-rendered views cannot show it (pre-existing).
- **Livewire 3 vs 4.** Only `skipRender()`, `\Livewire\store()`, trait lifecycle hooks and `#[Locked]` are used; all exist in 3.6.4 and 4.x. `forceRender()` is deliberately not used. Lazy components (4.x): verified by `LazyClientRenderedFilterTest`.
- **Stache vs Eloquent.** Nothing in the query path changes; the counts comparison is on the resulting array, so the Eloquent driver follows the same rule. Untested there; falls back to rendering whenever the arrays differ.
- **Multi-site.** No site logic touched.
- **Snapshot.** +23 B per filter component (`clientRendered`). Locked, so a tampered value fails the checksum.
- **Changelog line.** "Filters no longer send HTML back for renders the browser discards or does not need: views with a bare `wire:ignore` root (the advanced checkbox and select views), `params-updated` events when counts or bounds are disabled or unchanged, and `clear-option` events for another field's tag. Data, events and published views that read `$selected` server-side behave as before."
- **Docs** (sibling repo `/Volumes/1TB/Sites/livewirefilters-docs`, not edited here): add a short note to `installation.md` under "Publish the views" (a view whose root carries a bare `wire:ignore` is treated as client-rendered and gets no HTML after its first render; `wire:ignore.self`/`.children` are unaffected), one sentence under "Advanced ... Filter" in `usage/checkbox-filter.md` and `usage/select-filter.md`, and the changelog entry in `changelog.md`.

## Optional tier 2: skip the selection render on the addon's stock plain views

Apply **on top of tier 1** only if the maintainer wants it; it changes two shipped views.

**Mechanism.** On the stock `lf-checkbox`, `lf-radio` and `lf-select` views a selection only toggles native inputs the browser already updated, so the html is redundant; at 500 taxonomy terms the plain checkbox renders 310 KB per click. `IsLivewireFilter::viewIsAddonStock()` resolves `statamic-livewire-filters::livewire.filters.{$view}` through the view finder and returns true only when its `realpath` lies under the addon's own `resources/views/livewire/filters` directory, so a published copy (lives under the app) or any custom view name keeps rendering. `LfCheckboxFilter::updatedSelected()` and `LfRadioFilter::updatedSelected()` (inherited by `LfSelectFilter`) call `skipRender()` after dispatching when the view is stock; `clear()` and validation failures still render. Because the server no longer re-renders the `@unless ($selected == ...)` clear-button block on selection, `lf-checkbox.blade.php:24-28` and `lf-radio.blade.php:24-28` render the button always and toggle it with `x-data x-show="$wire.selected.length > 0" x-cloak` (radio/select: `$wire.selected !== ''`).

Reference implementation: `plans/perf-2026-10/reference/010/optional-tier2-plain-view-selection-skip.diff` (apply from the addon root after tier 1; generated against the tier-1 working tree).

**Tests**: `tests/Feature/PlainViewSelectionSkipTest.php` (6 tests, copy in `reference/010/tests/Feature/`): stock views send no html on selection but keep data and events; the clear button is always rendered and driven by `$wire.selected`; own clear-option on the stock checkbox skips; a published or custom view keeps rendering; a validation error still renders; a selection pooled with a counts change renders the new counts. `vendor/bin/phpunit` → `OK (430 tests, 1429 assertions)`; bench after → `reference/010/bench/render-waste-after-tier2.md`.

**Numbers** (same setups as above):

| Interaction | Before | Tier 1 | Tier 2 |
|---|---|---|---|
| bench: plain checkbox (500 terms) set selected, html B | 309,851 | 309,851 | 0 |
| bench: plain checkbox (8) / select / radio set selected | 5,583 / 1,000 / 1,913 | same | 0 / 0 / 0 |
| browser `/advanced`: plain checkbox own `$commit` | 6,692 B | 6,717 B | 1,349 B |
| browser `/basic`: tag chip hop 2 | 69,383 B | 64,523 B | 55,595 B |
| bench: counts fan-out html, plain checkbox / radio (button always rendered) | 5,666 / 1,538 | 5,666 / 1,538 | 6,180 / 2,048 |
| browser `/basic`: page-load POST | 16,131 B | 16,206 B | 17,324 B |

The last two rows are the cost: about 0.5 KB per plain checkbox/radio render and 1.1 KB per `/basic` page load, which is why tier 2 only pays off when plain views carry many options. The `x-cloak` attribute needs the usual `[x-cloak] { display: none !important; }` rule in the site's CSS; the published views are the place to adapt the markup if a site styles the clear button differently.
