# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Statamic Livewire Filters is a Statamic addon that provides Livewire-powered live filtering for Statamic collections. It allows users to filter entries using various filter components without writing JavaScript.

## Commands

### Testing
```bash
# Run all tests
./vendor/bin/phpunit

# Run a single test file
./vendor/bin/phpunit tests/Feature/LfCheckboxFilterTest.php

# Run a specific test method
./vendor/bin/phpunit --filter testMethodName
```

### Code Formatting
```bash
# Format code with Laravel Pint
./vendor/bin/pint
```

## Architecture

### Core Components

**ServiceProvider** (`src/ServiceProvider.php`)
- Extends Statamic's `AddonServiceProvider`
- Registers all Livewire components, middleware, tags, scopes, and commands
- Publishes views, config, and CSS theme

**Main Livewire Component** (`src/Http/Livewire/LivewireCollection.php`)
- Central component that manages filtering state and renders filtered entries
- Listens for `filter-updated` and `sort-updated` events from filter components
- Uses Statamic's `Entries` class internally to query collections
- Supports hooks via Statamic's `Hookable` trait

**Filter Components** (`src/Http/Livewire/Lf*.php`)
- Each filter type is a separate Livewire component (Checkbox, Radio, Select, Text, Range, DualRange, Date, Toggle)
- All filters use the `IsLivewireFilter` trait which handles blueprint/field initialization
- Filters dispatch `filter-updated` event to `LivewireCollection` when values change

**Key Traits** (`src/Http/Livewire/Traits/`)
- `IsLivewireFilter`: Base trait for all filter components, handles field options from blueprints
- `HandleParams`: Manages filter parameter state on LivewireCollection
- `HandleFieldOptions`: Resolves options from Statamic fields (terms, entries, dictionaries)
- `GenerateParams`: Converts filter state to Statamic collection tag parameters

**Statamic Tag** (`src/Tags/LivewireCollection.php`)
- Antlers tag `{{ livewire-collection:collection_handle }}` that renders the Livewire component
- Accepts same parameters as Statamic's native `{{ collection }}` tag

### Event Flow

1. User interacts with a filter component
2. Filter dispatches `filter-updated` event with field, condition, payload, and modifier
3. `LivewireCollection` receives event, updates params, re-queries entries
4. `LivewireCollection` dispatches `entries-updated` event with count info
5. UI components like `LfCount` and `LfTags` react to the update

### Configuration

Config file: `config/config.php` (publishable)
- `enable_query_string`: Save filter state to URL
- `enable_filter_values_count`: Show counts per filter option (performance impact)
- `custom_query_string`: Enable custom URL parameter aliases

### Views

Views are in `resources/views/livewire/`:
- `filters/`: Individual filter component views
- `sort/`: Sort component view
- `ui/`: Pagination, tags, count, placeholder views
- `utility/`: URL handler view

Views are publishable to `resources/views/vendor/statamic-livewire-filters/`.

## Testing

Tests use Orchestra Testbench with Statamic and Livewire. Test fixtures are in `tests/__fixtures__/`. The `PreventSavingStacheItemsToDisk` trait prevents test data from persisting.
