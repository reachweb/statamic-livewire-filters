<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

class LfDualRangeFilter extends Component
{
    use Traits\IsLivewireFilter;

    #[Locked]
    public $view = 'lf-dual-range';

    #[Validate('required')]
    public $selectedMin;

    #[Validate('required')]
    public $selectedMax;

    #[Locked]
    public $min;

    #[Locked]
    public $max;

    #[Locked]
    public $step = 1;

    #[Locked]
    public $minRange = 1;

    #[Locked]
    public $format = 'number';

    public function mount($defaultMin = null, $defaultMax = null)
    {
        $this->condition = 'dual_range';
        $this->selectedMin = $defaultMin ?? $this->min;
        $this->selectedMax = $defaultMax ?? $this->max;
    }

    public function dispatchEvent()
    {
        $this->dispatch('filter-updated',
            field: $this->field,
            condition: $this->condition,
            payload: [
                'min' => $this->selectedMin,
                'max' => $this->selectedMax,
            ],
            command: 'replace',
            modifier: $this->modifier,
        )
            ->to(LivewireCollection::class);
    }

    public function updatedSelectedMin($value)
    {
        // Ensure min doesn't exceed max - minRange
        if ($value > $this->selectedMax - $this->minRange) {
            $this->selectedMin = $this->selectedMax - $this->minRange;
        }
        $this->dispatchEvent();
    }

    public function updatedSelectedMax($value)
    {
        // Ensure max doesn't go below min + minRange
        if ($value < $this->selectedMin + $this->minRange) {
            $this->selectedMax = $this->selectedMin + $this->minRange;
        }
        $this->dispatchEvent();
    }

    // #[On('livewire:initialized')]
    // public function livewireComponentReady()
    // {
    //     $this->dispatchEvent();
    // }

    #[On('preset-params')]
    public function setPresetSort($params)
    {
        $paramKey = $this->getParamKey();
        if (isset($params[$paramKey]['min'])) {
            $this->selectedMin = $params[$paramKey]['min'];
        }
        if (isset($params[$paramKey]['max'])) {
            $this->selectedMax = $params[$paramKey]['max'];
        }
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.filters.'.$this->view);
    }
}
