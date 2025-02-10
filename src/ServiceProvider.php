<?php

namespace Reach\StatamicLivewireFilters;

use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Reach\StatamicLivewireFilters\Http\Livewire\LfCheckboxFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfDateFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfDualRangeFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfRadioFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfRangeFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfSelectFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfSort;
use Reach\StatamicLivewireFilters\Http\Livewire\LfTags;
use Reach\StatamicLivewireFilters\Http\Livewire\LfTextFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfUrlHandler;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection as LivewireCollectionComponent;
use Reach\StatamicLivewireFilters\Http\Middleware\HandleFiltersQueryString;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $middlewareGroups = [
        'web' => [
            HandleFiltersQueryString::class,
        ],
    ];

    protected $tags = [
        \Reach\StatamicLivewireFilters\Tags\LivewireCollection::class,
    ];

    protected $scopes = [
        \Reach\StatamicLivewireFilters\Scopes\Multiselect::class,
    ];

    protected $commands = [
        \Reach\StatamicLivewireFilters\Console\Commands\UpdateLivewireFilters::class,
    ];

    protected $publishables = [
        __DIR__.'/../resources/build' => 'build',
    ];

    public function bootAddon()
    {
        Livewire::component('livewire-collection', LivewireCollectionComponent::class);
        Livewire::component('lf-checkbox-filter', LfCheckboxFilter::class);
        Livewire::component('lf-date-filter', LfDateFilter::class);
        Livewire::component('lf-dual-range-filter', LfDualRangeFilter::class);
        Livewire::component('lf-radio-filter', LfRadioFilter::class);
        Livewire::component('lf-range-filter', LfRangeFilter::class);
        Livewire::component('lf-text-filter', LfTextFilter::class);
        Livewire::component('lf-select-filter', LfSelectFilter::class);
        Livewire::component('lf-sort', LfSort::class);
        Livewire::component('lf-tags', LfTags::class);
        Livewire::component('lf-url-handler', LfUrlHandler::class);

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'statamic-livewire-filters');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'statamic-livewire-filters');

        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'statamic-livewire-filters');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/statamic-livewire-filters'),
        ], 'statamic-livewire-filters-views');

        $this->publishes([
            __DIR__.'/../config/config.php' => config_path('statamic-livewire-filters.php'),
        ], 'statamic-livewire-filters-config');

        $this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/statamic-livewire-filters'),
        ], 'statamic-livewire-filters-lang');

        if ($this->app->runningInConsole()) {
            Artisan::call('statamic-livewire-filters:update');
        }
    }
}
