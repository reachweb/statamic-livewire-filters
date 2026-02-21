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
- **RadioFilter**: Single-choice radio buttons.
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
