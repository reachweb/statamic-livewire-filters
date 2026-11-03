<?php

namespace Reach\StatamicLivewireFilters\Support;

use Statamic\Tags\Collection\Entries as StatamicEntries;

/**
 * Statamic's collection tag with the addon's own conditions. `field:contains_all`
 * matches multi-value fields holding every piped value, the match-all counterpart
 * of `field:overlaps`; Statamic's `contains` is a substring match, not an array one.
 */
class Entries extends StatamicEntries
{
    protected function queryCondition($query, $field, $condition, $value)
    {
        if ($condition !== 'contains_all') {
            return parent::queryCondition($query, $field, $condition, $value);
        }

        $field = str_replace('.', '->', $field);

        // One overlap per value, so it matches wherever its `overlaps` counterpart does,
        // including single values stored as a string
        foreach (is_string($value) ? $this->getPipedValues($value) : (array) $value as $item) {
            $query->whereJsonOverlaps($field, [$item]);
        }

        return $query;
    }
}
