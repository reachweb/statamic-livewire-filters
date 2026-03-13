<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Reach\StatamicLivewireFilters\Support\CountQueryPool;
use Statamic\Entries\EntryCollection;

trait HandleEntriesCount
{
    #[Computed]
    public function counts()
    {
        if ($this->options !== null && is_array($this->options)) {
            return array_fill_keys(array_keys($this->options), null);
        }

        return $this->statamic_field['counts'];
    }

    #[On('params-updated')]
    public function updateCounts($params)
    {
        $fieldHandle = $this->statamic_field['handle'];

        $baseParams = $this->removeCurrentFieldFromParams($params, $fieldHandle);

        $previousCounts = $this->statamic_field['counts'];

        $this->updateCountsWithBatchQuery($baseParams, $fieldHandle);

        // Skip re-render when counts haven't changed (e.g. lazy mount
        // dispatching params that match the initial SSR computation).
        if ($previousCounts === $this->statamic_field['counts']) {
            $this->skipRender();
        }

        $this->dispatch('counts-updated', $this->counts());
    }

    protected function removeCurrentFieldFromParams($params, $fieldHandle)
    {
        $baseParams = [];

        foreach ($params as $key => $value) {
            // Check if this is a dual_range field (has min/max keys)
            if ($this->condition === 'dual_range') {
                // Skip both min and max keys for the current field
                if (str_starts_with($key, $fieldHandle.':')) {
                    continue;
                }
            }

            // Skip standard field parameters
            if (str_starts_with($key, $fieldHandle.':')) {
                continue;
            }

            // Skip taxonomy parameters
            if (str_starts_with($key, 'taxonomy:'.$fieldHandle)) {
                continue;
            }

            // Handle query_scope - skip the current field's scoped param
            if ($this->condition === 'query_scope' && $key === $this->modifier.':'.$fieldHandle) {
                continue;
            }

            $baseParams[$key] = $value;
        }

        // Clean up the query_scope param only if no remaining params use this scope
        if ($this->condition === 'query_scope' && isset($baseParams['query_scope'])) {
            $hasRemainingParams = collect($baseParams)->keys()->contains(
                fn ($key) => str_starts_with($key, $this->modifier.':')
            );

            if (! $hasRemainingParams) {
                $scopes = explode('|', $baseParams['query_scope']);
                $scopes = array_filter($scopes, fn ($scope) => $scope !== $this->modifier);

                if (empty($scopes)) {
                    unset($baseParams['query_scope']);
                } else {
                    $baseParams['query_scope'] = implode('|', $scopes);
                }
            }
        }

        return $baseParams;
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

    protected function getCountFieldValues(array $baseParams, string $fieldHandle)
    {
        return app(CountQueryPool::class)->getFieldValues($this->collection, $baseParams, $fieldHandle);
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
