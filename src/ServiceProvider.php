<?php

namespace Reach\StatamicLivewireFilters;

use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $tags = [
        \Reach\StatamicLivewireFilters\Tags\LivewireCollection::class,
    ];

    public function bootAddon()
    {
        //
    }
}
