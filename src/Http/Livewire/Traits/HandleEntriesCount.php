<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Reach\StatamicLivewireFilters\Support\RangeBoundsQuery;
use Statamic\Entries\EntryCollection;

trait HandleEntriesCount
{
    use HandleFieldValuesQuery;

    #[Computed]
    public function counts()
    {
        if (isset($this->statamic_field['counts']) && is_array($this->statamic_field['counts'])) {
            return $this->statamic_field['counts'];
        }

        if ($this->options !== null && is_array($this->options)) {
            return array_fill_keys(array_keys($this->options), null);
        }

        return [];
    }

    /**
     * The collection sends an encrypted query token alongside the plain params.
     * When a token arrives, only its params are used, so a forged plain payload
     * (e.g. `status:is any`) cannot change the counts, while tag-authored control
     * params (status, site, ...) still scope the counts like the entries query.
     * A token that fails to verify for this filter's collection is ignored outright.
     * Without any token, the plain params are used unstripped (legacy trade-off kept
     * for backwards compatibility): a forged event can at most skew the forger's own
     * aggregate counts of this field's options, never expose entry data. Collection
     * switching stays blocked regardless: CountQueryPool forces the locked collection.
     */
    #[On('params-updated')]
    public function updateCounts($params, $countsQuery = null)
    {
        if (! config('statamic-livewire-filters.enable_filter_values_count')) {
            return;
        }

        if ($countsQuery !== null) {
            $params = RangeBoundsQuery::paramsForCollection($countsQuery, $this->collection);

            if ($params === null) {
                return;
            }
        }

        $fieldHandle = $this->resolveCountFieldHandle();

        $baseParams = $this->removeCurrentFieldFromParams($params, $fieldHandle);

        $this->updateCountsWithBatchQuery($baseParams, $fieldHandle);

        $this->dispatch('counts-updated', $this->counts());
    }

    protected function updateCountsWithBatchQuery($baseParams, $fieldHandle)
    {
        $this->statamic_field['counts'] = array_fill_keys(array_keys($this->statamic_field['options']), 0);

        $fieldValues = $this->getCountFieldValues($baseParams, $fieldHandle);

        if ($fieldValues->isEmpty()) {
            return;
        }

        foreach ($fieldValues as $fieldValue) {
            // Handle array values (entries, terms fields can be multi-select)
            if (is_array($fieldValue)) {
                foreach ($fieldValue as $value) {
                    if (isset($this->statamic_field['counts'][$value])) {
                        $this->statamic_field['counts'][$value]++;
                    }
                }
            } elseif (isset($this->statamic_field['counts'][$fieldValue])) {
                // Handle single values - combined condition for better performance
                $this->statamic_field['counts'][$fieldValue]++;
            }
        }
    }

    protected function getOptionParam($option)
    {
        if ($this->condition === 'query_scope') {
            return [
                'query_scope' => $this->modifier,
                $this->getParamKey() => $option,
            ];
        }

        return [$this->getParamKey() => $option];
    }

    protected function countAllEntries(array $entries): int
    {
        if (isset($entries['pagination_total'])) {
            return (int) $entries['pagination_total'];
        } elseif ($entries['entries'] instanceof EntryCollection) {
            return $entries['entries']->count();
        }

        return 0;
    }
}
