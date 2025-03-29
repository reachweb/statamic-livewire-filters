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
        } elseif ($field->type() == 'entries') {
            $field = $this->addEntriesToOptions($field);
        } elseif ($this->hasOptionsInConfig($field)) {
            $field = $this->transformOptionsArray($field);
            $field = $this->addCountsArrayToConfig($field);
        } elseif ($this->hasCustomOptions()) {
            $field = $this->addCustomOptionsToConfig($field);
        }

        $this->statamic_field = $field->toArray();
    }

    public function clearFilters()
    {
        $this->dispatch('clear-filter',
            field: $this->field,
            condition: $this->condition,
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

        if ($this->condition === 'dual_range') {
            $minModifier = 'gte';
            $maxModifier = 'lte';

            if ($this->statamic_field['type'] === 'date') {
                $minModifier = 'is_after';
                $maxModifier = 'is_before';
            }

            if ($this->modifier !== 'any') {
                [$minModifier, $maxModifier] = explode('|', $this->modifier);
            }

            return [
                'min' => $this->field.':'.$minModifier,
                'max' => $this->field.':'.$maxModifier,
            ];
        }

        return $this->field.':'.$this->condition;
    }
}
