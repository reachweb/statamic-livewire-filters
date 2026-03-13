<?php

namespace Reach\StatamicLivewireFilters\Support;

use Illuminate\Support\Collection;
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

            return $query->pluck($column);
        } catch (NoResultsExpected $exception) {
            return collect();
        }
    }
}
