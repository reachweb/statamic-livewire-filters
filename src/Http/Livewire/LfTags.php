<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class LfTags extends Component
{
    use Traits\HandleFieldOptions, Traits\HandleStatamicQueries;

    public $view = 'tags';

    #[Locked]
    public string $blueprint;

    #[Locked]
    public string $collection;

    #[Locked]
    public $fields;

    #[Locked]
    public $params;

    #[Locked]
    public $tags;

    public function mount($blueprint, $fields)
    {
        [$collection, $blueprint] = explode('.', $blueprint);
        $this->collection = $collection;
        $this->blueprint = $blueprint;
        $this->fields = explode('|', $fields);
        $this->tags = collect();
    }

    #[Computed(persist: true)]
    public function statamicFields(): Collection
    {
        $statamicFields = collect();
        $statamicBlueprint = $this->getStatamicBlueprint();
        foreach ($this->fields as $field_handle) {
            $field = $this->getStatamicField($statamicBlueprint, $field_handle);
            if ($field->type() == 'terms') {
                $field = $this->addTermsToOptions($field);
            }
            $statamicFields->put($field_handle, $field->toArray());
        }

        return $statamicFields;
    }

    #[On('tags-updated')]
    public function updateTags($params)
    {
        $this->params = collect($params)
            ->reject(fn ($value, $key) => $key === 'sort')
            ->reject(fn ($value, $key) => ! Str::contains($key, ':') && ! Str::startsWith($key, 'query_scope'));

        $this->tags = collect();

        // Order is critical here
        $this->parseQueryScopes();
        $this->parseTaxonomyTerms();
        $this->parseConditions();
    }

    public function parseConditions()
    {
        $this->params->each(function ($value, $key) {
            [$field, $condition] = explode(':', $key);
            $values = collect(explode('|', $value));
            $values->each(function ($value) use ($field, $condition) {
                $this->addFieldOptionToTags($field, $value, $condition);
            });
        });
    }

    public function parseTaxonomyTerms()
    {
        $taxonomies = $this->params->filter(fn ($value, $key) => Str::startsWith($key, 'taxonomy:'));

        if ($taxonomies->isEmpty()) {
            return;
        }

        $this->handleTaxonomyTermConditions($taxonomies);
    }

    public function parseQueryScopes()
    {
        $scopes = $this->params->filter(fn ($value, $key) => Str::startsWith($key, 'query_scope'));

        if ($scopes->isEmpty()) {
            return;
        }

        $scopes = collect(explode('|', $scopes->first()));

        $scopes->each(function ($scopeName) {
            $scopeValues = $this->params->filter(fn ($value, $key) => Str::startsWith($key, $scopeName));
            $this->handleQueryScopeCondition($scopeValues);
        });

        $this->params = $this->params->reject(fn ($value, $key) => Str::startsWith($key, 'query_scope'));

    }

    public function handleTaxonomyTermConditions($taxonomies)
    {
        $taxonomies->each(function ($values, $key) {
            [$taxonomy, $field, $condition] = explode(':', $key);
            $terms = collect(explode('|', $values));
            $terms->each(function ($value) use ($field, $condition) {
                $this->addFieldOptionToTags($field, $value, $condition);
            });
        });

        $this->params = $this->params->reject(fn ($value, $key) => Str::startsWith($key, 'taxonomy:'));
    }

    public function handleQueryScopeCondition($values)
    {
        $values->each(function ($values, $key) {
            [$scope, $field] = explode(':', $key);
            if ($this->isNotTaggable($field)) {
                $this->params = $this->params->reject(fn ($value, $key) => Str::startsWith($key, $scope));

                return;
            }
            $selectedValues = is_array($values) ? collect($values)->flatten() : collect(explode('|', $values));
            $selectedValues->each(function ($value) use ($field) {
                $this->addFieldOptionToTags($field, $value, 'query_scope');
            });
            $this->params = $this->params->reject(fn ($value, $key) => Str::startsWith($key, $scope));
        });
    }

    public function addFieldOptionToTags($field, $value, $condition = null)
    {
        if ($this->isNotTaggable($field)) {
            return;
        }
        $fieldLabel = $this->statamicFields->get($field)['display'] ?? $field;
        $optionLabel = $this->statamicFields->get($field)['options'][$value] ?? $value;
        $tag = [
            'field' => $field,
            'value' => $value,
            'fieldLabel' => $fieldLabel,
            'optionLabel' => $optionLabel,
            'condition' => $condition,
        ];

        $this->tags->push($tag);
    }

    public function removeOption($field, $value)
    {
        $tag = $this->tags->firstOrFail(fn ($item) => $item['field'] === $field && $item['value'] === $value);
        $this->dispatch('clear-option', $tag);
    }

    public function isNotTaggable($field)
    {
        return ! in_array($field, $this->fields);
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.ui.'.$this->view);
    }
}
