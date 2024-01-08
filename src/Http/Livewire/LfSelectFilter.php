<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

class LfSelectFilter extends LfRadioFilter
{
    use Traits\IsLivewireFilter;

    public $view = 'lf-select';

    public $options;

    public $selected = '';

    #[Computed(persist: true)]
    public function filterOptions()
    {
        if (isset($this->statamic_field['options'])) {
            return $this->statamic_field['options'];
        } elseif (is_array($this->options)) {
            return $this->options;
        }
    }

    public function updatedSelected()
    {
        $this->dispatch('filter-updated',
            field: $this->field,
            condition: $this->condition,
            payload: $this->selected,
            command: 'replace',
            modifier: $this->modifier,
        )
            ->to(LivewireCollection::class);
    }

    public function clear()
    {
        $this->selected = '';
        $this->clearFilters();
    }

    #[On('preset-params')]
    public function setPresetSort($params)
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
