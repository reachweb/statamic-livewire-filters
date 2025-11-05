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
        $fieldType = $this->statamic_field['type'];

        // For fields that can have many options, use batch optimization
        if (in_array($fieldType, ['entries', 'terms', 'dictionary'])) {
            $this->updateCountsWithBatchQuery($params, $fieldHandle);
        } else {
            // For other field types (checkboxes, radio, select, etc.), use the standard approach
            $this->updateCountsStandard($params);
        }

        $this->dispatch('counts-updated', $this->counts());
    }

    protected function updateCountsStandard($params)
    {
        foreach (array_keys($this->statamic_field['options']) as $option) {
            $optionParams = array_merge($params, $this->getOptionParam($option));
            $this->statamic_field['counts'][$option] = (new Entries($this->generateParamsForCount($this->collection, $optionParams)))->count();
        }
    }

    protected function updateCountsWithBatchQuery($params, $fieldHandle)
    {
        // Get all entries matching the base params (without the current field filter)
        $baseParams = [];
        foreach ($params as $key => $value) {
            if (! str_starts_with($key, $fieldHandle.':')
                && ! str_starts_with($key, 'taxonomy:'.$fieldHandle)
                && ! ($this->condition === 'query_scope' && str_ends_with($key, ':'.$fieldHandle))) {
                $baseParams[$key] = $value;
            }
        }

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
