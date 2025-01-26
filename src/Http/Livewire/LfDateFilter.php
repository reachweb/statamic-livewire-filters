<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class LfDateFilter extends Component
{
    use Traits\IsLivewireFilter;

    public $view = 'lf-date';

    public $selected = '';

    public function updatedSelected()
    {
        $this->dispatch('filter-updated',
            field: $this->field,
            condition: $this->condition,
            payload: $this->selected,
            modifier: $this->modifier,
        )
            ->to(LivewireCollection::class);
    }

    #[Computed(persist: true)]
    public function filterOptions()
    {
        $fieldOptions = collect($this->statamic_field);

        return $fieldOptions->only(['earliest_date', 'latest_date'])->all();
    }

    #[On('clear-all-filters')]
    public function clear()
    {
        $this->selected = '';
        $this->clearFilters();
    }

    #[On('clear-option')]
    public function clearOption($tag)
    {
        if ($tag['field'] === $this->field) {
            $this->clear();
        }
    }

    #[On('preset-params')]
    public function setPresetValues($params)
    {
        if (array_key_exists($this->getParamKey(), $params)) {
            $this->selected = $params[$this->getParamKey()];
        }
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.filters.'.$this->view);
    }
}
