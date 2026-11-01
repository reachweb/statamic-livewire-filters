<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

/**
 * The event protocol between a filter component and LivewireCollection.
 *
 * A filter consumes:
 *  - `clear-all-filters`: reset the selection; handled by clear().
 *  - `clear-option` with a tag `{field, value, condition}` from LfTags: drop
 *    that value when `field` matches; handled by clearOption().
 *  - `preset-params` with the collection's params keyed like getParamKey():
 *    restore the selection from the URL or tag; handled by setPresetValues().
 *  - `params-updated` with the collection's params: only filters that show
 *    counts or dynamic bounds listen (HandleEntriesCount, HandleFieldBounds).
 *
 * A filter emits to LivewireCollection:
 *  - `filter-updated` with `field`, `condition`, `payload` (string, list of
 *    strings, or `{min, max}` for dual_range; '', null or [] clears),
 *    `modifier`, and optionally `boundsSelection`.
 *  - `clear-filter` with `field`, `condition`, `modifier`, `boundsSelection`.
 */
interface FilterContract
{
    public function clear();

    public function clearOption($tag);

    public function setPresetValues($params);
}
