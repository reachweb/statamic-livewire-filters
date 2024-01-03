<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;

class LfCheckbox extends Component
{
    use Traits\IsLivewireFilter;

    public $view = 'lf-checkbox';

    public $options;

    public $selected = [];
    public $previousSelected = [];

    #[Computed]
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
        $optionsToAdd = array_diff($this->selected, $this->previousSelected);
        $optionsToRemove = array_diff($this->previousSelected, $this->selected);

        foreach ($optionsToAdd as $option) {
            $this->dispatch('filter-updated',
                field: $this->field,
                condition: $this->condition,
                payload: $option,
                command: 'add',
                modifer: $this->modifier,
            )
                ->to(LivewireCollection::class);
        }

        foreach ($optionsToRemove as $option) {
            $this->dispatch('filter-updated',
                field: $this->field,
                condition: $this->condition,
                payload: $option,
                command: 'remove',
                modifer: $this->modifier,
            )
                ->to(LivewireCollection::class);
        }    

        $this->previousSelected = $this->selected;
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.filters.'.$this->view);
    }
}
