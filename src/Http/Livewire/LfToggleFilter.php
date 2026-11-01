<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Attributes\Locked;

class LfToggleFilter extends LivewireFilter
{
    #[Locked]
    public $view = 'lf-toggle';

    public $label = '';

    public $selected = false;

    public $preset_value;

    protected function payload(): mixed
    {
        return $this->preset_value;
    }

    protected function emptyValue(): mixed
    {
        return false;
    }

    public function updatedSelected()
    {
        if ($this->selected) {
            $this->dispatchFilterUpdated();
        } else {
            $this->clearFilters();
        }
    }

    protected function applyPreset(array $params): void
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
                $this->selected = $params[$paramKey] == $this->preset_value;
            }
        }
    }
}
