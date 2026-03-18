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

        $field = $this->processFieldByType($field);

        $this->statamic_field = $field->toArray();
    }

    protected function processFieldByType($field)
    {
        $fieldType = $field->type();

        // Try to call a type-specific processor method if it exists
        $processorMethod = 'process'.ucfirst($fieldType).'Field';
        if (method_exists($this, $processorMethod)) {
            return $this->$processorMethod($field);
        }

        // Process by configuration
        if ($this->hasOptionsInConfig($field)) {
            $field = $this->transformOptionsArray($field);

            return $this->addCountsArrayToConfig($field);
        }

        if ($this->hasCustomOptions()) {
            return $this->addCustomOptionsToConfig($field);
        }

        // Return unmodified field if no processors applied
        return $field;
    }

    protected function processTermsField($field)
    {
        return $this->addTermsToOptions($field);
    }

    protected function processEntriesField($field)
    {
        return $this->addEntriesToOptions($field);
    }

    protected function processDictionaryField($field)
    {
        return $this->addDictionaryToOptions($field);
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
