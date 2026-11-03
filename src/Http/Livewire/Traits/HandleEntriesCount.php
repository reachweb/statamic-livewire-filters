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

        $this->refreshCounts($params);
    }

    protected function refreshCounts(array $params): void
    {
        $fieldHandle = $this->resolveCountFieldHandle();

        $baseParams = $this->removeCurrentFieldFromParams($params, $fieldHandle);

        $this->updateCountsWithBatchQuery($baseParams, $fieldHandle, $baseParams['from']);

        $this->dispatch('counts-updated', $this->counts());
    }

    protected function updateCountsWithBatchQuery($baseParams, $fieldHandle, $collections = null)
    {
        $this->statamic_field['counts'] = array_fill_keys(array_keys($this->statamic_field['options']), 0);

        foreach ($this->optionValuesIn($this->getCountFieldValues($baseParams, $fieldHandle, $collections)) as $value) {
            $this->statamic_field['counts'][$value]++;
        }
    }

    /**
     * The option keys found in the plucked field values, once per occurrence.
     */
    protected function optionValuesIn($fieldValues): array
    {
        $values = [];
        $singleTaxonomyPrefix = $this->singleTaxonomyPrefix();

        foreach ($fieldValues as $fieldValue) {
            // Multi-select values (checkboxes, entries, terms) are flattened one level; nested
            // arrays and objects (e.g. Carbon dates) are skipped, as such offsets throw in isset()
            foreach (is_array($fieldValue) ? $fieldValue : [$fieldValue] as $value) {
                // Single-taxonomy options use bare slugs, but entries may store "taxonomy::slug"
                if ($singleTaxonomyPrefix && is_string($value) && str_starts_with($value, $singleTaxonomyPrefix)) {
                    $value = substr($value, strlen($singleTaxonomyPrefix));
                }

                if (is_scalar($value) && array_key_exists($value, $this->statamic_field['options'])) {
                    $values[] = $value;
                }
            }
        }

        return $values;
    }

    protected function singleTaxonomyPrefix(): ?string
    {
        $taxonomies = (array) ($this->statamic_field['taxonomies'] ?? []);

        if (($this->statamic_field['type'] ?? null) !== 'terms' || count($taxonomies) !== 1) {
            return null;
        }

        return reset($taxonomies).'::';
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
