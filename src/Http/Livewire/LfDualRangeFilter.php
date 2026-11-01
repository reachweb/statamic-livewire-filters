<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Carbon\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;

class LfDualRangeFilter extends LivewireFilter
{
    use Traits\HandleFieldBounds;

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
        // Displayed endpoints can clear only when both comparisons include all matches.
        if ($this->selectionSpansDisplayedBounds($this->selectedMin, $this->selectedMax)) {
            $this->clearFilters($this->rememberBoundsSelection([]));

            return;
        }

        if ($this->statamic_field['type'] === 'date') {
            $this->modifier = 'is_after|is_before';
        }

        $payload = $this->payload();
        $paramKeys = $this->getParamKey();
        $boundsSelection = $this->rememberBoundsSelection([$paramKeys['min'] => $payload['min'], $paramKeys['max'] => $payload['max']]);

        $this->dispatchFilterUpdated(['boundsSelection' => $boundsSelection]);
    }

    protected function payload(): mixed
    {
        if ($this->statamic_field['type'] === 'date') {
            return [
                'min' => Carbon::createFromDate($this->selectedMin)->startOfYear()->format('Y-m-d'),
                'max' => Carbon::createFromDate($this->selectedMax)->endOfYear()->format('Y-m-d'),
            ];
        }

        return ['min' => $this->selectedMin, 'max' => $this->selectedMax];
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
    public function clear(): void
    {
        $this->selectedMin = $this->min;
        $this->selectedMax = $this->max;

        // Reset to the last known bounds; on clear-all the relay may not trigger another params-updated.
        if ($this->dynamicMin !== null) {
            $this->pinSelectionToBounds($this->dynamicMin, $this->dynamicMax);
        }

        $boundsSelection = $this->rememberBoundsSelection([]);

        $this->dispatch('dual-range-preset-values',
            min: $this->selectedMin,
            max: $this->selectedMax,
        );

        $this->clearFilters($boundsSelection);
    }

    #[On('clear-option')]
    public function clearOption($tag)
    {
        if ($tag['field'] !== $this->field) {
            return;
        }

        if ($tag['value'] == $this->selectedMin) {
            $this->selectedMin = $this->min;
        }

        if ($tag['value'] == $this->selectedMax) {
            $this->selectedMax = $this->max;
        }

        $this->dispatchEvent();
        $this->dispatch('dual-range-preset-values',
            min: $this->selectedMin,
            max: $this->selectedMax,
        );
    }

    protected function applyPreset(array $params): void
    {
        $paramKeys = $this->getParamKey();
        if (isset($params[$paramKeys['min']])) {
            $this->selectedMin = $params[$paramKeys['min']];
        }
        if (isset($params[$paramKeys['max']])) {
            $this->selectedMax = $params[$paramKeys['max']];
        }
        $this->dispatch('dual-range-preset-values',
            min: $this->selectedMin,
            max: $this->selectedMax,
        );
    }

    protected function selectionSpansDisplayedBounds($min, $max): bool
    {
        if ($min == $this->min && $max == $this->max) {
            return true;
        }

        [$minComparison, $maxComparison] = $this->boundsComparisons();

        return $this->dynamicMin !== null
            && $min == $this->dynamicMin
            && $max == $this->dynamicMax
            && $this->boundIncludesAllValues($min, $minComparison)
            && $this->boundIncludesAllValues($max, $maxComparison);
    }

    protected function minimumBoundsSpan(): int|float
    {
        return $this->minRange;
    }

    protected function clampedSelectionPayload($min, $max): array
    {
        return [
            'selectedMin' => max($min, min($this->selectedMin, $max)),
            'selectedMax' => max($min, min($this->selectedMax, $max)),
        ];
    }

    protected function pinSelectionToBounds($min, $max): void
    {
        $this->selectedMin = $min;
        $this->selectedMax = $max;
    }

    protected function selectionEventPayload(): array
    {
        return [
            'selectedMin' => $this->selectedMin,
            'selectedMax' => $this->selectedMax,
        ];
    }
}
