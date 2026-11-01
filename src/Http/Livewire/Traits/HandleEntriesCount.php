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

    // Only the collection's encrypted query can scope counts; the plain params are client-controlled
    // and could probe hidden entries (e.g. `status:is any` with `title:contains`), so they are ignored.
    #[On('params-updated')]
    public function updateCounts($params, $boundsQuery = null)
    {
        if (! config('statamic-livewire-filters.enable_filter_values_count')) {
            return;
        }

        $params = RangeBoundsQuery::paramsForCollection($boundsQuery, $this->collection);

        if ($params === null) {
            return;
        }

        $fieldHandle = $this->resolveCountFieldHandle();

        $baseParams = $this->removeCurrentFieldFromParams($params, $fieldHandle);

        $this->updateCountsWithBatchQuery($baseParams, $fieldHandle, $baseParams['from']);

        $this->dispatch('counts-updated', $this->counts());
    }

    protected function updateCountsWithBatchQuery($baseParams, $fieldHandle, $collections = null)
    {
        $this->statamic_field['counts'] = array_fill_keys(array_keys($this->statamic_field['options']), 0);

        $fieldValues = $this->getCountFieldValues($baseParams, $fieldHandle, $collections);

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
