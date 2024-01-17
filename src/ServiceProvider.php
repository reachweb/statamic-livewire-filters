<?php

namespace Reach\StatamicLivewireFilters;

use Livewire\Livewire;
use Reach\StatamicLivewireFilters\Http\Livewire\LfCheckboxFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfDateFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfRadioFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfRangeFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfSelectFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfSort;
use Reach\StatamicLivewireFilters\Http\Livewire\LfTextFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection as LivewireCollectionComponent;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $tags = [
        \Reach\StatamicLivewireFilters\Tags\LivewireCollection::class,
    ];

    protected $scopes = [
        \Reach\StatamicLivewireFilters\Scopes\Multiselect::class,
    ];

    protected $publishables = [
        __DIR__.'/../resources/build' => 'assets',
    ];

    public function bootAddon()
    {
        Livewire::component('livewire-collection', LivewireCollectionComponent::class);
        Livewire::component('lf-checkbox-filter', LfCheckboxFilter::class);
        Livewire::component('lf-date-filter', LfDateFilter::class);
        Livewire::component('lf-radio-filter', LfRadioFilter::class);
        Livewire::component('lf-range-filter', LfRangeFilter::class);
        Livewire::component('lf-text-filter', LfTextFilter::class);
        Livewire::component('lf-select-filter', LfSelectFilter::class);
        Livewire::component('lf-sort', LfSort::class);

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'statamic-livewire-filters');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'statamic-livewire-filters');

        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'statamic-livewire-filters');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/statamic-livewire-filters'),
        ], 'statamic-livewire-filters-views');

        $this->publishes([
            __DIR__.'/../config/config.php' => config_path('statamic-livewire-filters.php'),
        ], 'statamic-livewire-filters');
    }
}
