<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Reach\StatamicLivewireFilters\Exceptions\BlueprintNotFoundException;
use Reach\StatamicLivewireFilters\Exceptions\FieldNotFoundException;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Taxonomy;

trait IsLivewireFilter
{
    public $field;

    public $statamic_field;

    public $blueprint;

    public $collection;

    public $condition;

    public $modifier;

    public function mountIsLivewireFilter($blueprint, $field, $condition, $modifier = 'any')
    {
        [$collection, $blueprint] = explode('.', $blueprint);
        $this->collection = $collection;
        $this->blueprint = $blueprint;
        $this->field = $field;
        $this->condition = $condition;
        $this->modifier = $modifier;

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
            $field->setConfig(['options' => $terms->collapse()->all()]);
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

    protected function getTaxonomyTerms($taxonomy_handle)
    {
        $taxonomy = Taxonomy::findByHandle($taxonomy_handle);

        return $taxonomy->queryTerms()->get()->flatMap(function ($term) {
            return [
                $term->slug() => $term->title(),
            ];
        });
    }

    public function getStatamicBlueprint()
    {
        if ($blueprint = Blueprint::find('collections.'.$this->collection.'.'.$this->blueprint)) {
            return $blueprint;
        }
        throw new BlueprintNotFoundException($this->blueprint);
    }

    public function getStatamicField($blueprint)
    {
        if ($field = $blueprint->field($this->field)) {
            return $field;
        }
        throw new FieldNotFoundException($this->field, $this->blueprint);
    }
}
