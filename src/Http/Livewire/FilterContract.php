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
 *    sent once at mount for params the URL doesn't carry, such as tag presets:
 *    restore the selection; handled by setPresetValues(). URL params are
 *    applied at mount instead (IsLivewireFilter calls setPresetValues() with
 *    CustomQueryString::urlParams()), so the first render shows them.
 *  - `params-updated` with `(params, boundsQuery)`: only filters that show
 *    counts or dynamic bounds listen (HandleEntriesCount, HandleFieldBounds).
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
