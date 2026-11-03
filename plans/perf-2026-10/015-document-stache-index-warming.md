# Plan 015: Document Stache index declarations for filtered and sorted fields

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving to the
> next step. If anything in the "STOP conditions" section occurs, stop and
> report — do not improvise. When done, update the status row for this plan
> in `plans/perf-2026-10/README.md`.
>
> **Drift check (run first)**: `git diff --stat 8096a0b..HEAD -- README.md src/Console/Commands/UpdateLivewireFilters.php`
> This plan was written on the uncommitted `feature/dynamic-range-bounds` working tree
> (file list in `plans/perf-2026-10/reference/working-tree-status-at-review.txt`). If
> `README.md` gained a performance section in the meantime, merge the text below into it
> instead of adding a second one; if the "Current state" excerpts no longer match, STOP.

## Status

- **Priority**: P3
- **Effort**: S
- **Risk**: LOW
- **Depends on**: none
- **Category**: docs
- **Planned at**: commit `8096a0b` + uncommitted dynamic-range-bounds work, 2026-10-21
- **Implements / refines**: perf review 2026-10 finding #8 (verifier V5, finding A4). Not one of PERF-01…PERF-08 in `plans/README.md`. It sharpens the existing advice in the public docs (`advanced/tips-performance.md:66`, "Set up Stache indexes and warm the Stache beforehand"), which does not say *why* a warm alone is not enough. No code change is required.

## Why this matters

Every `where`/`orderBy` the addon issues on the Stache resolves against a per-field value index (`Statamic\Stache\Stores\Store::index()`, `vendor/statamic/cms/src/Stache/Stores/Store.php:51`). An index that is not in the cache is built on first use by loading **every item of the collection** (the "cold" rows below). Which indexes exist is the union of the defaults, the ones configured under `statamic.stache.stores.entries.indexes`, and a persisted *usage list* of indexes that were built on demand (`Store::indexes()`, `:135-149`). `php please stache:warm` rebuilds exactly that union (`Store::warm()`, `:405-413`). The catch: `Store::clear()` (`:384-397`) also forgets the usage list (`$this->usedIndexes = collect(); Stache::cacheStore()->forget($this->indexUsageCacheKey());` at `:395-396`), so after `stache:clear` + `stache:warm`, or `stache:refresh` (clear then warm, `Stache::refresh()`), only the default and the *configured* indexes exist again. The first visitor who filters or sorts on an undeclared field pays the full-collection hydration, once per undeclared field, on every deploy that clears the cache.

Numbers (Stache driver, no addon config flags involved):

| Where | N | Cold (index built on demand) | Warm | Source |
|---|---|---|---|---|
| Testbench, addon vendor (Livewire 4.2.1 / Statamic 6.7.1), infinite-scroll mount of a 3,000-entry collection; the on-demand build is the `redirect` index | 3,000 | 440.7 ms (`item gets 3021`) | 44.4 ms (`item gets 21`) | `reference/014/bench/before-grow-N3000.txt` |
| Same, first filter by `brand` after the warm pass (builds `entries::products::brand`) | 3,000 | 370.0 ms (`item gets 3022`) | 16.2 ms | same file |
| Demo app (Livewire 4.3.1 / Statamic 6.21), `php please` + tinker, first `where('price', …)` on the `cars` collection | 40 | 20.6 ms | 0.2 ms | `reference/015/a4-stache-warm-indexes.txt` |

The demo evidence file also shows the state machine: `price` is not declared in the demo's `config/statamic/stache.php`; after `stache:clear` + `stache:warm` the key `stache::indexes::entries::cars::price` is absent; one request builds it and adds `price` to the usage list; a later `stache:warm` **without** clear keeps it; `stache:clear` + `stache:warm` loses it again; declaring `price` under `stores.entries.indexes` makes clear + warm produce it.

## Current state

- `README.md` headings (`:5` Introduction, `:19` Documentation & Examples, `:23` Feature list, `:38` Infinite scroll, `:61` License, `:66` Issues and pull requests): there is no performance or deployment section.
- Public docs `advanced/tips-performance.md:59-67`, section "Improving Performance", bullet at `:66`: "Set up [Stache indexes](https://statamic.dev/stache#indexes) and warm the Stache beforehand, don't let your users wait for it to rebuild in real-time." Nothing about the usage list being dropped by `stache:clear`, and no config example.
- `src/Console/Commands/UpdateLivewireFilters.php:17-20` — `handle()` only calls `updateConfigForCustomQueryString()`; it runs from the `post-autoload-dump` Composer script (`composer.json`), i.e. on every `composer install/update`.
- The demo app already declares its filter fields (`/Volumes/1TB/Sites/livewirefilters-demos/config/statamic/stache.php:44-54`: `title`, `car_brand`, `transmission`, `fuel_type`, `date_of_registration`, `special_categories`, `air_condition`, `seats`, `doors`) but not `price` (present in the `cars` blueprint, used by no demo page), which is why the verifier used that field for the demonstration.
- Statamic default `config/stache.php` ships `'indexes' => []` per store.

