<?php

namespace Reach\StatamicLivewireFilters\Tags;

use Statamic\Tags\Tags;

class LivewireCollection extends Tags
{
    use Concerns\OutputsLivewireComponent;

    protected static $handle = 'livewire-collection';

    public function __call($method, $args)
    {
        $this->params['from'] = $this->method;

        return $this->output();

    }

    public function index()
    {
        if (! $this->params->hasAny(['from', 'in', 'folder', 'use', 'collection'])) {
            return $this->context->value('collection');
        }

        return $this->output();
    }

    protected function output()
    {
        return $this->renderLivewireComponent(
            'livewire-collection',
            $this->params->all(),
            $this->params->get('key')
        );
    }

}