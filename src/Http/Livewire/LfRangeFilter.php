<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class LfRangeFilter extends Component
{
    use Traits\HandleFieldBounds, Traits\IsLivewireFilter;

    public $view = 'lf-range';

    public $selected;

    #[Locked]
    public $min;

    #[Locked]
    public $max;

    #[Locked]
    public $default;

    public $step = 1;

    public function mount($default = null)
    {
        $this->selected = $default ?? $this->min;
    }

    public function dispatchEvent()
    {
        // Clear the filter if we are back to default (clamped into the displayed bounds)
        if ($this->isAtClearValue()) {
            $this->clearFilters();

            return;
        }

        $this->dispatch('filter-updated',
            field: $this->field,
            condition: $this->condition,
            payload: $this->selected,
            modifier: null,
        )
            ->to(LivewireCollection::class);
    }

    public function updatedSelected()
    {
        $this->dispatchEvent();
    }

    #[On('preset-params')]
    public function setPresetValues($params)
    {
        if (array_key_exists($this->getParamKey(), $params)) {
            $this->selected = $params[$this->getParamKey()];
        }
    }

    #[On('clear-all-filters')]
    public function clear(): void
    {
        $this->selected = $this->default ?? $this->min;

        $this->clearFilters();
    }

    #[On('clear-option')]
    public function clearOption($tag)
    {
        if ($tag['field'] !== $this->field) {
            return;
        }

        $this->selected = $this->default ?? $this->min;
        $this->dispatchEvent();
    }

    protected function isAtClearValue(): bool
    {
        $default = $this->default ?? $this->min;

        if ($this->selected == $default) {
            return true;
        }

        return $this->dynamicMin !== null
            && $this->selected == max($this->dynamicMin, min($default, $this->dynamicMax));
    }

    protected function minimumBoundsSpan(): int|float
    {
        return 0;
    }

    protected function clampedSelectionPayload($min, $max): array
    {
        return ['selected' => max($min, min($this->selected, $max))];
    }

    protected function pinSelectionToBounds($min, $max): void
    {
        $this->selected = max($min, min($this->default ?? $this->min, $max));
    }

    protected function selectionEventPayload(): array
    {
        return ['selected' => $this->selected];
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.filters.'.$this->view);
    }
}
