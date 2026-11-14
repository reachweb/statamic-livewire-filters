<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Reach\StatamicLivewireFilters\Exceptions\InvalidZeroCountModeException;
use Reach\StatamicLivewireFilters\Support\RangeBoundsQuery;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder as StacheEntryQueryBuilder;

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
        $hidden = [
            ...($this->zeroCountMode() === 'hide' ? $this->zeroCountOptions() : []),
            ...$this->unavailableOptions('hide'),
        ];

        return $hidden === [] ? $this->filter_options : array_diff_key($this->filter_options, array_flip($hidden));
    }

    /** @return list<string|int> The option keys to render disabled with `zero_count="disable"`. */
    #[Computed]
    public function disabledOptions(): array
    {
        return [
            ...($this->zeroCountMode() === 'disable' ? $this->zeroCountOptions() : []),
            ...$this->unavailableOptions('disable'),
        ];
    }

    /**
     * Option keys a composing trait hides or disables besides the zero counts; see HandleDependentOptions.
     *
     * @param  'hide'|'disable'  $mode
     * @return list<string|int>
     */
    protected function unavailableOptions(string $mode): array
    {
        return [];
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
        $selected = $this->selectedOptionKeys();

        return array_keys(array_filter(
            $this->counts(),
            fn ($count, $value) => $count === 0 && ! in_array((string) $value, $selected, true),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /**
     * `selected` comes from the client, so a tampered nested array must not reach strval().
     *
     * @return list<string>
     */
    protected function selectedOptionKeys(): array
    {
        return array_values(array_map('strval', array_filter((array) $this->selected, 'is_scalar')));
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

        if (isset($this->statamic_field['options']) && is_array($this->statamic_field['options'])) {
            return array_fill_keys(array_keys($this->statamic_field['options']), null);
        }

        return [];
    }

    // Only the collection's encrypted query can scope counts; the plain params are client-controlled
    // and could probe hidden entries (e.g. `status:is any` with `title:contains`), so they are ignored.
    #[On('params-updated')]
    public function updateCounts($params, $boundsQuery = null)
    {
        if (! config('statamic-livewire-filters.enable_filter_values_count')) {
            $this->skipRenderIfFilterStateUnchanged();

            return;
        }

        $params = RangeBoundsQuery::paramsForCollection($boundsQuery, $this->collection);

        if ($params === null) {
            $this->skipRenderIfFilterStateUnchanged();

            return;
        }

        $this->refreshCounts($params);
    }

    /** @param  array<string, mixed>  $params  The collection's params, from its encrypted query. */
    protected function refreshCounts(array $params): void
    {
        $fieldHandle = $this->resolveCountFieldHandle();

        $baseParams = $this->removeCurrentFieldFromParams($params, $fieldHandle);

        $countsBefore = $this->statamic_field['counts'] ?? null;

        $this->updateCountsWithBatchQuery($baseParams, $fieldHandle, $baseParams['from']);

        // The Alpine views update from counts-updated; only views printing counts need the html.
        if ($this->statamic_field['counts'] === $countsBefore) {
            $this->skipRenderIfFilterStateUnchanged();
        } else {
            $this->markFilterStateChanged();
        }

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

        foreach ($this->optionValuesIn($fieldValues) as $value) {
            $this->statamic_field['counts'][$value]++;
        }
    }

    /**
     * The option keys found in the plucked field values, once per occurrence.
     *
     * @return list<string|int|float|bool>
     */
    protected function optionValuesIn($fieldValues): array
    {
        $values = [];
        $singleTaxonomyPrefix = $this->singleTaxonomyPrefix();

        foreach ($fieldValues as $fieldValue) {
            // Multi-select values (checkboxes, entries, terms) are flattened one level; nested
            // arrays and objects (e.g. Carbon dates) are skipped, as such offsets throw in array_key_exists()
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
        [$minComparison, $maxComparison] = $this->boundsComparisons();
        $minInclusive = $minComparison === 'gte';
        $maxInclusive = $maxComparison === 'lte';

        // The Stache's where() compares every value uncast, so an entry without a value passes
        // `<` and `<=` and is listed by a bucket with an open min. SQL never matches one.
        $countsLikeStache = Entry::query() instanceof StacheEntryQueryBuilder;

        $counts = $this->statamic_field['counts'];

        foreach ($fieldValues as $value) {
            if (! $countsLikeStache && ! is_numeric($value)) {
                continue;
            }

            foreach ($buckets as $key => ['min' => $min, 'max' => $max]) {
                // Uncast, like Statamic's own where() comparisons, so counts match the entries query.
                if (($min === null || ($minInclusive ? $value >= $min : $value > $min))
                    && ($max === null || ($maxInclusive ? $value <= $max : $value < $max))) {
                    $counts[$key]++;
                }
            }
        }

        $this->statamic_field['counts'] = $counts;
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
