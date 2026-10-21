<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Carbon\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Reach\StatamicLivewireFilters\Support\CountQueryPool;
use Reach\StatamicLivewireFilters\Support\RangeBoundsQuery;

trait HandleFieldBounds
{
    use HandleFieldValuesQuery;

    #[Locked]
    public $dynamicMin = null;

    #[Locked]
    public $dynamicMax = null;

    #[Locked]
    public $boundsDisabled = false;

    // Unrounded extrema distinguish strict endpoint predicates from a clear selection.
    #[Locked]
    public $fieldMin = null;

    #[Locked]
    public $fieldMax = null;

    // Keep local changes authoritative until the collection acknowledges them.
    #[Locked]
    public ?array $pendingBoundsSelection = null;

    #[Locked]
    public int $boundsSelectionRevision = 0;

    #[Locked]
    public array $boundsQueryRevisions = [];

    abstract protected function minimumBoundsSpan(): int|float;

    abstract protected function clampedSelectionPayload($min, $max): array;

    abstract protected function pinSelectionToBounds($min, $max): void;

    abstract protected function selectionEventPayload(): array;

    #[On('params-updated')]
    public function updateBounds($params, $boundsQuery = null): void
    {
        if (! config('statamic-livewire-filters.enable_dynamic_range_bounds')) {
            return;
        }

        // Only the collection's authenticated query can authorize visibility params;
        // the event's plain params are client-controlled, even on an otherwise valid event.
        $query = RangeBoundsQuery::decodeForCollection($boundsQuery, $this->collection);

        if ($query === null) {
            return;
        }

        if ($query['source'] !== null) {
            if ($query['revision'] < ($this->boundsQueryRevisions[$query['source']] ?? 0)) {
                return;
            }

            $this->boundsQueryRevisions[$query['source']] = $query['revision'];
        }

        $params = $query['params'];
        $fieldHandle = $this->resolveCountFieldHandle();

        $baseParams = $this->removeCurrentFieldFromParams($params, $fieldHandle);

        [$min, $max] = $this->computeFieldBounds($baseParams, $fieldHandle);

        // Tag-supplied bounds arrive as strings
        $absoluteMin = $this->normalizeBound($this->min);
        $absoluteMax = $this->normalizeBound($this->max);

        if ($min === null) {
            [$min, $max] = [$absoluteMin, $absoluteMax];
        } else {
            $min = $this->roundDownToStep($min);
            $max = $this->roundUpToStep($max);

            // Strict comparisons need a grid point beyond the matching values.
            [$minComparison, $maxComparison] = $this->boundsComparisons();
            if (in_array($minComparison, ['gt', 'greater_than', 'is_after'], true)
                && ! $this->boundIncludesAllValues($min, $minComparison)) {
                $min -= $this->step ?: 1;
            }
            if (in_array($maxComparison, ['lt', 'less_than', 'is_before'], true)
                && ! $this->boundIncludesAllValues($max, $maxComparison)) {
                $max += $this->step ?: 1;
            }

            $min = max($absoluteMin, $min);
            $max = min($absoluteMax, $max);

            // An empty intersection inverts the clamps — fall back like no-matches
            if ($min > $max) {
                [$min, $max] = [$absoluteMin, $absoluteMax];
            }
        }

        $selectionParams = $this->boundsSelectionParams($params, $query['selections']);
        $isActive = $this->fieldHasActiveParams($selectionParams);
        $minimumSpan = $this->minimumBoundsSpan();

        if ($isActive) {
            // An active range must stay adjustable: contain the selection, never disable
            [$min, $max] = $this->widenBoundsToContainSelection($min, $max, $absoluteMin, $absoluteMax, $selectionParams);
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

    protected function rememberBoundsSelection(array $params): ?array
    {
        if (! config('statamic-livewire-filters.enable_dynamic_range_bounds')) {
            return null;
        }

        $this->pendingBoundsSelection = $params;

        return ['source' => $this->getId(), 'revision' => ++$this->boundsSelectionRevision];
    }

    protected function boundsSelectionParams(array $params, array $selections): array
    {
        if ($this->pendingBoundsSelection === null) {
            return $params;
        }

        $appliedSelection = array_intersect_key($params, array_flip($this->currentFilterParamKeys()));

        // Equal values may belong to an older selection before a clear/reselect.
        if (($selections[$this->getId()] ?? null) === $this->boundsSelectionRevision
            && $appliedSelection == $this->pendingBoundsSelection) {
            $this->pendingBoundsSelection = null;

            return $params;
        }

        // Only selection handling uses these values; queries still use authenticated params.
        return $this->pendingBoundsSelection;
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
        $values = app(CountQueryPool::class)->getFieldValues($baseParams['from'], $baseParams, $fieldHandle)
            ->flatten()
            ->filter(fn ($value) => $value !== null && $value !== '');

        if (($this->statamic_field['type'] ?? null) === 'date') {
            $values = $values->map(function ($value) {
                try {
                    return Carbon::parse($value)->timestamp;
                } catch (\Throwable $exception) {
                    return null;
                }
            })->filter(fn ($value) => $value !== null);
        } else {
            $values = $values->filter(fn ($value) => is_numeric($value))->map(fn ($value) => $value + 0);
        }

        $this->fieldMin = $values->min();
        $this->fieldMax = $values->max();

        if ($this->fieldMin !== null && ($this->statamic_field['type'] ?? null) === 'date') {
            return [
                Carbon::createFromTimestamp($this->fieldMin, date_default_timezone_get())->year,
                Carbon::createFromTimestamp($this->fieldMax, date_default_timezone_get())->year,
            ];
        }

        return [$this->fieldMin, $this->fieldMax];
    }

    protected function boundsComparisons(): array
    {
        if ($this->condition !== 'dual_range') {
            return [$this->condition, $this->condition];
        }

        if (($this->statamic_field['type'] ?? null) === 'date') {
            return ['is_after', 'is_before'];
        }

        return $this->modifier === 'any' ? ['gte', 'lte'] : explode('|', $this->modifier);
    }

    protected function boundIncludesAllValues($value, string $comparison): bool
    {
        if ($this->fieldMin === null) {
            return false;
        }

        return match ($comparison) {
            'gt', 'greater_than' => $value < $this->fieldMin,
            'gte', 'greater_than_or_equal_to' => $value <= $this->fieldMin,
            'lt', 'less_than' => $value > $this->fieldMax,
            'lte', 'less_than_or_equal_to' => $value >= $this->fieldMax,
            'is_after' => Carbon::createFromDate($value)->startOfYear()->timestamp < $this->fieldMin,
            'is_before' => Carbon::createFromDate($value)->endOfYear()->startOfDay()->timestamp > $this->fieldMax,
            default => false,
        };
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
