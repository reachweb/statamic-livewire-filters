<?php

namespace Reach\StatamicLivewireFilters;

use Statamic\Providers\AddonServiceProvider;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection as LivewireCollectionComponent;
use Livewire\Livewire;

class ServiceProvider extends AddonServiceProvider
{
    protected $tags = [
        \Reach\StatamicLivewireFilters\Tags\LivewireCollection::class,
    ];

    public function bootAddon()
    {
        Livewire::component('livewire-collection', LivewireCollectionComponent::class);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'statamic-livewire-filters');
    }
}
