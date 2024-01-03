<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Jonassiewertsen\Livewire\WithPagination;
use Livewire\Attributes\On; 
use Livewire\Component;
use Statamic\Tags\Collection\Entries;

class LivewireCollection extends Component
{
    use Traits\GenerateParams, Traits\HandleConditions, WithPagination;

    public $params;

    public $view = 'livewire-collection';

    public function mount($params)
    {
        $this->setParameters($params);
    }

    public function setParameters($params)
    {
        if (array_key_exists('view', $params)) {
            $this->view = $params['view'];
            unset($params['view']);
        }
        $this->params = $params;
        ray($this->params);
    }

    #[On('filter-updated')] 
    public function updateParameters($field, $condition, $payload, $modifier)
    {
        if ($condition === 'taxonomy') {
            $this->handleTaxonomyCondition($field, $condition, $payload, $modifier);
            return;
        }
        if ($payload === null || $payload === '' || $payload === []) {
            unset($this->params[$field.':'.$condition]);
            return;
        }
        if (is_array($payload)) {
            $payload = implode('|', $payload);
        }
        // $this->handleExceptions($field, $condition, $payload);
        $this->params[$field.':'.$condition] = $payload;
    }

    protected function handleTaxonomyCondition($field, $condition, $payload, $modifier)
    {
        
    }

    public function entries()
    {
        $entries = (new Entries($this->generateParams($this->params)))->get();
        $this->dispatch('entriesUpdated');
        if (isset($this->params['paginate'])) {
            return $this->withPagination('entries', $entries);
        }

        return ['entries' => $entries];
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.'.$this->view)->with([
            ...$this->entries(),
        ]);
    }
}
