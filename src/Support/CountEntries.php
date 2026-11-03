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
     * Stache only: the "collection::id" keys of the matching entries, so any number
     * of fields can be read straight from their indexes without re-running the
     * filter. Null when the driver cannot resolve keys without hydrating entries.
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

        $ids = $query->pluck('id');

        if ($this->collections->count() === 1) {
            $handle = $this->collections->first()->handle();

            return $ids->map(fn ($id) => "{$handle}::{$id}")->values();
        }

        // A keyed pluck would renumber numeric ids, so map each id back to its
        // collection through the stores' own (already loaded) path indexes.
        $owner = [];

        foreach ($this->collections as $collection) {
            foreach (Stache::store('entries')->store($collection->handle())->paths()->keys() as $id) {
                $owner[$id] = $collection->handle();
            }
        }

        $keys = $ids->map(fn ($id) => isset($owner[$id]) ? "{$owner[$id]}::{$id}" : null);

        return $keys->contains(fn ($key) => $key === null) ? null : $keys->values();
    }

    /**
     * Exactly what the Stache query builder's pluck() does once it holds the keys.
     */
    public static function valuesForKeys(Collection $keys, string $column): Collection
    {
        if ($keys->isEmpty()) {
            return collect();
        }

        return Stache::store('entries')->getItemValues($keys, $column, null)->values();
    }

    /**
     * Counts and bounds are order-independent, and the Stache pays for the
     * collection's default sort with a full multisort of every matching key;
     * keep the ordering only when limit/offset make it select the rows.
     */
    protected function countQuery()
    {
        $query = $this->query();

        $limit = $this->params->int('limit');
        $offset = $this->params->int('offset');

        if (! $limit && ! $offset) {
            return $query->reorder();
        }

        if ($limit) {
            $query->limit($limit);
        }

        if ($offset) {
            $query->offset($offset);
        }

        return $query;
    }

    protected function eloquentPluck(EloquentQueryBuilder $query, string $column): Collection
    {
        try {
            $results = $query->toBase()->pluck("data->{$column}");

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
