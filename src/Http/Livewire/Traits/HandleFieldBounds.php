<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Carbon\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

trait HandleFieldBounds
{
    use HandleFieldValuesQuery;

    #[Locked]
    public $dynamicMin = null;

    #[Locked]
    public $dynamicMax = null;

    #[Locked]
    public $boundsDisabled = false;

    abstract protected function minimumBoundsSpan(): int|float;

    abstract protected function clampedSelectionPayload($min, $max): array;

    abstract protected function pinSelectionToBounds($min, $max): void;

    abstract protected function selectionEventPayload(): array;

    /**
     * Blocked params are deliberately not stripped — a forged event can only
     * skew the forger's own displayed bounds (see HandleEntriesCount::updateCounts()).
     */
    #[On('params-updated')]
    public function updateBounds($params): void
    {
        if (! config('statamic-livewire-filters.enable_dynamic_range_bounds')) {
            return;
        }

        $fieldHandle = $this->resolveCountFieldHandle();

        $baseParams = $this->removeCurrentFieldFromParams($params, $fieldHandle);

        [$min, $max] = $this->computeFieldBounds($baseParams, $fieldHandle);

        // Tag-supplied bounds arrive as strings
        $absoluteMin = $this->normalizeBound($this->min);
        $absoluteMax = $this->normalizeBound($this->max);

        if ($min === null) {
            [$min, $max] = [$absoluteMin, $absoluteMax];
        } else {
            $min = max($absoluteMin, $this->roundDownToStep($min));
            $max = min($absoluteMax, $this->roundUpToStep($max));

            // An empty intersection inverts the clamps — fall back like no-matches
            if ($min > $max) {
                [$min, $max] = [$absoluteMin, $absoluteMax];
            }
        }

        $isActive = $this->fieldHasActiveParams($params);
        $minimumSpan = $this->minimumBoundsSpan();

        if ($isActive) {
            // An active range must stay adjustable: contain the selection, never disable
            [$min, $max] = $this->widenBoundsToContainSelection($min, $max, $absoluteMin, $absoluteMax, $params);
            $disabled = false;
        } else {
            $span = $max - $min;
            $disabled = $span <= 0 || $span < $minimumSpan;

            // noUiSlider rejects ranges narrower than its margin; widen server-side
            if ($disabled && $minimumSpan > 0) {
                [$min, $max] = $this->widenBoundsToMinimumSpan($min, $max, $minimumSpan);
            }
        }

        $this->dynamicMin = $min;
        $this->dynamicMax = $max;
        $this->boundsDisabled = $disabled;

        // Active selections are never mutated, inactive ones follow the bounds;
        // neither path dispatches filter-updated
        if ($isActive) {
            $selectionPayload = $this->clampedSelectionPayload($min, $max);
        } else {
            $this->pinSelectionToBounds($min, $max);
            $selectionPayload = $this->selectionEventPayload();
        }

        $this->dispatch('range-bounds-updated', ...array_merge(
            ['min' => $min, 'max' => $max],
            $selectionPayload,
            ['disabled' => $disabled],
        ));
    }

    /**
     * Exact own-key matching — a prefix would also match a scope named like the field.
     */
    protected function fieldHasActiveParams($params): bool
    {
        return collect($this->currentFilterParamKeys())
            ->contains(fn ($key) => array_key_exists($key, (array) $params));
    }

    protected function currentFilterParamKeys(): array
    {
        $paramKey = $this->getParamKey();

        return is_array($paramKey) ? array_values($paramKey) : [$paramKey];
    }

    /**
     * Union of the faceted bounds and the applied selection, or the absolutes
     * when even that leaves no room to move the handles.
     */
    protected function widenBoundsToContainSelection($min, $max, $absoluteMin, $absoluteMax, $params): array
    {
        $selection = $this->activeSelectionBounds($params);

        if ($selection === null) {
            return [$min, $max];
        }

        $min = $this->normalizeBound(max($absoluteMin, min($min, $selection[0])));
        $max = $this->normalizeBound(min($absoluteMax, max($max, $selection[1])));

        if (($max - $min) < max($this->minimumBoundsSpan(), $this->step ?: 1)) {
            return [$absoluteMin, $absoluteMax];
        }

        return [$min, $max];
    }

    /**
     * The applied selection in slider units, read from the params rather than
     * component state.
     */
    protected function activeSelectionBounds($params): ?array
    {
        $values = collect($this->currentFilterParamKeys())
            ->map(fn ($key) => $params[$key] ?? null)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => $this->boundValueFromParam($value))
            ->filter(fn ($value) => $value !== null);

        return $values->isEmpty() ? null : [$values->min(), $values->max()];
    }

    protected function boundValueFromParam($value): int|float|null
    {
        if (($this->statamic_field['type'] ?? null) === 'date') {
            try {
                return Carbon::parse($value)->year;
            } catch (\Throwable $exception) {
                return null;
            }
        }

        return is_numeric($value) ? $value + 0 : null;
    }

    protected function computeFieldBounds(array $baseParams, string $fieldHandle): array
    {
        $values = $this->getCountFieldValues($baseParams, $fieldHandle)
            ->flatten()
            ->filter(fn ($value) => $value !== null && $value !== '');

        if (($this->statamic_field['type'] ?? null) === 'date') {
            $values = $values->map(function ($value) {
                try {
                    return Carbon::parse($value)->year;
                } catch (\Throwable $exception) {
                    return null;
                }
            })->filter();
        } else {
            $values = $values->filter(fn ($value) => is_numeric($value))->map(fn ($value) => $value + 0);
        }

        return $values->isEmpty() ? [null, null] : [$values->min(), $values->max()];
    }

    /**
     * Anchored at the absolute min so bounds land on noUiSlider's snap grid.
     */
    protected function roundDownToStep($value): int|float
    {
        $step = $this->step ?: 1;

        return $this->normalizeBound($this->min + floor(($value - $this->min) / $step) * $step);
    }

    protected function roundUpToStep($value): int|float
    {
        $step = $this->step ?: 1;

        return $this->normalizeBound($this->min + ceil(($value - $this->min) / $step) * $step);
    }

    protected function normalizeBound($value): int|float
    {
        return fmod((float) $value, 1.0) === 0.0 ? (int) $value : $value;
    }

    protected function widenBoundsToMinimumSpan($min, $max, $minimumSpan): array
    {
        $max = $this->normalizeBound(min($this->max, $min + $minimumSpan));

        if (($max - $min) < $minimumSpan) {
            $min = $this->normalizeBound(max($this->min, $max - $minimumSpan));
        }

        return [$min, $max];
    }
}
