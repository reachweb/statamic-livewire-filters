<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Illuminate\Support\Str;
use Reach\StatamicLivewireFilters\Support\BlockedQueryParams;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;

trait GenerateParams
{
    /**
     * The locked `from` and `paginate` are merged last so no param surviving the
     * allowed/blocked filtering can re-point the query at another collection.
     */
    protected function generateParams()
    {
        return Parameters::make(array_merge(
            $this->effectiveQueryParams(),
            ['from' => $this->collections, 'paginate' => $this->paginate]),
            Context::make([])
        );
    }

    /**
     * Filter state after the allowed/blocked hardening — what both the entries
     * query and the counts dispatch must use.
     */
    protected function effectiveQueryParams(): array
    {
        $params = ($this->allowedFilters && $this->allowedFilters->isNotEmpty())
            ? $this->removeParamsNotInAllowedFiltersCollection()
            : $this->params;

        return $this->removeBlockedQueryParams($params);
    }

    protected function removeBlockedQueryParams(array $params): array
    {
        $allowed = ($this->allowedFilters && $this->allowedFilters->isNotEmpty())
            ? $this->allowedFilters
            : null;

        return array_merge(
            $this->trustedQueryParams,
            BlockedQueryParams::strip($params, $allowed),
        );
    }

    protected function captureTrustedQueryParams(array $tagParams): void
    {
        $blocked = collect(config('statamic-livewire-filters.blocked_query_params', []));

        $this->trustedQueryParams = collect($tagParams)
            ->only(array_keys($this->params))
            ->reject(fn ($value, $key) => ! $blocked->contains(Str::before($key, ':')))
            ->all();
    }

    /**
     * Init hooks run after the tag-param capture above and are registered in
     * application code, so blocked-key params they add or change are as trusted
     * as the tag's own. Without this capture, effectiveQueryParams() would strip
     * a hook's constraint or restore the tag value the hook overrode. Keys the
     * hook removed are dropped from the trusted set so they are not restored.
     */
    protected function captureHookAuthoredQueryParams(array $paramsBeforeHooks): void
    {
        $blocked = collect(config('statamic-livewire-filters.blocked_query_params', []));

        $hookAuthored = collect($this->params)
            ->filter(fn ($value, $key) => $blocked->contains(Str::before($key, ':'))
                && (! array_key_exists($key, $paramsBeforeHooks) || $paramsBeforeHooks[$key] !== $value))
            ->all();

        $this->trustedQueryParams = collect($this->trustedQueryParams)
            ->filter(fn ($value, $key) => array_key_exists($key, $this->params))
            ->merge($hookAuthored)
            ->all();
    }

    protected function removeParamsNotInAllowedFiltersCollection()
    {
        return collect($this->params)->filter(function ($value, $key) {
            // page_name is collection config, not a filter — it must always reach the
            // Entries tag so Statamic paginates under the same name the addon resets.
            if ($key === 'sort' || $key === 'page_name') {
                return true;
            }
            if ($key === 'query_scope') {
                return $this->allowedFilters->contains('query_scope:'.$value);
            }

            return $this->allowedFilters->contains($key);
        })->all();
    }
}
