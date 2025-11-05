<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Statamic\Entries\EntryCollection;
use Statamic\Tags\Collection\Entries;

trait HandleEntriesCount
{
    use GenerateParams;

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

        $this->updateCountsWithBatchQuery($baseParams, $fieldHandle);

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

            // Handle query_scope - need to check if this field uses query_scope
            if ($this->condition === 'query_scope' && str_ends_with($key, ':'.$fieldHandle)) {
                // Also need to clean up the query_scope parameter if this was the only scope
                if ($key === 'query_scope') {
                    // Remove the modifier from the pipe-separated list
                    $scopes = explode('|', $value);
                    $scopes = array_filter($scopes, fn ($scope) => $scope !== $this->modifier);

                    if (empty($scopes)) {
                        continue; // Skip the query_scope key entirely if no scopes left
                    }

                    $baseParams[$key] = implode('|', $scopes);

                    continue;
                }

                continue;
            }

            $baseParams[$key] = $value;
        }

        return $baseParams;
    }

    protected function updateCountsWithBatchQuery($baseParams, $fieldHandle)
    {

        $entries = (new Entries($this->generateParamsForCount($this->collection, $baseParams)))->get();

        $this->statamic_field['counts'] = array_fill_keys(array_keys($this->statamic_field['options']), 0);

        // If no entries, return
        if (! $entries instanceof EntryCollection || $entries->isEmpty()) {
            return;
        }

        foreach ($entries as $entry) {
            $fieldValue = $entry->get($fieldHandle);

            if ($fieldValue === null) {
                continue;
            }

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
