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
            return $this->query()->pluck($column);
        } catch (NoResultsExpected $exception) {
            return collect();
        }
    }
}
