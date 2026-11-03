<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

/**
 * The event protocol between a filter component and LivewireCollection.
 *
 * A filter consumes:
 *  - `clear-all-filters`: reset the selection; handled by clear().
 *  - `clear-option` with a tag `{field, value, condition}` from LfTags: drop
 *    that value when `field` matches; handled by clearOption().
 *  - `preset-params` with the collection's params keyed like getParamKey(),
 *    sent once when they come from the collection tag or the URL: restore the
 *    selection; handled by setPresetValues().
 *  - `params-updated` with `(params, boundsQuery)`: only filters that show
 *    counts, dynamic bounds or dependent options listen (HandleEntriesCount,
 *    HandleFieldBounds, HandleDependentOptions).
 *    They ignore the plain `params`, which a client controls, and read the
 *    filter state only from the encrypted `boundsQuery` (RangeBoundsQuery).
 *
 * A filter emits to LivewireCollection:
 *  - `filter-updated` with `field`, `condition`, `payload` (a scalar, a list,
 *    or `{min, max}` for dual_range; '', null or [] clears),
 *    `modifier`, and optionally `boundsSelection`.
 *  - `clear-filter` with `field`, `condition`, `modifier`, `boundsSelection`.
 */
interface FilterContract
{
    public function clear();

    public function clearOption($tag);

    public function setPresetValues($params);
}
