<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Livewire\Attributes\Locked;
use Reach\StatamicLivewireFilters\Exceptions\TaxonomyFieldHandleMismatchException;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;
use Reach\StatamicLivewireFilters\Support\ParamKey;
use Statamic\Facades\Taxonomy;
use Statamic\Support\Arr;

trait IsLivewireFilter
{
    use HandleFieldOptions, HandleStatamicQueries;

    #[Locked]
    public $field;

    #[Locked]
    public $statamic_field;

    #[Locked]
    public $blueprint;

    #[Locked]
    public $collection;

    #[Locked]
    public $condition;

    #[Locked]
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

        if ($this->condition === 'taxonomy') {
            $this->ensureFieldHandleIsTaxonomy($field);
        }

        // The collection never sees the field type, so date ranges carry their comparisons in the modifier.
        if ($this->condition === 'dual_range' && $field->type() === 'date') {
            $this->modifier = 'is_after|is_before';
        }

        $field = $this->processFieldByType($field);

        $this->statamic_field = $field->toArray();
    }

    // The collection tag reads taxonomy:{handle}:{modifier} as a taxonomy handle, and
    // Statamic only associates entries with terms stored under the taxonomy's handle.
    protected function ensureFieldHandleIsTaxonomy($field): void
    {
        $taxonomies = Arr::wrap($field->get('taxonomies'));

        if (($field->type() === 'terms' && ! in_array($this->field, $taxonomies, true)) || ! Taxonomy::findByHandle($this->field)) {
            throw new TaxonomyFieldHandleMismatchException($this->field, $taxonomies);
        }
    }

    protected function processFieldByType($field)
    {
        if ($this->hasCustomOptions()) {
            return $this->addCustomOptionsToConfig($field);
        }

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

    public function clearFilters($boundsSelection = null)
    {
        $this->dispatch('clear-filter',
            field: $this->field,
            condition: $this->condition,
            modifier: $this->modifier,
            boundsSelection: $boundsSelection,
        )
            ->to(LivewireCollection::class);
    }

    protected function getParamKey()
    {
        return ParamKey::build($this->field, $this->condition, $this->modifier);
    }
}
