<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Reach\StatamicLivewireFilters\Exceptions\InvalidZeroCountModeException;
use Reach\StatamicLivewireFilters\Support\RangeBoundsQuery;
use Statamic\Entries\EntryCollection;

trait HandleEntriesCount
{
    use HandleFieldValuesQuery;

    /** How options with a count of zero render: `show`, `hide` or `disable`. */
    #[Locked]
    public $zero_count = 'show';

    public function mountHandleEntriesCount(): void
    {
        if (! in_array($this->zero_count, ['show', 'hide', 'disable'], true)) {
            throw new InvalidZeroCountModeException($this->zero_count);
        }
    }

    /** The options to render: with `zero_count="hide"`, those without matching entries are left out. */
    #[Computed]
    public function visibleOptions(): array
    {
        if ($this->zeroCountMode() !== 'hide') {
            return $this->filterOptions();
        }

        return array_diff_key($this->filterOptions(), array_flip($this->zeroCountOptions()));
    }

    /** @return list<string|int> The option keys to render disabled with `zero_count="disable"`. */
    #[Computed]
    public function disabledOptions(): array
    {
        return $this->zeroCountMode() === 'disable' ? $this->zeroCountOptions() : [];
    }

    /** The mode the views apply; without counts there is nothing to hide or disable. */
    #[Computed]
    public function zeroCountMode(): string
    {
        return config('statamic-livewire-filters.enable_filter_values_count') ? $this->zero_count : 'show';
    }

    // Counts are null until the first `params-updated`, so only an exact 0 matches. Selected options
    // stay usable, or a visitor could not see or uncheck an option the other filters narrowed to zero.
    protected function zeroCountOptions(): array
    {
        // `selected` comes from the client, so a tampered nested array must not reach strval().
        $selected = array_map('strval', array_filter((array) $this->selected, 'is_scalar'));

        return array_keys(array_filter(
            $this->counts(),
            fn ($count, $value) => $count === 0 && ! in_array((string) $value, $selected, true),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

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
