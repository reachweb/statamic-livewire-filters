<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;

trait IsLivewireFilter
{
    use HandleFieldOptions, HandleStatamicQueries;

    public $field;

    public $statamic_field;

    public $blueprint;

    public $collection;

    public $condition;

    public $modifier = 'any';

    public function mountIsLivewireFilter($blueprint)
    {
        [$collection, $blueprint] = explode('.', $blueprint);
        $this->collection = $collection;
        $this->blueprint = $blueprint;

        $this->initiateField();

    }

    public function initiateField()
    {
        $blueprint = $this->getStatamicBlueprint();
        $field = $this->getStatamicField($blueprint);

        if ($field->type() == 'terms') {
            $field = $this->addTermsToOptions($field);
        } elseif ($this->hasOptionsInConfig($field)) {
            $field = $this->addCountsArrayToConfig($field);
        } elseif ($this->hasCustomOptions()) {
            $field = $this->addCustomOptionsToConfig($field);
        }

        $this->statamic_field = $field->toArray();
    }

    public function clearFilters()
    {
        $this->dispatch('filter-updated',
            field: $this->field,
            condition: $this->condition,
            payload: false,
            command: 'clear',
            modifier: $this->modifier,
        )
            ->to(LivewireCollection::class);
    }

    protected function getParamKey()
    {
        if ($this->condition === 'taxonomy') {
            return 'taxonomy:'.$this->field.':'.$this->modifier;
        }

        if ($this->condition === 'query_scope') {
            return $this->modifier.':'.$this->field;
        }

        return $this->field.':'.$this->condition;
    }
}