## Commands you will need

| Purpose | Command | Expected on success |
|---|---|---|
| Suite unchanged | `vendor/bin/phpunit` | `OK (406 tests, …)` on the review-time tree |
| Statamic behaviour still as documented | `grep -n "indexUsageCacheKey" vendor/statamic/cms/src/Stache/Stores/Store.php` | a `forget(...)` line inside `clear()` (`:396` today) |
| Demo reproduction | see Verification step 3 | `false` after clear + warm, `true` once declared |

## Scope

**In scope**:
- `README.md` (new "Performance notes" section)
- Text handed to the maintainer for `advanced/tips-performance.md` in the docs repo
- Optionally `src/Console/Commands/UpdateLivewireFilters.php` (one guarded line; recommended against)

**Out of scope** (do NOT touch):
- Any automatic index declaration from the addon (e.g. a service provider merging filter fields into `statamic.stache.stores.entries.indexes`): the addon cannot know the fields at boot without parsing templates, and a wrong index list silently costs memory on every warm. Record as a direction idea if the maintainer wants it; not this plan.
- `config/config.php`: no new option.
- The demo app's `config/statamic/stache.php`: only touched temporarily during Verification step 3 and reverted.

## The change

No source change. Three documentation edits; the third is optional.

1. **`README.md`** — add a `## Performance notes` section between "Infinite scroll" (`:38-59`) and "License" (`:61`) with this text (adapt the field names to the README's `cars` example):

   > ### Declare Stache indexes for the fields you filter and sort by
   >
   > On the Stache driver every field a filter or sort touches needs a value index. Statamic builds a missing index on first use by loading every entry of the collection, and remembers it as "used" so `php please stache:warm` rebuilds it next time. `php please stache:clear` (and `stache:refresh`) also forgets that list, so after a deploy that clears the cache only the indexes you **declare** are warmed; the first visitor to filter or sort on an undeclared field pays the full rebuild (hundreds of milliseconds at a few thousand entries).
   >
   > Declare them in `config/statamic/stache.php`, under the collection's store, and warm the Stache as part of your deploy:
   >
   > ```php
   > 'stores' => [
   >     'entries' => [
   >         // ...
   >         'indexes' => ['title', 'car_brand', 'transmission', 'fuel_type', 'price', 'date_of_registration'],
   >     ],
   > ],
   > ```
   >
   > List every field used by an `lf-*` filter (including the field behind a `query_scope` such as `multiselect`), every `lf-sort` field, and any `sort` set on the collection tag. Taxonomy filters use the taxonomy field's index on the entries store. Then run `php please stache:warm` after `stache:clear`/`stache:refresh`.

2. **Public docs** (sibling repo `/Volumes/1TB/Sites/livewirefilters-docs`, read-only here; hand the text to the maintainer): expand the bullet at `advanced/tips-performance.md:66` into a short subsection "Declare Stache indexes" with the same paragraph and config snippet, keeping the link to https://statamic.dev/stache#indexes. Optionally cross-link it from `usage/livewire-collection-tag.md` next to the `sort` parameter.

3. **Optional — `UpdateLivewireFilters` command** (`src/Console/Commands/UpdateLivewireFilters.php`): a one-line `$this->line('Tip: declare the fields you filter and sort by under statamic.stache.stores.entries.indexes so stache:warm builds them (see README → Performance notes).')` at the end of `handle()`. Recommendation: **do not** add it. The command runs on every `composer install`/`update` via `post-autoload-dump`, so the tip would print on every dependency change for every install, and it cannot know which fields the site filters on. If the maintainer wants it anyway, keep it to one line and guard it with `if ($this->output->isVerbose())`.

Reference implementation: none (documentation only). The evidence file is `plans/perf-2026-10/reference/015/a4-stache-warm-indexes.txt`.

## Tests to add

None; nothing executable changes. If step 3 is taken, `TESTS-02` in `plans/README.md` (the command has no coverage) still applies and a test for the new line is not required by this plan.

## Verification

1. `vendor/bin/phpunit` → `OK (406 tests, 1268 assertions)` unchanged (plus whatever other landed plans added).
2. `vendor/bin/pint --dirty --format agent` → nothing to fix unless step 3 was taken.
3. Reproduce the mechanism on the demo app (which symlinks this addon; all commands from `/Volumes/1TB/Sites/livewirefilters-demos`):
   - `php please stache:clear --no-interaction && php please stache:warm --no-interaction`
   - `php artisan tinker --no-interaction --execute 'var_export(\Statamic\Facades\Stache::cacheStore()->get("stache::indexes::entries::cars::price") !== null);'` → `false` (`price` is not declared).
   - `php artisan tinker --no-interaction --execute '$t=hrtime(true); echo \Statamic\Facades\Entry::query()->where("collection","cars")->where("price",">",0)->count()," entries, ",round((hrtime(true)-$t)/1e6,1)," ms\n";'` → roughly 15–25 ms the first time, under 1 ms when run again.
   - Repeat the first two commands → still `false` after clear + warm, because the usage list was dropped.
   - Temporarily add `'price'` to `config/statamic/stache.php` → `stores.entries.indexes`, repeat the first two commands → `true`. **Revert the demo config afterwards** and run `php please stache:warm` once more.
4. Read the README section back for Markdown rendering (code fence inside a blockquote is not needed in the README itself; use a plain fence).

## Done criteria

ALL must hold:

- [ ] `README.md` has the "Performance notes" section with the mechanism paragraph, the config snippet and the "declare every filtered and sorted field" rule, placed before "License".
- [ ] The docs text (same content, with the statamic.dev link) was handed to the maintainer with the target file and line (`advanced/tips-performance.md:66`).
- [ ] Verification step 3 was run on the demo app and showed `false` → `true` as described; the demo config was reverted and the Stache re-warmed.
- [ ] `vendor/bin/phpunit` still exits 0 with the baseline count; `git status` shows only `README.md` (and the command file if step 3 was taken).
- [ ] Changelog line added; `plans/perf-2026-10/README.md` status row updated.

## STOP conditions

- `grep -n "indexUsageCacheKey" vendor/statamic/cms/src/Stache/Stores/Store.php` no longer shows a `forget(...)` inside `clear()`: Statamic changed the behaviour, the advice would be wrong. Report instead of documenting it.
- The demo reproduction in Verification step 3 returns `true` right after clear + warm without `price` declared: same reason.
- `README.md` already contains a performance section with conflicting advice (merge, do not duplicate; if the advice conflicts, report).

## What verification refuted

Nothing. Two precisions the verifier established that the text must keep:

- A plain `php please stache:warm` **without** a preceding clear does rebuild the indexes that were used on demand, because the usage list persists in the cache store. The problem is specifically clear/refresh (and any deploy that starts from an empty cache store, e.g. a new Redis instance or a container rebuild).
- The demo site is not a bad example: it already declares nine filter fields; only `price` was missing, which is why that field was used for the demonstration.

## Compatibility & docs

- **Published views / config flags / Livewire version**: none involved.
- **Multi-site**: the index cache key carries no site segment (`stache::indexes::entries::<collection>::<field>`), so one declaration per field covers every site of that collection.
- **Stache vs Eloquent**: the advice applies to the Stache driver only. On the Eloquent driver the equivalent is a database index on the queried JSON path or column; say so in one sentence in the README section so Eloquent users do not look for a Stache setting.
- **Changelog line**: "Docs: explain why fields used by filters and sorts should be declared under `statamic.stache.stores.entries.indexes` (a `stache:clear` forgets on-demand indexes, so `stache:warm` alone will not rebuild them)."
- **Public docs page to edit**: `advanced/tips-performance.md` (section "Improving Performance", bullet at line 66), plus an optional cross-link from `usage/livewire-collection-tag.md`.

## Maintenance notes

- Which fields need an index: every `field` of an `lf-*` filter tag (the condition's `where` runs on it), the field behind a `query_scope` such as `multiselect`, every field offered by `lf-sort`, any `sort` on the collection tag, and the fields faceted counts pluck when `enable_filter_values_count` is on (same indexes). Taxonomy filters (`condition="taxonomy"`) use the taxonomy field's index on the entries store; dated collections already get `date` by default.
- `statamic.stache.indexes` (top-level) applies to every store; `stores.entries.indexes` is the usual place. Both are merged by `Store::indexes()`.
- A deploy recipe that avoids the cold hit entirely: `php please stache:clear && php please stache:warm` **after** the new config is in place, or `php please stache:refresh`; with the fields declared, either command leaves every filter/sort index built.
- To see whether a given index is built right now, read the Stache cache store directly: the key is `stache::indexes::entries::<collection>::<field>` (the demo evidence file reads `stache::indexes::entries::cars::price`); the persisted usage list lives under the store's `indexUsageCacheKey()`. Both are plain cache reads and safe to run on production.
- Declared indexes are built for **every** collection served by the entries store, so a field that exists in only one collection costs an empty index in the others; that is harmless.
- If Statamic ever stops forgetting the usage list in `clear()`, the section becomes "nice to have" rather than necessary; the STOP condition above catches that.
