<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class LfCheckboxFilter extends Component
{
    use Traits\HandleEntriesCount, Traits\IsLivewireFilter;

    public $view = 'lf-checkbox';

    public $options;

    public $selected = [];

    public $previousSelected = [];

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
        if (config('statamic-livewire-filters.validate_filter_values')) {
            $this->validate();
        }

        $optionsToAdd = array_diff($this->selected, $this->previousSelected);
        $optionsToRemove = array_diff($this->previousSelected, $this->selected);

        foreach ($optionsToAdd as $option) {
            $this->dispatch('filter-updated',
                field: $this->field,
                condition: $this->condition,
                payload: $option,
                command: 'add',
                modifier: $this->modifier,
            )
                ->to(LivewireCollection::class);
        }

        foreach ($optionsToRemove as $option) {
            $this->dispatch('filter-updated',
                field: $this->field,
                condition: $this->condition,
                payload: $option,
                command: 'remove',
                modifier: $this->modifier,
            )
                ->to(LivewireCollection::class);
        }

        $this->previousSelected = $this->selected;
    }

    public function clear()
    {
        $this->selected = [];
        $this->previousSelected = [];
        $this->clearFilters();
    }

    public function rules()
    {
        return [
            'selected' => ['array', Rule::in(array_keys($this->filterOptions()))],
        ];
    }

    #[On('preset-params')]
    public function setPresetSort($params)
    {
        if (array_key_exists($this->getParamKey(), $params)) {
            $this->selected = explode('|', $params[$this->getParamKey()]);
            $this->previousSelected = $this->selected;
        }
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.filters.'.$this->view);
    }
}
