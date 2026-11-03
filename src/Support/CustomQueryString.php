<?php

namespace Reach\StatamicLivewireFilters\Support;

use Illuminate\Support\Str;

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

    /**
     * The filter params the current request carries in its URL, as the collection
     * reads them: Livewire's `?params[...]` query, or the custom query string
     * segments that HandleFiltersQueryString merged into the request. Filters read
     * these at mount so their first render already shows the URL's selection.
     * Blocked params are dropped as the collection drops them; one a tag re-permits
     * with `allowed_filters` still reaches its filter through `preset-params`.
     *
     * @return array<string, string>
     */
    public static function urlParams(): array
    {
        if (static::livewireQueryStringEnabled()) {
            $params = request()->query('params');
            $params = is_array($params) ? array_filter($params, 'is_string') : [];
        } else {
            $params = static::enabled() ? request()->input('params') : null;
            $params = is_array($params)
                ? static::sanitize(array_filter($params, fn ($value) => is_string($value) && mb_strlen($value) <= 255))
                : [];
        }

        return BlockedQueryParams::strip($params);
    }

    /**
     * @param  array<string, string>  $params
     * @return array<string, string>
     */
    public static function sanitize(array $params): array
    {
        return collect($params)
            ->map(function ($value) {
                $value = htmlspecialchars($value);
                $value = strip_tags($value);
                $value = Str::before($value, '?');

                // Additional sanitization
                return preg_replace('/[<>\'";]/', '', $value);
            })
            ->filter()
            ->all();
    }

    /**
     * Strip the custom query string prefix and everything after it,
     * leaving just the base page path (no leading slash).
     */
    public static function stripPrefix(string $path): string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), fn ($segment) => $segment !== ''));

        $prefix = static::prefix();

        if ($prefix === false) {
            return implode('/', $segments);
        }

        $prefixIndex = array_search($prefix, $segments, true);

        if ($prefixIndex === false) {
            return implode('/', $segments);
        }

        return implode('/', array_slice($segments, 0, $prefixIndex));
    }
}
