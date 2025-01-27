<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Statamic\Entries\EntryCollection;

trait HandleTotalEntriesCount
{
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
