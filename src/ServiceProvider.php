<?php

namespace Reach\StatamicLivewireFilters;

use Livewire\Livewire;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection as LivewireCollectionComponent;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $tags = [
        \Reach\StatamicLivewireFilters\Tags\LivewireCollection::class,
    ];

    public function bootAddon()
    {
        Livewire::component('livewire-collection', LivewireCollectionComponent::class);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'statamic-livewire-filters');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/statamic-livewire-filters'),
        ], 'statamic-livewire-filters');
    }
}
