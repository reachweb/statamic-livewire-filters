<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;

trait IsLivewireFilter
{
    use HandleStatamicQueries;

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
            $terms = collect();
            collect($field->config()['taxonomies'])->each(function ($taxonomy) use ($terms) {
                $terms->push(($this->getTaxonomyTerms($taxonomy)->all()));
            });
            $field->setConfig([
                'options' => $terms->collapse()->all(),
                'counts' => $terms->collapse()->keys()->flatMap(fn ($slug) => [$slug => null])->all(),
            ]);
        } else {
            if (array_key_exists('options', $field->toArray())) {
                $field->setConfig(array_merge(
                    $field->config(),
                    ['counts' => collect($field->get('options'))->keys()->flatMap(fn ($option) => [$option => null])->all()]
                ));
            }
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
