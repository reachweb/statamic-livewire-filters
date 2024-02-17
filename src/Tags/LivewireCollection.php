<?php

namespace Reach\StatamicLivewireFilters\Tags;

use Statamic\Tags\Tags;

class LivewireCollection extends Tags
{
    use Concerns\OutputsLivewireComponent, Concerns\SupportTaxonomyTermRoute;

    protected static $handle = 'livewire-collection';

    public function __call($method, $args)
    {
        $this->params['from'] = $this->method;

        return $this->output();

    }

    public function index()
    {
        if (! $this->params->hasAny(['from', 'in', 'folder', 'use', 'collection'])) {
            throw new \Reach\StatamicLivewireFilters\Exceptions\NoCollectionException;
        }

        return $this->output();
    }

    protected function output()
    {
        $this->addTaxonomyTermRoute();

        return $this->renderLivewireComponent(
            'livewire-collection',
            $this->params->all(),
        );
    }
}
