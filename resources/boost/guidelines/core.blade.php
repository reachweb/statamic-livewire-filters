## Statamic Livewire Filters

Statamic Livewire Filters is an addon that enables real-time, reactive filtering of Statamic collections without page reloads, using Livewire. It provides a drop-in replacement for Statamic's collection tag and a suite of filter components.

Full documentation: https://livewirefilters.com/llms-full.txt

### Basic Usage

Replace your collection tag with the livewire-collection tag and add filter components anywhere on the page:

@verbatim
<code-snippet name="Livewire Collection Tag" lang="antlers">
{{ livewire-collection:cars paginate="10" sort="title:asc" }}
</code-snippet>
@endverbatim

Your entry template is now at `resources/views/vendor/statamic-livewire-filters/livewire/livewire-collection.antlers.html`.

### Filter Components

All filters require `blueprint`, `field`, and `condition` properties. The `blueprint` format is `collection.blueprint` (e.g., `cars.car`).

Available filter types:

- **TextFilter**: Live search text input.
- **CheckboxFilter**: Multiple-choice checkboxes.
- **RadioFilter**: Single-choice radio buttons. With `buckets` it is a range list of numeric ranges and needs no `condition`.
- **SelectFilter**: Dropdown selection, with optional search.
- **DateFilter**: Date filtering using Flatpickr.
- **RangeFilter**: Single range slider.
- **DualRangeFilter**: Min/max range selection using noUiSlider.
- **ToggleFilter**: Boolean toggle switch.

@verbatim
<code-snippet name="Text filter example" lang="antlers">
{{ livewire:lf-text-filter
    blueprint="cars.car"
    field="title"
    condition="contains"
    placeholder="Search cars"
}}
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Checkbox filter with taxonomy" lang="antlers">
{{ livewire:lf-checkbox-filter
    blueprint="cars.car"
    field="car_brand"
    condition="taxonomy"
}}
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Select filter with search" lang="antlers">
{{ livewire:lf-select-filter
    blueprint="cars.car"
    field="transmission"
    condition="is"
    view="lf-select-advanced"
    searchable="true"
    placeholder="Transmission"
}}
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Range filter" lang="antlers">
{{ livewire:lf-range-filter
    blueprint="cars.car"
    field="seats"
    condition="gte"
    min="2"
    max="9"
    default="2"
}}
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Range list (radio filter with numeric buckets)" lang="antlers">
{{ livewire:lf-radio-filter
    blueprint="cars.car"
    field="price"
    buckets="0-12000|12000-16000|16000-20000|20000-"
    modifier="gte|lt"
}}
</code-snippet>
@endverbatim

- A bucket is `min-max`; leave a side empty for an open end (`20000-`, `-12000`). Each bucket is a radio option with its count, next to an "All" option.
- A bucket sets the field's `gte`/`lte` params like the DualRangeFilter. Both ends are inclusive by default, so a value on a shared edge matches both buckets; `modifier="gte|lt"` makes the upper end exclusive. The modifier takes `gt` or `gte`, then `lt` or `lte`.
- Labels are generated ("Under 12000", "12000 to 16000", "Over 20000"). Pass `options` keyed by bucket to set your own.

@verbatim
<code-snippet name="Toggle filter" lang="antlers">
{{ livewire:lf-toggle-filter
    blueprint="cars.car"
    field="date_of_registration"
    condition="gte"
    preset_value="2025-01-01"
    label="Only new vehicles"
}}
</code-snippet>
@endverbatim

### Sorting

@verbatim
<code-snippet name="Sort component" lang="antlers">
{{ livewire:lf-sort
    blueprint="cars.car"
    fields="title|max_passengers|price"
}}
</code-snippet>
@endverbatim

### UI Components

@verbatim
<code-snippet name="Tags and count components" lang="antlers">
{{ livewire:lf-tags blueprint="cars.car" fields="title|car_brand|transmission" }}
{{ livewire:lf-count }}
</code-snippet>
@endverbatim

### Preset Filters

You can preset filter values directly in the tag:

@verbatim
<code-snippet name="Preset filter values" lang="antlers">
{{ livewire-collection:cars taxonomy:car_brand:any="toyota" max_passengers:gte="4" }}
</code-snippet>
@endverbatim

### Allowed Filters

Restrict which filters can be applied for security:

@verbatim
<code-snippet name="Allowed filters" lang="antlers">
{{ livewire-collection:cars paginate="6" allowed_filters="taxonomy:car_brand:any|transmission:is" }}
</code-snippet>
@endverbatim

### Query Scopes

Use custom query scopes for advanced filtering:

