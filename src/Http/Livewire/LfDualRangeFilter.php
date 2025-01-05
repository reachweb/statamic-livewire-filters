<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Carbon\Carbon;
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
    public $format = 'integer';

    public function mount()
    {
        $this->condition = 'dual_range';
        $this->selectedMin = $this->min;
        $this->selectedMax = $this->max;
    }

    public function dispatchEvent()
    {
        $min = $this->selectedMin;
        $max = $this->selectedMax;

        if ($this->statamic_field['type'] === 'date') {
            $min = Carbon::createFromDate($this->selectedMin)->startOfYear()->format('Y-m-d');
            $max = Carbon::createFromDate($this->selectedMax)->endOfYear()->format('Y-m-d');
            $this->modifier = 'is_after|is_before';
        }

        $this->dispatch(
            'filter-updated',
            field: $this->field,
            condition: $this->condition,
            payload: [
                'min' => $min,
                'max' => $max,
            ],
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

    #[On('clear-all-filters')]
    public function clear()
    {
        $this->selectedMin = $this->min;
        $this->selectedMax = $this->max;
        $this->clearFilters();
    }

    #[On('preset-params')]
    public function setPresetValues($params)
    {
        $paramKeys = $this->getParamKey();
        if (isset($params[$paramKeys['min']])) {
            $this->selectedMin = $params[$paramKeys['min']];
        }
        if (isset($params[$paramKeys['max']])) {
            $this->selectedMax = $params[$paramKeys['max']];
        }
        $this->dispatch(
            'dual-range-preset-values',
            min: $this->selectedMin,
            max: $this->selectedMax,
        );
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.filters.' . $this->view);
    }
}
