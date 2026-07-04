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
     * exempt so a tag's `allowed_filters` can explicitly re-permit one.
     *
     * @param  array<string, mixed>  $params
     * @param  Collection<int, string>|null  $allowed
     * @return array<string, mixed>
     */
    public static function strip(array $params, ?Collection $allowed = null): array
    {
        $blocked = collect(config('statamic-livewire-filters.blocked_query_params', []));

        if ($blocked->isEmpty()) {
            return $params;
        }

        $allowed ??= collect();

        return collect($params)
            ->reject(fn ($value, $key) => ! $allowed->contains($key) && $blocked->contains(Str::before($key, ':')))
            ->all();
    }
}
