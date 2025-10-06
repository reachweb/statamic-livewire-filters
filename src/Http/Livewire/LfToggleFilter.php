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
            // For dual range, check if the values match the preset value
            if (array_key_exists($paramKey['min'], $params) || array_key_exists($paramKey['max'], $params)) {
                // For dual range, preset_value should be an array with 'min' and 'max' keys
                if (is_array($this->preset_value)) {
                    $minMatch = ! isset($this->preset_value['min']) || (isset($params[$paramKey['min']]) && $params[$paramKey['min']] == $this->preset_value['min']);
                    $maxMatch = ! isset($this->preset_value['max']) || (isset($params[$paramKey['max']]) && $params[$paramKey['max']] == $this->preset_value['max']);
                    $this->selected = $minMatch && $maxMatch;
                } else {
                    $this->selected = true;
                }
            }
        } else {
            // For regular conditions, check if the param exists and matches the preset value
            if (array_key_exists($paramKey, $params)) {
                // Check if the value in params matches our preset value
                $this->selected = $params[$paramKey] === $this->preset_value;
            }
        }
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.filters.'.$this->view);
    }
}
