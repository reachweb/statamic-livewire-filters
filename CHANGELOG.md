# Changelog

All notable changes to this project are documented in this file. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

Full documentation: [livewirefilters.com](https://livewirefilters.com).

## [Unreleased]

## [4.6.3] - 2026-11-04

### Changed

- Filters and `lf-sort` now read the URL's filter params when they mount, in both the custom query string and the Livewire query string modes, so their state matches the URL from the first render. Before, every control rendered empty and waited for a `preset-params` event in a follow-up request, so the page briefly showed unchecked controls, and `{{ nocache }}` regions returned empty controls.
- The `lf-checkbox`, `lf-radio`, `lf-select`, `lf-toggle`, `lf-text`, `lf-date`, `lf-range` and `lf-sort` views show the selection in the server HTML: checkboxes and radios are `checked`, select options `selected`, and text, date and range inputs carry their `value`. The Alpine-driven `lf-checkbox-advanced`, `lf-select-advanced` and `lf-dual-range` views show it as soon as Livewire starts, without waiting for another request.
- The collection now sends `preset-params` only when a filter param doesn't come from the URL, such as a preset set on the `livewire-collection` tag; it then carries every filter param, URL ones included. It only contains filter keys (`field:condition` style) and `sort`.

### Fixed

- Under full-measure static caching, `{{ livewire-filters:head }}` now waits for `DOMContentLoaded` before starting Livewire, like Livewire does on its own. Before, it could start Livewire and Alpine while the page was still loading, so `alpine:init` listeners placed after `{{ livewire:scripts }}`, such as ones that register `Alpine.store` or `Alpine.data`, sometimes ran too late. Expressions that used them then threw errors like `Cannot read properties of undefined`. On the demo site this happened on up to 1 in 6 page loads.
- With `custom_query_string` and full-measure static caching, filter changes sometimes left the browser URL unchanged, so a reload or shared link lost the filters or page. The URL handler waited for `livewire:initialized`, which had often already fired by the time it loaded. It now registers its listeners as soon as it loads, once per page.
- With `custom_query_string` and `{{ nocache }}` regions under full-measure static caching, opening or reloading a paginated URL such as `/cars/filters/brand/audi?page=2` now shows that page. Before, the `/!/nocache` request only restored the filter segments from the page URL, so the regions always rendered page 1. The page URL's query string now reaches the regions, as it already did with `enable_query_string`.

### Upgrading from 4.6.2

Sites that use the bundled views need no changes. Check the following:

- **Custom filter components.** A custom filter that relied on `preset-params` for a URL param no longer receives it, because the collection skips the event when every filter param comes from the URL. Read `CustomQueryString::urlParams()` at mount instead. Filters that use the `IsLivewireFilter` trait and define `setPresetValues()` get this automatically.
- **Custom listeners for keys without a colon.** `preset-params` now only carries filter keys (`field:condition` style) and `sort`. A listener that read another tag option from it, such as `limit`, no longer receives it.
- **Published filter views.** They keep working and skip the follow-up request, but they only show the selection once Livewire starts. Copy the new `@checked`, `@selected` and `value` attributes from the bundled views to get it in the server HTML.
- **Published `livewire/utility/url-handler.blade.php`.** Re-publish it or merge in the new script to get the full-caching URL fix.

## [4.6.2] - 2026-11-04

### Changed

- Faceted counts and dynamic range bounds now find the matching entries once per filter state per request and read each field straight from the Stache index, instead of running one sorted query per filter. On a 5,000-entry collection with 8 filters, the counts request after page load drops from about 1 s to 300 ms, and the requests after filter changes get about 25% faster overall (less when many filters are active). Counts and bounds are the same as before. The Eloquent driver is unchanged.
- The minimum supported Statamic 5 version is now 5.29.
- With `enable_filter_values_count` off, filter snapshots no longer carry a placeholder `counts` array, which made up nearly half of a large filter's request. `$this->counts` in views returns the same empty counts as before. A custom view that reads `$statamic_field['counts']` directly must switch to `$this->counts`.
- Filter option lists are no longer stored in the cache store on every page view, so `lw_computed` cache files stop piling up. On drivers other than the Stache, `LfTags` still caches its resolved options for the life of the component. Term and entry option labels are now read from the Stache indexes instead of loading every term and entry, which makes mounting a filter over a large taxonomy or collection several times faster.
- Filters no longer send HTML back for renders the browser discards or doesn't need: views with a bare `wire:ignore` on their root (the advanced checkbox and select views), `params-updated` events when counts or bounds are disabled or come back unchanged, and `clear-option` events for another field's tag. On the demo site this cuts the Livewire response bytes of a typical filtering session by 15–25% (gzipped), with every static caching strategy. Data, events, and published views that read `$selected` server-side behave as before. A custom view with a bare `wire:ignore` root gets no HTML after its first render; Livewire already discarded it, so such views keep updating from `$wire` or browser events as before.
- Filtering needs fewer Livewire requests. The bundled checkbox view sends its `filter-updated` event from the browser, in the same request as its own update, so ticking a checkbox takes one request fewer. The bundled `lf-count` view updates from the `entries-updated` browser event instead of a server request, and the collection no longer sends `tags-updated` on page load when there is no tag to show, so with counts and bounds off a page without preset filters makes no Livewire request on load. Clear all from the collection (`$wire.clearAll()`) no longer makes every filter send a `clear-filter` back; only a filter that manages a param set on the tag or by an `init` hook still does, or one whose selection changed while the clear was on its way, so that selection can't stay applied after the filter resets. On the demo site, a filtering session makes 19% fewer requests with counts and bounds on and 35% fewer with them off, with every static caching strategy. That saves about 150 ms per checkbox tick or clear all on a slow mobile connection.
- `validate_filter_values` still rejects invalid checkbox values and shows the error, but it no longer stops the bundled checkbox view from applying them to the collection, because the view sends the event from the browser. As before, `allowed_filters` and `blocked_query_params` control which params the collection accepts.
- `entries-updated` now also carries `entriesLabel`, the translated `ui.entries` text for the count. A published `count.blade.php` or a custom `view` on `lf-count` keeps the server listener and works as before. A published `lf-checkbox.blade.php` keeps working through the server relay, without the saved request. `clear-all-filters` from the collection now carries two arguments, the list of condition keys it kept and the params it removed; listeners without parameters are unaffected.

### Fixed

- Under full-measure static caching, `{{ livewire-filters:head }}` now starts Livewire even when it runs after Statamic has replaced the CSRF token. Before, if the tag came after a stylesheet, the browser could hold the inline script back until the CSS loaded. On slow networks Statamic's `statamic:csrf.replaced` event then fired first, `Livewire.start()` was never called, and filters didn't respond. Full-measure pages served without the CSRF script, such as excluded URLs, now start Livewire too. Placing the tag before your stylesheets is now recommended. On pages that never load Livewire, the script now stops checking for it once the page has loaded instead of checking forever.

### Upgrading from 4.6

Sites that use the bundled views, or published copies of them, need no changes. Check the following:

- **Statamic 5 before 5.29.** Composer keeps such sites on 4.6 until Statamic is updated, so nothing breaks; update Statamic to get this release.
- **Custom views that read `$statamic_field['counts']`.** None of the bundled views ever did. With `enable_filter_values_count` off the key is no longer set, so `$statamic_field['counts'][$value]` throws an "Undefined array key" error. Use `$this->counts[$value]` instead, which works with counts on or off.
- **Custom views with a bare `wire:ignore` on their root.** Livewire already skipped morphing such a root, so the HTML it no longer receives was being thrown away. Nothing changes in the browser.
- **Leftover cache entries.** `lw_computed.*` entries written by earlier versions are no longer read and expire on their own within an hour.
- **`validate_filter_values`.** With the bundled checkbox view, an invalid value still shows the validation error but now reaches the collection too. Don't rely on validation to keep values out of the query; a visitor could always send `filter-updated` directly.
- **Subclasses of `LfCheckboxFilter`.** A subclass that defines its own `payload()`, `updatedSelected()` or `dispatchFilterUpdated()` keeps the server relay and works as before, without the saved request.
- **Published `lf-checkbox.blade.php`.** It keeps working through the server relay. Merge in the new bundled markup, or re-publish it, to get the saved request.
- **Published `ui/count.blade.php`.** Any published copy, re-published ones included, keeps the server listener and works as before. Delete it to get the saved request.
- **Custom `clear-all-filters` listeners.** When the collection's "Clear all" sends the event, it now passes two arguments, the list of condition keys it kept and the params it removed. A listener that declares parameters receives them in that order.

## [4.6.1] - 2026-11-02

### Security

- Faceted counts now only use the encrypted copy of the filter state that the collection sends, the same one dynamic range bounds use. Before, a crafted `params-updated` event could put query controls such as `status:is any` into the count query and, combined with a condition like `title:contains`, reveal whether matching draft, future or other-site entries exist. A count update without a valid encrypted query for the filter's collection is now ignored.
- The `view` property of every filter component is now `#[Locked]`, so a tampered request can't switch a filter to another view. Before, only `LfDualRangeFilter` locked it. Setting `view` on the tag still works.

### Changed

- The `params-updated` event always carries its second argument, the encrypted query, whenever `enable_filter_values_count` or `enable_dynamic_range_bounds` is on. Custom code that dispatches `params-updated` to refresh counts must now pass this argument; events with only plain params no longer update counts.
- The filter components now extend a new abstract `LivewireFilter` class, which implements a `FilterContract` interface documenting the events a filter consumes and emits. The `IsLivewireFilter` trait is unchanged for custom filters built on `Livewire\Component`. A subclass of a bundled filter that already defines `payload()`, `emptyValue()`, `applyPreset()` or `dispatchFilterUpdated()` must now match the base class's signatures; those methods are what the base calls to build `filter-updated`, clear the selection and apply preset params. A subclass of `LfTextFilter` or `LfDateFilter` that defines `rules()` is now validated on update when `validate_filter_values` is on, as checkbox and radio filters already were.
- `LfRangeFilter` now sends its own `modifier` in `filter-updated` instead of `null`, matching the `clear-filter` event it already sent. Range conditions such as `gte` ignore the modifier, so existing filters behave the same.

### Fixed

- A filter with `condition="taxonomy"` on a field whose handle doesn't match a taxonomy handle now throws `TaxonomyFieldHandleMismatchException` at mount, naming the taxonomy to rename the field to. Statamic only filters terms fields whose handle matches the taxonomy handle, so such a filter previously crashed the collection with `Call to a member function collections() on null`.
- Pretty URLs in custom query-string mode with several `query_scope` aliases now apply every scope. Before, only the last aliased scope was applied, while the other filters still showed as selected. ([#93](https://github.com/reachweb/statamic-livewire-filters/pull/93))
- A date dual-range filter now clears its `is_after`/`is_before` params even before it has dispatched a value. Before, it cleared with the `any` modifier, which the collection read as `gte`/`lte` keys, so the date params stayed applied.
- Tags no longer disappear for a field whose handle starts with the name of an active query scope, such as `item_options` next to an `item` scope.
- On a collection tag with several collections (`from="a|b"`), faceted counts and dynamic range bounds now query the same collections: the tag's collections whose blueprint contains the field, falling back to the filter's own collection. Before, counts only covered the filter's collection, while bounds also picked up values from collections without the field. With `from="*"`, only collections whose blueprints contain the field are queried. ([#89](https://github.com/reachweb/statamic-livewire-filters/pull/89))
- Options of a terms field that spans several taxonomies are now keyed by `taxonomy::slug`, which is how Statamic stores them. Before, faceted counts never matched those values, and terms sharing a slug in different taxonomies overwrote each other. Single-taxonomy fields keep bare slugs, so their URLs don't change. ([#91](https://github.com/reachweb/statamic-livewire-filters/pull/91))
- With `enable_term_routes` on, localized term pages now filter by the term's default-locale slug. Before, the translated slug from the URL was used, so localized term pages showed no entries. ([#92](https://github.com/reachweb/statamic-livewire-filters/pull/92))
- Faceted counts no longer crash with `TypeError: Cannot access offset of type ...` when a counted field holds nested arrays or objects, such as Carbon dates on the Stache driver. Those values are now skipped; plain values inside a list are still counted.
- With `enable_dynamic_range_bounds` on, Clear all now resets range and dual-range filters to the full-collection bounds instead of the tag's absolute `min`/`max`. Before, the slider could end with a selection outside its displayed bounds, because no bounds update followed the reset.
- A dual-range filter on a date field no longer crashes with `A non-numeric value encountered` when the slider moves after its values were preset from the URL or tag. Preset `Y-m-d` dates are now converted to years, which is what the slider uses, and an unparseable preset falls back to the slider's `min`/`max`. Removing the tag of a preset date bound now also clears it.
- Removing a dual-range tag now resets only the bound that tag names, for numeric and date fields alike. Before, when both bounds held the same value (for example `cabins:gte=6` and `cabins:lte=6`), clicking ✕ on either tag reset both. Custom tag views should pass the tag's condition as the third argument of `removeOption`; without it, the first tag with a matching field and value is removed, as before.

## [4.6.0] - 2026-10-21

### Security

- Client-supplied filter state can no longer inject Statamic query controls into the entries query. Livewire keeps filter state in a public property, so a crafted request could inject Statamic query controls such as `status`, `from`, `site` or `limit` and surface draft entries, query another collection or inflate query cost. These keys are now stripped by default (see `blocked_query_params` below). ([#84](https://github.com/reachweb/statamic-livewire-filters/pull/84))
- Filter identity properties (`field`, `condition`, `modifier`, `collection`, `blueprint`, `statamic_field`) and the `collection`, `blueprint` and `fields` properties of `LfSort` are now `#[Locked]`, so a tampered request can't point a filter at another field or collection.
- `setParameters()` on `LivewireCollection` is now `protected`, which removes it from the actions a client can call.
- Filter state keys starting with `:` are rejected. Statamic evaluates a `:key` parameter as an Antlers expression, so a crafted request could have one evaluated inside the entries or count query.
- The faceted-count listener exits early when `enable_filter_values_count` is off, resolves the field handle from the blueprint instead of client state, and always queries the component's own collection.

### Added

- `blocked_query_params` config option. It ships populated with Statamic's query-control keys:
  - visibility: `status`, `published`, `show_future`, `show_past`, `since`, `until`, `redirect`, `redirects`, `links`
  - collection and site scope: `from`, `in`, `folder`, `use`, `collection`, `not_from`, `not_in`, `not_folder`, `dont_use`, `not_collection`, `site`, `locale`
  - query cost: `limit`, `offset`, `paginate`, `chunk`
  - scope alias: `filter`

  Keys are matched on the segment before the first colon, so `status` blocks both `status` and `status:is`, while filters like `transmission:is` pass through.
- Dynamic range bounds, off by default. Set `enable_dynamic_range_bounds` to `true` and the range and dual range sliders narrow to the values that still match the other active filters. A selection the visitor already made is never moved; the bounds widen to keep it reachable. When an unused slider has no room left to move, it is disabled. Bounds are computed from an encrypted copy of the filter state that the collection sends, so they can't be tampered with from the browser. Each range filter runs one extra query per update, so weigh it on large collections the same way as `enable_filter_values_count`.
- `range-bounds-updated` browser event for custom range views. Its payload has `min`, `max` and `disabled`, plus `selected` (range) or `selectedMin` and `selectedMax` (dual range).

### Changed

- Removing a filter from the tags component, or clicking "Clear all", now updates the collection in the same Livewire round trip instead of waiting for each filter component to relay the change back. The `clear-option` and `clear-all-filters` events still reach filter components with unchanged payloads. On "Clear all", parameters set on the tag or by an `init` hook are still only cleared by their own filter component, and Resrv's availability filter keeps its dates. ([#85](https://github.com/reachweb/statamic-livewire-filters/pull/85))
- A `filter-updated`, `clear-filter` or `clear-option` event that changes nothing (on page 1) no longer re-renders the collection. If you dispatched `filter-updated` to force a refresh, call Livewire's `$refresh` on the collection component instead.
- Filter state from the URL (`params[...]` in either query string mode) is sanitized on page load: blocked keys are stripped, and tag-only parameters (`from`, `in`, `folder`, `use`, `collection`, `view`, `lazy-placeholder`, `paginate`, `infinite_scroll`, `allowed_filters`) are never read from the URL.
- Parameters set on the `{{ livewire-collection }}` tag, and parameters an `init` hook adds or changes during mount, are trusted and kept even when their key is blocked.
- A key listed in the tag's `allowed_filters` is exempt from the blocklist, and a value sent by the client for that key overrides the tag's default.
- The `lf-range` and `lf-dual-range` views listen for `range-bounds-updated` to update the slider's bounds and value.
- The `params-updated` event carries the hardened parameters instead of the raw filter state, gains a second argument (the encrypted bounds query) and also fires when only dynamic bounds are on. Listeners that take one argument keep working.
- Faceted counts are computed from the same hardened parameters as the entries query, so the blocklist and `allowed_filters` apply to them too. The first `params-updated` event is now dispatched after `init` hooks run, so the initial counts include hook-authored parameters.

### Fixed

- Moving a range slider back to its default value now clears the filter instead of leaving a redundant parameter in the URL and the tags.
- A toggle filter restored from the URL or a preset now shows as on when its `preset_value` is a number or boolean.
- Removing a tag for a condition, taxonomy or query scope parameter that has no filter component on the page now removes the parameter. Previously the click did nothing.
- With `allowed_filters`, an active query scope is now allowed when its own `query_scope:<scope>` entry is listed. Previously the whole scope list had to match one entry, so two active scope filters dropped both scopes.

### Upgrading from 4.5

The blocklist is on by default, so most sites need no changes. Check the following:

- **Cached config.** Laravel skips merging package config defaults while the config is cached. Run `php artisan config:clear` or rebuild with `php artisan config:cache` after upgrading, or `blocked_query_params` stays unset and nothing is blocked.
- **Static caching.** Clear the static cache after upgrading (`php please static:clear`). Pages cached under 4.5.1 hold the old component state, so on the first filter change control parameters set on the tag (such as `limit` or `site`) would be stripped as if a visitor had sent them.
- **Published config.** If you published the config file, the new key is filled in from the package defaults automatically. If you copy `blocked_query_params` into your file, your list replaces the default entirely and you won't get keys added in later releases.
- **Filters on fields named like a control key.** A filter on a field whose handle is a blocked key (for example `status`, `site` or `from`) produces a param such as `status:is`, which is now stripped. Remove that key from `blocked_query_params` in your published config, or add the param to the tag's `allowed_filters`. `allowed_filters` is an allowlist, so the tag must then list every filter it accepts.
- **Control keys changed from the URL.** If visitors changed a control key such as `limit` through the URL (for example with legacy `paginate="true"` pagination), add it to `allowed_filters` along with your filter keys, for example `allowed_filters="transmission:is|limit"`.
- **Query scopes.** `query_scope` is not blocked, because the addon builds that key itself for scope filters. If your app registers sensitive or expensive scopes, list them explicitly with `query_scope:<scope>` entries in `allowed_filters`.
- **`setParameters()`.** It is now `protected`. Subclasses can still call it, but client-side calls (`$wire.setParameters(...)` or `wire:click="setParameters(...)"`) no longer work; set those parameters on the tag instead.
- **Overridden methods.** `filterUpdated()` and `clearFilter()` on `LivewireCollection`, and `clearFilters()` on the filter components, have a new optional `$boundsSelection` parameter. If you extend these classes and override any of them, add `$boundsSelection = null` to your method's parameters, or PHP throws a fatal "must be compatible" error.
- **Custom filter components.** "Clear all" now removes every filter parameter except those set on the tag or by an `init` hook, including those from custom components that don't listen for `clear-all-filters`. If such a component should reset its own controls too, listen for that event.
- **Published range views.** If you published `lf-range.blade.php` or `lf-dual-range.blade.php` and turn on `enable_dynamic_range_bounds`, merge in the new `range-bounds-updated` listener (or re-publish the views), otherwise the sliders won't follow the bounds. Nothing changes while the option is off.

## [4.5.1] - 2026-06-29

### Fixed

- Browser history behavior in the custom query string mode.

## [4.5.0] - 2026-06-19

### Added

- Infinite scroll and "Load more" pagination. Add `infinite_scroll="true"` to the `{{ livewire-collection }}` tag, then render the trigger with the new `{{ livewire-filters:load_more }}` tag. Use `auto="true"` to load on scroll, or omit it for a "Load more" button; either way it falls back to a real button without JavaScript. Each load grows the page by the initial `paginate` value and resets on filter or sort changes. Thanks to [@mikemartin](https://github.com/mikemartin) for starting this feature. You may need to update template overrides to use it.

### Fixed

- Duplicate browser history entries when paginating in the custom query string mode, and more reliable browser Back button behavior.
- Static caching on multisite installs: correct site resolution and subdirectory handling.

## [4.4.0] - 2026-06-14

### Added

- `{{ livewire-filters:head }}` tag. Add it to your layout before `{{ livewire:scripts }}` to prevent 419 "Page Expired" errors on the first filter interaction under full-measure static caching. It outputs nothing when caching is off.
- Filter state is rehydrated on `/!/nocache` requests in both the Livewire and the custom query string modes, so filters resolve correctly on a shared cache shell instead of serving stale filtered pages.

### Changed

- The query string modes are mutually exclusive: when `enable_query_string` is on, `custom_query_string` is ignored.

### Fixed

- Taxonomy terms with numeric slugs are no longer dropped from filter options and counts.

See the [static caching guide](https://livewirefilters.com/advanced/static-caching) for setup.

## [4.3.2] - 2026-03-24

### Fixed

- Counts for filters that use a custom options array.

## [4.3.1] - 2026-03-24

### Fixed

- Existing non-filter query parameters are preserved when rewriting custom filter URLs ([#69](https://github.com/reachweb/statamic-livewire-filters/issues/69)).
- Browser history is no longer pushed when the computed URL is unchanged.

## [4.3.0] - 2026-03-20

### Added

- Laravel 13 support.

## [4.2.0] - 2026-03-20

### Changed

- Filters with the same base parameters share a single count query instead of each running their own.
- On the Eloquent driver, counts pull only the needed field values from the database instead of hydrating full entries.
- Taxonomy terms, collection entries, dictionary options and blueprint lookups are cached within the request.

### Fixed

- Custom query string URL matching is segment-exact, so `/myfilters/` no longer matches a `filters` prefix.
- Incorrect counts when using scoped filters.
- Livewire requests are detected by the `X-Livewire` header instead of the `livewire/update` path, which fixes custom Livewire endpoints.
- On multisite, the current page path is resolved from the Referer header during Livewire requests.

### Upgrading

If you published `lf-checkbox-advanced.blade.php` or `lf-select-advanced.blade.php`:

- Change `counts: {},` to `counts: @js($this->counts),` so Alpine knows the available options from the start.
- You can simplify `x-show="counts && counts[value] !== undefined"` to `x-show="counts[value] != null"`.

## [4.1.1] - 2026-03-09

### Fixed

- Sorting a taxonomy filter on a multisite install showed terms from mixed locales instead of the current site's.

## [4.1.0] - 2026-02-21

### Added

- Laravel Boost guidelines.

## [4.0.0] - 2026-01-28

### Added

- Statamic 6 support.
- Livewire 4 support.

### Fixed

- The `?page` query string when using the custom query string.

For releases before 4.0.0, see the [GitHub releases](https://github.com/reachweb/statamic-livewire-filters/releases).

[Unreleased]: https://github.com/reachweb/statamic-livewire-filters/compare/v4.6.3...HEAD
[4.6.3]: https://github.com/reachweb/statamic-livewire-filters/compare/v4.6.2...v4.6.3
[4.6.2]: https://github.com/reachweb/statamic-livewire-filters/compare/v4.6.1...v4.6.2
[4.6.1]: https://github.com/reachweb/statamic-livewire-filters/compare/v4.6.0...v4.6.1
[4.6.0]: https://github.com/reachweb/statamic-livewire-filters/compare/v4.5.1...v4.6.0
[4.5.1]: https://github.com/reachweb/statamic-livewire-filters/compare/v4.5.0...v4.5.1
[4.5.0]: https://github.com/reachweb/statamic-livewire-filters/compare/v4.4.0...v4.5.0
[4.4.0]: https://github.com/reachweb/statamic-livewire-filters/compare/v4.3.2...v4.4.0
[4.3.2]: https://github.com/reachweb/statamic-livewire-filters/compare/v4.3.1...v4.3.2
[4.3.1]: https://github.com/reachweb/statamic-livewire-filters/compare/v4.3.0...v4.3.1
[4.3.0]: https://github.com/reachweb/statamic-livewire-filters/compare/v4.2.0...v4.3.0
[4.2.0]: https://github.com/reachweb/statamic-livewire-filters/compare/v4.1.1...v4.2.0
[4.1.1]: https://github.com/reachweb/statamic-livewire-filters/compare/v4.1.0...v4.1.1
[4.1.0]: https://github.com/reachweb/statamic-livewire-filters/compare/v4.0.0...v4.1.0
[4.0.0]: https://github.com/reachweb/statamic-livewire-filters/compare/v3.1.4...v4.0.0
