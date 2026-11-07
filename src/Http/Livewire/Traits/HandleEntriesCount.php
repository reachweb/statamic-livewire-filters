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

        if ($buckets = $this->rangeBuckets()) {
            $this->countFieldValuesIntoBuckets($fieldValues, $buckets);

            return;
        }

        $singleTaxonomyPrefix = $this->singleTaxonomyPrefix();

        foreach ($fieldValues as $fieldValue) {
            // Multi-select values (checkboxes, entries, terms) are flattened one level; nested
            // arrays and objects (e.g. Carbon dates) are skipped, as such offsets throw in isset()
            foreach (is_array($fieldValue) ? $fieldValue : [$fieldValue] as $value) {
                // Single-taxonomy options use bare slugs, but entries may store "taxonomy::slug"
                if ($singleTaxonomyPrefix && is_string($value) && str_starts_with($value, $singleTaxonomyPrefix)) {
                    $value = substr($value, strlen($singleTaxonomyPrefix));
                }

                if (is_scalar($value) && isset($this->statamic_field['counts'][$value])) {
                    $this->statamic_field['counts'][$value]++;
                }
            }
        }
    }

    /**
     * Range list filters override this with their buckets, keyed by option.
     *
     * @return array<string, array{min: ?string, max: ?string}>
     */
    protected function rangeBuckets(): array
    {
        return [];
    }

    /**
     * Buckets may overlap (`0-100|100-200` with gte/lte both hold 100), so a value
     * counts toward every bucket whose comparisons it passes, like the entries query.
     */
    protected function countFieldValuesIntoBuckets($fieldValues, array $buckets): void
    {
        [$minComparison, $maxComparison] = $this->modifier === null || $this->modifier === 'any'
            ? ['gte', 'lte']
            : explode('|', $this->modifier);

        $passes = fn ($value, $comparison, $bound) => match ($comparison) {
            'gt' => $value > $bound,
            'gte' => $value >= $bound,
            'lt' => $value < $bound,
            'lte' => $value <= $bound,
        };

        foreach ($fieldValues as $value) {
            if (! is_numeric($value)) {
                continue;
            }

            foreach ($buckets as $key => $bucket) {
                // Uncast, like Statamic's own where() comparisons, so counts match the entries query.
                if (($bucket['min'] === null || $passes($value, $minComparison, $bucket['min']))
                    && ($bucket['max'] === null || $passes($value, $maxComparison, $bucket['max']))) {
                    $this->statamic_field['counts'][$key]++;
                }
            }
        }
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
