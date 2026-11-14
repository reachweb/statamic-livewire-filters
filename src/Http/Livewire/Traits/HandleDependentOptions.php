<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Reach\StatamicLivewireFilters\Exceptions\InvalidDependsOnException;
use Reach\StatamicLivewireFilters\Exceptions\InvalidUnavailableModeException;
use Reach\StatamicLivewireFilters\Support\ParamKey;
use Reach\StatamicLivewireFilters\Support\RangeBoundsQuery;

/**
 * Restricts an options filter, while a field named in `depends_on` has a
 * value, to the options found in the entries matching every other active filter.
 */
trait HandleDependentOptions
{
    use HandleEntriesCount;

    /** The parent field, or several separated by `|`, such as `car_brand|car_model` for the last filter of a chain. */
    #[Locked]
    public ?string $depends_on = null;

    /** How options the parents' values don't offer render: `hide` or `disable`. */
    #[Locked]
    public string $unavailable = 'hide';

    /** Option keys (as strings) found in the matching entries; null shows every option. */
    #[Locked]
    public ?array $availableOptions = null;

    #[Locked]
    public ?string $parentValue = null;

    /** The first `params-updated` only records the parent value, so a preset selection is never cleared on page load. */
    #[Locked]
    public bool $parentValueRecorded = false;

    public function mountHandleDependentOptions(): void
    {
        if (! in_array($this->unavailable, ['hide', 'disable'], true)) {
            throw new InvalidUnavailableModeException($this->unavailable);
        }

        // A range list's options are buckets, not field values, so there is nothing to look up in the entries.
        if ($this->parentFields() !== [] && $this->rangeBuckets() !== []) {
            throw new InvalidDependsOnException('A range list (`buckets`) cannot use depends_on.');
        }
    }

    // Keeps HandleEntriesCount's name so it replaces that listener: Livewire binds one method per event.
    #[On('params-updated')]
    public function updateCounts($params, $boundsQuery = null)
    {
        $countsEnabled = config('statamic-livewire-filters.enable_filter_values_count');
        $dependent = $this->parentFields() !== [];

        $query = ($countsEnabled || $dependent)
            ? RangeBoundsQuery::decodeForCollection($boundsQuery, $this->collection)
            : null;

        if ($query === null) {
            $this->skipRenderIfFilterStateUnchanged();

            return;
        }

        if ($dependent) {
            $this->refreshAvailableOptions($query['params'], $query['filters']);
        }

        if ($countsEnabled) {
            $this->refreshCounts($query['params']);

            return;
        }

        $this->skipRenderIfFilterStateUnchanged();
    }

    /**
     * Selected options stay usable, like the zero counts: a visitor must be able
     * to see and uncheck an option the other filters narrowed away.
     */
    protected function unavailableOptions(string $mode): array
    {
        if ($this->availableOptions === null || $this->unavailable !== $mode) {
            return [];
        }

        $usable = [...$this->availableOptions, ...$this->selectedOptionKeys()];

        return array_values(array_filter(
            array_keys($this->filter_options),
            fn ($value) => ! in_array((string) $value, $usable, true),
        ));
    }

    /**
     * @param  array<string, mixed>  $params  The collection's params, from its encrypted query.
     * @param  array<int, string>  $filterKeys  The keys in them that hold visitor filter state.
     */
    protected function refreshAvailableOptions(array $params, array $filterKeys): void
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
            $available = $this->optionKeysInEntries($baseParams, $fieldHandle);
        }

        if ($available !== $this->availableOptions) {
            $this->availableOptions = $available;
            // The Alpine views never re-render, so they follow this event.
            $this->dispatch('available-options-updated', $available);
            $this->markFilterStateChanged();
        }

        if ($parentChanged && $available !== null) {
            $this->dropSelectionTheParentLacks($baseParams, $fieldHandle, $filterKeys);
        }
    }

    /**
     * The other filters narrow the options too, but only the parents unselect: a
     * selected option goes when no entry has it with the parents' values alone.
     */
    protected function dropSelectionTheParentLacks(array $baseParams, string $fieldHandle, array $filterKeys): void
    {
        $selected = array_values(array_filter($this->selectedOptionKeys(), fn ($value) => $value !== ''));
        $unavailable = array_diff($selected, $this->availableOptions);

        if ($unavailable === []) {
            return;
        }

        // With no other filter active this is the query above, so the pool answers it from its cache.
        $offered = $this->optionKeysInEntries($this->withoutOtherFilters($baseParams, $filterKeys), $fieldHandle);
        $kept = array_values(array_diff($selected, array_diff($unavailable, $offered)));

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
     * Removes the visitor's other filters and keeps everything else: the parents'
     * values and the constraints of the tag and its hooks, which decide what a
     * visitor may see at all.
     *
     * @param  array<string, mixed>  $baseParams  The params without this filter's own field.
     * @param  array<int, string>  $filterKeys
     * @return array<string, mixed>
     */
    protected function withoutOtherFilters(array $baseParams, array $filterKeys): array
    {
        $scopes = $this->activeScopeNames($baseParams);
        $parents = $this->parentFields();

        foreach ($filterKeys as $key) {
            if (! array_key_exists($key, $baseParams)) {
                continue;
            }

            $paramKey = ParamKey::parse($key, $scopes);

            if (in_array($paramKey->field, $parents, true)) {
                continue;
            }

            unset($baseParams[$key]);

            // Like the collection when the last param of a scope is cleared.
            if ($paramKey->condition === 'query_scope') {
                $baseParams = $this->removeScopeRegistrationIfUnused($baseParams, $paramKey->modifier);
            }
        }

        return $baseParams;
    }

    /**
     * @return list<string> The option keys found in the entries matching the params.
     */
    protected function optionKeysInEntries(array $baseParams, string $fieldHandle): array
    {
        $fieldValues = $this->getCountFieldValues($baseParams, $fieldHandle, $baseParams['from']);

        return array_values(array_unique(array_map('strval', $this->optionValuesIn($fieldValues))));
    }

    /**
     * @return list<string>
     */
    protected function parentFields(): array
    {
        return array_values(array_filter(array_map('trim', explode('|', (string) $this->depends_on)), fn ($field) => $field !== ''));
    }

    /**
     * The parents' active values as a stable signature, or null when none has a value.
     */
    protected function activeParentValue(array $params): ?string
    {
        $scopes = $this->activeScopeNames($params);
        $parents = $this->parentFields();

        $values = collect($params)
            ->flatMap(function ($value, $key) use ($scopes, $parents) {
                $field = ParamKey::parse((string) $key, $scopes)->field;

                if (! in_array($field, $parents, true)) {
                    return [];
                }

                return collect(is_string($value) ? explode('|', $value) : (array) $value)
                    ->filter(fn ($value) => is_scalar($value) && (string) $value !== '')
                    ->map(fn ($value) => $field.'='.$value)
                    ->values();
            })
            ->unique()
            ->sort()
            ->values();

        return $values->isEmpty() ? null : $values->implode('|');
    }
}
