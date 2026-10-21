<?php

namespace Reach\StatamicLivewireFilters\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BlockedQueryParams
{
    /**
     * Reject client-supplied query-control params (see the `blocked_query_params`
     * config) from a param array. Keys are matched against the segment before the
     * first colon, so `status` blocks both `status` and `status:is`, while genuine
     * `field:condition` filter params pass through. Keys present in $allowed are
     * exempt so a tag's `allowed_filters` can explicitly re-permit one. Dynamic
     * bindings are always rejected, even when the blocklist is empty.
     *
     * @param  array<string, mixed>  $params
     * @param  Collection<int, string>|null  $allowed
     * @return array<string, mixed>
     */
    public static function strip(array $params, ?Collection $allowed = null): array
    {
        $params = self::rejectDynamicBindings($params);

        $blocked = collect(config('statamic-livewire-filters.blocked_query_params', []));

        if ($blocked->isEmpty()) {
            return $params;
        }

        $allowed ??= collect();

        return collect($params)
            ->reject(fn ($value, $key) => ! $allowed->contains($key) && $blocked->contains(Str::before($key, ':')))
            ->all();
    }

    /**
     * Statamic's Parameters::make() evaluates a `:key` value as an Antlers expression
     * and stores it under `key`, so client state must never carry one.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function rejectDynamicBindings(array $params): array
    {
        return array_filter($params, fn ($key) => ! str_starts_with((string) $key, ':'), ARRAY_FILTER_USE_KEY);
    }
}
