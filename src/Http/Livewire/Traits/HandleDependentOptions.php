<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Reach\StatamicLivewireFilters\Support\ParamKey;
use Reach\StatamicLivewireFilters\Support\RangeBoundsQuery;

/**
 * Restricts an options filter to the values that co-occur with the active value
 * of the field named in `depends_on`. Livewire binds one method per event, so
 * this trait's listener replaces HandleEntriesCount's and refreshes both.
 */
trait HandleDependentOptions
{
    use HandleEntriesCount;

    #[Locked]
    public ?string $depends_on = null;

    // `hide` drops unavailable options, `disable` renders them disabled.
    #[Locked]
    public string $unavailable = 'hide';

    // Option keys (as strings) that co-occur with the parent value; null shows every option.
    #[Locked]
    public ?array $availableOptions = null;

    #[Locked]
    public ?string $parentValue = null;

    #[On('params-updated')]
    public function updateCounts($params, $boundsQuery = null)
    {
        $countsEnabled = config('statamic-livewire-filters.enable_filter_values_count');

        $params = ($countsEnabled || $this->depends_on !== null)
            ? RangeBoundsQuery::paramsForCollection($boundsQuery, $this->collection)
            : null;

        // The collection sends this event to every filter, so one with nothing to update skips its render.
        $changed = $params !== null && $this->depends_on !== null && $this->refreshAvailableOptions($params);

        if ($params !== null && $countsEnabled) {
            $this->refreshCounts($params);
        } elseif (! $changed) {
            $this->skipRender();
        }
    }

    public function optionIsAvailable($value): bool
    {
        return $this->availableOptions === null
            || in_array((string) $value, $this->availableOptions, true)
            || in_array((string) $value, array_map('strval', (array) $this->selected), true);
    }

    /**
     * @return bool Whether the available options or the selection changed.
     */
    protected function refreshAvailableOptions(array $params): bool
    {
        $parentValue = $this->activeParentValue($params);
        $parentChanged = $parentValue !== $this->parentValue;
        $this->parentValue = $parentValue;

        $available = null;

        if ($parentValue !== null) {
            $fieldHandle = $this->resolveCountFieldHandle();
            $baseParams = $this->removeCurrentFieldFromParams($params, $fieldHandle);

            // The same query and cache key as the counts, so with counts on it costs no extra query.
            $fieldValues = $this->getCountFieldValues($baseParams, $fieldHandle, $baseParams['from']);

            $available = array_values(array_unique(array_map('strval', $this->optionValuesIn($fieldValues))));
        }

        $optionsChanged = $available !== $this->availableOptions;

        if ($optionsChanged) {
            $this->availableOptions = $available;
            $this->dispatch('available-options-updated', $available);
        }

        $selectionChanged = $parentChanged && $available !== null && $this->dropUnavailableSelection();

        return $optionsChanged || $selectionChanged;
    }

    protected function dropUnavailableSelection(): bool
    {
        $selected = array_map('strval', (array) $this->selected);
        $kept = array_values(array_intersect($selected, $this->availableOptions));

        if ($kept === $selected) {
            return false;
        }

        if (! is_array($this->selected) || $kept === []) {
            $this->clear();
        } else {
            $this->selected = $kept;
            $this->updatedSelected();
        }

        return true;
    }

    /**
     * The parent's active values as a stable signature, or null when it has none.
     */
    protected function activeParentValue(array $params): ?string
    {
        $scopes = $this->activeScopeNames($params);

        $values = collect($params)
            ->filter(fn ($value, $key) => ParamKey::parse((string) $key, $scopes)->field === $this->depends_on)
            ->flatMap(fn ($value) => is_string($value) ? explode('|', $value) : (array) $value)
            ->map(fn ($value) => (string) $value)
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->sort()
            ->values();

        return $values->isEmpty() ? null : $values->implode('|');
    }
}
