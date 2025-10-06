<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class LfToggleFilter extends Component
{
    use Traits\IsLivewireFilter;

    public $view = 'lf-toggle';

    public $label = '';

    public $selected = false;

    public $preset_value;

    public function updatedSelected()
    {
        if ($this->selected) {
            $this->dispatch('filter-updated',
                field: $this->field,
                condition: $this->condition,
                payload: $this->preset_value,
                modifier: $this->modifier,
            )
                ->to(LivewireCollection::class);
        } else {
            $this->clearFilters();
        }
    }

    #[On('clear-all-filters')]
    public function clear()
    {
        $this->selected = false;
        $this->clearFilters();
    }

    #[On('clear-option')]
    public function clearOption($tag)
    {
        if ($tag['field'] === $this->field) {
            $this->selected = false;
            $this->clearFilters();
        }
    }

    #[On('preset-params')]
    public function setPresetValues($params)
    {
        $paramKey = $this->getParamKey();

        // Handle dual_range condition which returns an array
        if (is_array($paramKey)) {
            if (array_key_exists($paramKey['min'], $params) || array_key_exists($paramKey['max'], $params)) {
                $this->selected = true;
            }
        } else {
            if (array_key_exists($paramKey, $params)) {
                $this->selected = true;
            }
        }
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.filters.'.$this->view);
    }
}
