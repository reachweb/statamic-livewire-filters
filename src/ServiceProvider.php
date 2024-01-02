<?php

namespace Reach\StatamicLivewireFilters;

use Livewire\Livewire;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection as LivewireCollectionComponent;
use Reach\StatamicLivewireFilters\Http\Livewire\LfCheckbox;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $tags = [
        \Reach\StatamicLivewireFilters\Tags\LivewireCollection::class,
    ];

    // protected $vite = [ 
    //     'input' => [
    //         'resources/js/app.js',
    //         'resources/css/app.css',
    //     ],
    //     'hotFile' => 'vite.hot',
    //     'publicDirectory'=> 'dist',
    // ]; 

    public function bootAddon()
    {
        Livewire::component('livewire-collection', LivewireCollectionComponent::class);
        Livewire::component('lf-checkbox', LfCheckbox::class);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'statamic-livewire-filters');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/statamic-livewire-filters'),
        ], 'statamic-livewire-filters');
    }
}