@verbatim
<code-snippet name="Query scope filter" lang="antlers">
{{ livewire:lf-checkbox-filter
    blueprint="cars.car"
    field="car_brand"
    condition="query_scope"
    modifier="multiselect"
}}
</code-snippet>
@endverbatim

### URL Query String

Enable SEO-friendly URLs in the config:

@verbatim
<code-snippet name="Custom query string config" lang="php">
// config/statamic-livewire-filters.php
'custom_query_string' => 'search',
'custom_query_string_aliases' => [
    'brand' => 'taxonomy:car_brand:any',
    'fuel' => 'fuel_type:is',
],
</code-snippet>
@endverbatim

Add the URL handler component to your layout:

@verbatim
<code-snippet name="URL handler component" lang="antlers">
{{ livewire:lf-url-handler }}
</code-snippet>
@endverbatim

Add SEO head tags for filtered listings to the layout `<head>`:

@verbatim
<code-snippet name="SEO tags" lang="antlers">
{{ livewire-filters:seo }}
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="SEO config" lang="php">
// config/statamic-livewire-filters.php
// Filter keys (custom query string alias, params key or field handle) whose single-value URLs stay indexable
'seo_indexable_filters' => ['brand'],
// Custom query string mode: send the filter signals as headers
'seo_filter_headers' => true,
// Query string names that carry the page number (add each collection's custom page_name)
'seo_page_names' => ['page'],
</code-snippet>
@endverbatim

- Filtered pages (any filter, including `sort`) get `noindex,follow` and a canonical to the base page, without filter segments, the `params[...]` query or the page number. Only `field:condition` style keys, `sort`, `order_by` and `taxonomy` count as filters. Collection settings such as `page_name`, view variables and blocked params don't.
- Unfiltered pages above 1 stay indexable. Their canonical keeps the page number (`/cars?page=2`), so search engines can reach the entries on later pages.
- A first page whose filters are all single-valued and listed in `seo_indexable_filters` stays indexable with a self canonical. The canonical is rebuilt from the recognized filters in a fixed order, so unknown params, segments and param order don't change it. Later pages of that filter get `noindex,follow`.
- A field handle in `seo_indexable_filters` covers every condition Statamic applies to that field. List the full `params` key (`brand:is`) to allow one condition only. A key with an unknown condition never matches a field handle, because the query ignores it.
- Filters a tag presets (for example `sort="title"`) also appear in the Livewire mode URL. List them in `seo_indexable_filters` when those URLs should stay indexable.
- Livewire query string mode and no URL mode: the tag outputs `<link rel="canonical">` on every page that uses the layout, and `<meta name="robots">` on filtered pages.
- A page must not have two different canonicals. When the layout already outputs one (SEO Pro, a hand-written tag), use `{{ livewire-filters:seo canonical="false" }}` to output only the robots tag. `seo_indexable_filters` then only decides the robots tag, so the other canonical has to point to the filter URL itself for that URL to be indexed.
- Custom query string mode: every filter URL of a page shares the base page's static cache entry, so the tag only outputs the canonical of pages above 1 (`/cars?page=2`), which are cached separately; page 1 has no canonical tag. With `seo_filter_headers` on, filter URLs get `X-Robots-Tag: noindex, follow` and `Link: <url>; rel="canonical"` headers instead. Headers aren't stored in the cached page, so they stay correct with half and full static caching. The `Link` header of an indexable filter URL disagrees with a canonical another tool puts in the shared `<head>`, so remove that one from listing templates.

### Hooks

Modify entries data before display:

@verbatim
<code-snippet name="Using hooks" lang="php">
\Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection::hook('livewire-fetched-entries',
    function ($entries, $next) {
        $entries->each(function ($entry) {
            // Modify entry data
        });
        return $next($entries);
    }
);
</code-snippet>
@endverbatim

### File Structure

- `src/Http/Livewire/LivewireCollection.php` — Main Livewire component managing filtering state.
- `src/Http/Livewire/Lf*.php` — Individual filter components (LfCheckboxFilter, LfRadioFilter, etc.).
- `src/Http/Livewire/Traits/` — Shared traits: `IsLivewireFilter`, `HandleParams`, `HandleFieldOptions`, `GenerateParams`.
- `src/Tags/LivewireCollection.php` — Antlers livewire-collection tag.
- `resources/views/livewire/filters/` — Filter component views.
- `resources/views/livewire/sort/` — Sort component view.
- `resources/views/livewire/ui/` — Pagination, tags, count views.

### Namespace

PHP namespace: `Reach\StatamicLivewireFilters`
