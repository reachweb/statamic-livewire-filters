<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Jonassiewertsen\Livewire\WithPagination;
use Livewire\Attributes\On; 
use Livewire\Component;
use Statamic\Tags\Collection\Entries;

class LivewireCollection extends Component
{
    use Traits\GenerateParams, WithPagination;

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
    }

    #[On('filter-updated')] 
    public function updateParameters($field, $condition, $payload, $command, $modifier = 'any')
    {
        // if ($condition === 'taxonomy') {
        //     $this->handleTaxonomyCondition($field, $condition, $payload, $modifier, $command);
        //     return;
        // }
        $this->handleCondition($field, $condition, $payload, $command);
    }

    protected function handleCondition($field, $condition, $payload, $command)
    {
        $paramKey = $field.':'.$condition;
        if ($command === 'add') {
            $this->addValueToParam($paramKey, $payload);
        } elseif ($command === 'replace') {
            $this->replaceValueOfParam($paramKey, $payload);
        } elseif ($command === 'remove') {
            $this->removeValueFromParam($paramKey, $payload);
        }
    }

    protected function addValueToParam($paramKey, $value)
    {
        if (!isset($this->params[$paramKey])) {
            $this->params[$paramKey] = $value;
        } else {
            $values = collect(explode('|', $this->params[$paramKey]));
            if (!$values->contains($value)) {
                $values->push($value);
                $this->params[$paramKey] = $values->implode('|');
            }
        }
    }

    protected function replaceValueOfParam($paramKey, $value)
    {
        $this->params[$paramKey] = $value;
    }

    protected function removeValueFromParam($paramKey, $value)
    {
        if (isset($this->params[$paramKey])) {
            $values = collect(explode('|', $this->params[$paramKey]));

            $values = $values->reject(fn ($item) => $item === $value);

            if ($values->isNotEmpty()) {
                $this->params[$paramKey] = $values->implode('|');
            } else {
                unset($this->params[$paramKey]);
            }
        }
    }

    protected function handleTaxonomyCondition($field, $condition, $payload, $modifier, $command)
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
