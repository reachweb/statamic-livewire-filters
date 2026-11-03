<?php

namespace Reach\StatamicLivewireFilters\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Statamic\Facades\Site;
use Statamic\Facades\Stache;
use Statamic\Query\EloquentQueryBuilder;
use Statamic\Stache\Query\EntryQueryBuilder as StacheEntryQueryBuilder;
use Statamic\Tags\Collection\Entries as StatamicEntries;
use Statamic\Tags\Collection\NoResultsExpected;

class CountEntries extends StatamicEntries
{
    public function pluck(string $column): Collection
    {
        try {
            $query = $this->countQuery();

            // Eloquent optimization: pluck directly from the base query to avoid
            // hydrating full Entry objects. Field values are in the JSON data column.
            // Skip for multisite collections — localized entries inherit field
            // values from their origin at runtime, and the raw JSON pluck
            // cannot resolve that fallback chain.
            if ($query instanceof EloquentQueryBuilder && ! $this->isMultisiteCollection()) {
                return $this->eloquentPluck($query, $column);
            }

            return $query->pluck($column);
        } catch (NoResultsExpected $exception) {
            return collect();
        }
    }

    /**
     * Stache only: the ids of the matching entries grouped by collection handle, so
     * any number of fields can be read straight from their indexes without re-running
     * the filter. Null when the driver or a scope-added collection rules that out.
     */
    public function resolvedKeys(): ?Collection
    {
        try {
            $query = $this->countQuery();
        } catch (NoResultsExpected $exception) {
            return collect();
        }

        if (! $query instanceof StacheEntryQueryBuilder) {
            return null;
        }

        // A keyed pluck would renumber numeric ids, so map each id back to its
        // collection through the stores' own (already loaded) path indexes.
        $owner = [];

        foreach ($this->collections as $collection) {
            foreach (Stache::store('entries')->store($collection->handle())->paths()->keys() as $id) {
                $owner[$id] = $collection->handle();
            }
        }

        $grouped = [];

        foreach ($query->pluck('id') as $id) {
            if (! isset($owner[$id])) {
                return null;
            }

            $grouped[$owner[$id]][] = $id;
        }

        return collect($grouped)->map(fn ($ids) => collect($ids));
    }

    /**
     * What the Stache query builder's pluck() does once it holds the keys, minus
     * re-splitting "collection::id" strings for every field.
     */
    public static function valuesForKeys(Collection $keys, string $column): Collection
    {
        return $keys
            ->flatMap(fn ($ids, $handle) => Stache::store('entries')->store($handle)->getItemValues($ids, $column, null))
            ->values();
    }

    /**
     * Counts and bounds are order-independent, and the Stache pays for the sort
     * with a full multisort of every matching key, so drop it unless a limit or
     * offset (from the params or a query scope) makes it select the rows.
     */
    protected function countQuery()
    {
        $query = $this->query();

        if ($limit = $this->params->int('limit')) {
            $query->limit($limit);
        }

        if ($offset = $this->params->int('offset')) {
            $query->offset($offset);
        }

        if ($query instanceof StacheEntryQueryBuilder && ! (fn () => $this->limit || $this->offset)->call($query)) {
            $query->reorder();
        }

        return $query;
    }

    protected function eloquentPluck(EloquentQueryBuilder $query, string $column): Collection
    {
        try {
            // Without an alias the builder reads the row property "data->{$column}", which doesn't exist.
            $results = $query->toBase()->pluck("data->{$column} as lf_value");

            // JSON-stored arrays come back as strings -- decode them
            return $results->map(function ($value) {
                if (is_string($value) && isset($value[0]) && ($value[0] === '[' || $value[0] === '{')) {
                    $decoded = json_decode($value, true);

                    return is_array($decoded) ? $decoded : $value;
                }

                return $value;
            });
        } catch (QueryException $e) {
            // Fall back to standard pluck if raw query fails
            return $query->pluck($column);
        }
    }

    protected function isMultisiteCollection(): bool
    {
        if (! Site::multiEnabled()) {
            return false;
        }

        return $this->collections->contains(fn ($collection) => $collection->sites()->count() > 1);
    }
}
