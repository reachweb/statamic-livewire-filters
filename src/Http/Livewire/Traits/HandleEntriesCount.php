<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
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
     * Blocked params are intentionally NOT stripped here (deliberate trade-off).
     * This path exposes only aggregate counts of this field's own option values —
     * CountEntries plucks the single field, never entry data — so a forged
     * `params-updated` event can at most skew the forger's own count numbers, a
     * low-severity aggregate effect we accept in exchange for letting tag-authored
     * control params (status, site, ...) scope the counts the same way they scope
     * the entries query. Collection switching stays blocked regardless:
     * CountQueryPool forces the component's locked collection to win the `from` merge.
     */
    #[On('params-updated')]
    public function updateCounts($params)
    {
        if (! config('statamic-livewire-filters.enable_filter_values_count')) {
            return;
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

        $singleTaxonomyPrefix = $this->singleTaxonomyPrefix();

        foreach ($fieldValues as $fieldValue) {
            // Array values come from multi-select fields (entries, terms, ...)
            foreach (is_array($fieldValue) ? $fieldValue : [$fieldValue] as $value) {
                // Single-taxonomy options use bare slugs, but entries may store "taxonomy::slug"
                if ($singleTaxonomyPrefix && is_string($value) && str_starts_with($value, $singleTaxonomyPrefix)) {
                    $value = substr($value, strlen($singleTaxonomyPrefix));
                }

                if (isset($this->statamic_field['counts'][$value])) {
                    $this->statamic_field['counts'][$value]++;
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
