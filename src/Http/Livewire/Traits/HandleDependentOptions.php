<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Reach\StatamicLivewireFilters\Support\ParamKey;
use Reach\StatamicLivewireFilters\Support\RangeBoundsQuery;

/**
 * Restricts an options filter, while the field named in `depends_on` has a
 * value, to the options found in the entries matching every other active filter.
 */
trait HandleDependentOptions
{
    use HandleEntriesCount;

    #[Locked]
    public ?string $depends_on = null;

    // `hide` drops unavailable options, `disable` renders them disabled.
    #[Locked]
    public string $unavailable = 'hide';

    // Option keys (as strings) found in the matching entries; null shows every option.
    #[Locked]
    public ?array $availableOptions = null;

    #[Locked]
    public ?string $parentValue = null;

    // The first `params-updated` only records the parent value, so a preset selection is never cleared on page load.
    #[Locked]
    public bool $parentValueRecorded = false;

    public function mountHandleDependentOptions(): void
    {
        if (! in_array($this->unavailable, ['hide', 'disable'], true)) {
            throw new \InvalidArgumentException("The unavailable parameter must be `hide` or `disable`, `{$this->unavailable}` given.");
        }
    }

    // Keeps HandleEntriesCount's name so it replaces that listener: Livewire binds one method per event.
    #[On('params-updated')]
    public function updateCounts($params, $boundsQuery = null)
    {
        $countsEnabled = config('statamic-livewire-filters.enable_filter_values_count');

        $params = ($countsEnabled || $this->depends_on !== null)
            ? RangeBoundsQuery::paramsForCollection($boundsQuery, $this->collection)
            : null;

        if ($params !== null && $this->depends_on !== null) {
            $this->refreshAvailableOptions($params);
        }

        if ($params !== null && $countsEnabled) {
            $this->refreshCounts($params);
            $this->markFilterStateChanged();
        }

        // The collection sends this event to every filter, so one with nothing to update skips its render.
        $this->skipRenderIfFilterStateUnchanged();
    }

    public function optionIsAvailable($value): bool
    {
        return $this->availableOptions === null
            || in_array((string) $value, $this->availableOptions, true)
            || in_array((string) $value, array_map('strval', (array) $this->selected), true);
    }

    protected function refreshAvailableOptions(array $params): void
    {
        $parentValue = $this->activeParentValue($params);
        $parentChanged = $this->parentValueRecorded && $parentValue !== $this->parentValue;
        $this->parentValue = $parentValue;
        $this->parentValueRecorded = true;

        $available = null;

        if ($parentValue !== null) {
            $fieldHandle = $this->resolveCountFieldHandle();
            $baseParams = $this->removeCurrentFieldFromParams($params, $fieldHandle);

            // The same query and cache key as the counts, so with counts on it costs no extra query.
            $fieldValues = $this->getCountFieldValues($baseParams, $fieldHandle, $baseParams['from']);

            $available = array_values(array_unique(array_map('strval', $this->optionValuesIn($fieldValues))));
        }

        if ($available !== $this->availableOptions) {
            $this->availableOptions = $available;
            $this->dispatch('available-options-updated', $available);
            $this->markFilterStateChanged();
        }

        if ($parentChanged && $available !== null) {
            $this->dropUnavailableSelection();
        }
    }

    protected function dropUnavailableSelection(): void
    {
        $selected = array_values(array_filter(array_map('strval', (array) $this->selected), fn ($value) => $value !== ''));
        $kept = array_values(array_intersect($selected, $this->availableOptions));

        if ($kept === $selected) {
            return;
        }

        if (! is_array($this->selected) || $kept === []) {
            $this->clear();

            return;
        }

        $this->selected = $kept;
        $this->updatedSelected();
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
