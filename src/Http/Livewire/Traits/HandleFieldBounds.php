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
     * Blocked params are intentionally NOT stripped here (deliberate trade-off,
     * mirroring HandleEntriesCount::updateCounts()). This path exposes only the
     * aggregate min/max of this field's own values — a forged `params-updated`
     * event can at most skew the forger's own displayed slider bounds, never
     * entry data. Collection switching stays blocked regardless: CountQueryPool
     * forces the component's locked collection to win the `from` merge.
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

        // Tag-supplied bounds arrive as strings; normalize so the browser event carries numbers
        $absoluteMin = $this->normalizeBound($this->min);
        $absoluteMax = $this->normalizeBound($this->max);

        if ($min === null) {
            [$min, $max] = [$absoluteMin, $absoluteMax];
        } else {
            $min = max($absoluteMin, $this->roundDownToStep($min));
            $max = min($absoluteMax, $this->roundUpToStep($max));

            // When every match lies outside the configured interval the two clamps
            // above cross over (absolutes 10–20 with data topping out at 5 yield
            // 10–5), so treat the empty intersection like the no-matches case.
            if ($min > $max) {
                [$min, $max] = [$absoluteMin, $absoluteMax];
            }
        }

        $isActive = $this->fieldHasActiveParams($params);
        $minimumSpan = $this->minimumBoundsSpan();

        if ($isActive) {
            // An active range must stay adjustable: bounds are widened to contain
            // the applied selection so the display never contradicts the server
            // query, the noUiSlider margin always fits, and the control is never
            // disabled — otherwise a collapse caused by another filter could
            // strand the user on zero results with a dead slider.
            [$min, $max] = $this->widenBoundsToContainSelection($min, $max, $absoluteMin, $absoluteMax, $params);
            $disabled = false;
        } else {
            $span = $max - $min;
            $disabled = $span <= 0 || $span < $minimumSpan;

            // The dual-range slider is created with `margin: minRange` and must never
            // receive a range narrower than that, so widen disabled spans server-side.
            if ($disabled && $minimumSpan > 0) {
                [$min, $max] = $this->widenBoundsToMinimumSpan($min, $max, $minimumSpan);
            }
        }

        $this->dynamicMin = $min;
        $this->dynamicMax = $max;
        $this->boundsDisabled = $disabled;

        // An active selection is never mutated — only its display is clamped into
        // the new bounds (an identity once the bounds contain it), so the handles
        // spring back once the bounds re-expand. An inactive selection follows the
        // bounds so the handles never suggest a filter that isn't there. Neither
        // path dispatches filter-updated.
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
     * Matches this filter's own generated param keys exactly — a bare
     * `{fieldHandle}:` prefix would also match another filter's scoped param
     * when a query scope shares the field's name (`price:category`).
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
     * The union of the faceted bounds and the applied selection, capped at the
     * configured absolutes. When even that leaves no room to move the handles,
     * the full configured range is restored so the filter can still be changed
     * or cleared.
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
     * The applied selection in slider units, read from the params themselves
     * (the source of truth) rather than component state, which a forged or
     * out-of-sync event would not reflect.
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
     * Rounding is anchored at the absolute $min so bounds land on the same grid
     * noUiSlider snaps its handles to.
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
