<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

class LfRangeFilter extends LivewireFilter
{
    use Traits\HandleFieldBounds;

    #[Locked]
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
        // A clamped default can clear only when its comparison includes all matches.
        if ($this->isAtClearValue()) {
            $this->clearFilters($this->rememberBoundsSelection([]));

            return;
        }

        $boundsSelection = $this->rememberBoundsSelection([$this->getParamKey() => $this->selected]);

        $this->dispatchFilterUpdated(['boundsSelection' => $boundsSelection]);
    }

    public function updatedSelected()
    {
        $this->markFilterStateChanged();
        $this->dispatchEvent();
    }

    protected function emptyValue(): mixed
    {
        return $this->default ?? $this->min;
    }

    #[On('clear-all-filters')]
    public function clear(): void
    {
        $this->selected = $this->emptyValue();
        $this->markFilterStateChanged();

        // Reset to the last known bounds; on clear-all the relay may not trigger another params-updated.
        if ($this->dynamicMin !== null) {
            $this->pinSelectionToBounds($this->dynamicMin, $this->dynamicMax);
        }

        if (! $this->clearAllNeedsRelay(func_get_args()[0] ?? null)) {
            $this->forgetBoundsSelectionWithoutRelay();

            return;
        }

        $this->clearFilters($this->rememberBoundsSelection([]));
    }

    #[On('clear-option')]
    public function clearOption($tag)
    {
        if ($tag['field'] !== $this->field) {
            $this->skipRenderIfFilterStateUnchanged();

            return;
        }

        $this->selected = $this->emptyValue();
        $this->markFilterStateChanged();
        $this->dispatchEvent();
    }

    protected function isAtClearValue(): bool
    {
        $default = $this->emptyValue();

        if ($this->selected == $default) {
            return true;
        }

        return $this->dynamicMin !== null
            && $this->selected == max($this->dynamicMin, min($default, $this->dynamicMax))
            && $this->boundIncludesAllValues($this->selected, $this->condition);
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
        $this->selected = max($min, min($this->emptyValue(), $max));
    }

    protected function selectionEventPayload(): array
    {
        return ['selected' => $this->selected];
    }
}
