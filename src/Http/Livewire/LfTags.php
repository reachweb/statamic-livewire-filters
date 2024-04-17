<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Statamic\Fields\Blueprint;

class LfTags extends Component
{
    use Traits\HandleStatamicQueries;

    public $view = 'tags';

    public string $blueprint;

    protected Blueprint $statamicBlueprint;

    public string $collection;

    public $fields;

    public function mount($blueprint, $fields)
    {
        [$collection, $blueprint] = explode('.', $blueprint);
        $this->collection = $collection;
        $this->blueprint = $blueprint;
        $this->fields = explode('|', $fields);
        $this->statamicBlueprint = $this->getStatamicBlueprint();
    }

    #[Computed(persist: true)]
    public function optionLabels(): array
    {
        $optionLabels = [];
        ray($this->fields);
        foreach ($this->fields as $field_handle) {
            $field = $this->getStatamicField($this->statamicBlueprint, $field_handle);
            if ($field->type() == 'terms') {
                $terms = collect();
                collect($field->config()['taxonomies'])->each(function ($taxonomy) use ($terms) {
                    $terms->push(($this->getTaxonomyTerms($taxonomy)->all()));
                });
                $optionLabels[$field_handle] =  $terms->collapse()->all();
            } else {
                if (array_key_exists('options', $field->toArray())) {
                    $optionLabels[$field_handle] = $field->get('options');
                }
            }        
        }
        
        return $optionLabels;
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.ui.'.$this->view);
    }
}
