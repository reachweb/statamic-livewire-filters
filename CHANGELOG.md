# Changelog

All notable changes to this project are documented in this file. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

Full documentation: [livewirefilters.com](https://livewirefilters.com).

## [Unreleased]

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

[Unreleased]: https://github.com/reachweb/statamic-livewire-filters/compare/v4.6.0...HEAD
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
