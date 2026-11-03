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

    /**
     * Split a path at the first prefix segment into the base page path (no leading
     * slash) and the filter segments after the prefix, or null without a prefix.
     *
     * @return array{0: string, 1: array<int, string>}|null
     */
    public static function split(string $path): ?array
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), fn ($segment) => $segment !== ''));

        $prefix = static::prefix();
        $prefixIndex = $prefix === false ? false : array_search($prefix, $segments, true);

        if ($prefixIndex === false) {
            return null;
        }

        return [
            implode('/', array_slice($segments, 0, $prefixIndex)),
            array_slice($segments, $prefixIndex + 1),
        ];
    }

    /**
     * Strip the custom query string prefix and everything after it,
     * leaving just the base page path (no leading slash).
     */
    public static function stripPrefix(string $path): string
    {
        return static::split($path)[0]
            ?? implode('/', array_filter(explode('/', trim($path, '/')), fn ($segment) => $segment !== ''));
    }

    /**
     * Turn `alias/value` segment pairs into collection params, skipping unknown aliases.
     */
    public static function parseSegments(array $segments): array
    {
        $aliases = array_merge(
            [
                'sort' => 'sort',
            ],
            config('statamic-livewire-filters.custom_query_string_aliases', [])
        );
        $params = [];

        for ($i = 0; $i < count($segments); $i += 2) {
            if (! isset($segments[$i + 1])) {
                break;
            }

            $key = rawurldecode($segments[$i]);
            $value = rawurldecode($segments[$i + 1]);

            if (! isset($aliases[$key])) {
                continue;
            }

            // Convert alias to actual filter key
            $actualKey = $aliases[$key];

            // Handle query_scopes
            if (str_contains($actualKey, 'query_scope')) {
                [$scopeString, $scopeClass, $scopeKey] = explode(':', $actualKey);
                // Register the scope, keeping scopes from earlier aliases
                $params['query_scope'] = collect(explode('|', $params['query_scope'] ?? ''))
                    ->push($scopeClass)
                    ->filter()
                    ->unique()
                    ->implode('|');
                // Set the the actual parameter key
                $actualKey = $scopeClass.':'.$scopeKey;
            }

            // Handle multiple values
            $value = str_contains($value, ',')
                ? implode('|', explode(',', $value))
                : $value;

            $params[$actualKey] = $value;
        }

        return $params;
    }
}
