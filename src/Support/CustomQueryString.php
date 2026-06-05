<?php

namespace Reach\StatamicLivewireFilters\Support;

class CustomQueryString
{
    public static function livewireQueryStringEnabled(): bool
    {
        return (bool) config('statamic-livewire-filters.enable_query_string');
    }

    /**
     * The active custom query string prefix, or false when the feature is off.
     *
     * Only one URL mode may be active at a time: when `enable_query_string`
     * is on, Livewire's own query string handling wins and the custom
     * prefix is treated as disabled.
     */
    public static function prefix(): string|false
    {
        if (static::livewireQueryStringEnabled()) {
            return false;
        }

        $prefix = config('statamic-livewire-filters.custom_query_string', 'filters');

        return is_string($prefix) && $prefix !== '' ? $prefix : false;
    }

    public static function enabled(): bool
    {
        return static::prefix() !== false;
    }
}
