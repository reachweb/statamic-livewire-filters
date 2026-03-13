<?php

namespace Reach\StatamicLivewireFilters\Support;

use Illuminate\Support\Collection;
use Statamic\Facades\Site;
use Statamic\Query\EloquentQueryBuilder;
use Statamic\Tags\Collection\Entries as StatamicEntries;
use Statamic\Tags\Collection\NoResultsExpected;

class CountEntries extends StatamicEntries
{
    public function pluck(string $column): Collection
    {
        try {
            $query = $this->query();

            if ($limit = $this->params->int('limit')) {
                $query->limit($limit);
            }

            if ($offset = $this->params->int('offset')) {
                $query->offset($offset);
            }

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

    protected function eloquentPluck(EloquentQueryBuilder $query, string $column): Collection
    {
        try {
            $results = $query->toBase()->pluck("data->{$column}");

            // JSON-stored arrays come back as strings -- decode them
            return $results->map(function ($value) {
                if (is_string($value)) {
                    $decoded = json_decode($value, true);

                    return is_array($decoded) ? $decoded : $value;
                }

                return $value;
            });
        } catch (\Exception $e) {
            // Fall back to standard pluck if raw query fails
            return $query->pluck($column);
        }
    }

    protected function isMultisiteCollection(): bool
    {
        if (! Site::multiEnabled()) {
            return false;
        }

        $collection = $this->collections->first();

        return $collection && $collection->sites()->count() > 1;
    }
}
