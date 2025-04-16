<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Reach\StatamicLivewireFilters\Http\Livewire\LfTags;

trait HandleParams
{
    public function setParameters($params)
    {
        if ($customUrlParams = $this->handleCustomQueryStringParams()) {
            $params = $this->mergeParameters($params, $customUrlParams);
        }
        $paramsCollection = collect($params);

        $this->extractCollectionKeys($paramsCollection);
        $this->extractView($paramsCollection);
        $this->extractPagination($paramsCollection);
        $this->extractAllowedFilters($paramsCollection);

        $this->params = $paramsCollection->all();
        $this->handlePresetParams();
    }

    protected function handleCustomQueryStringParams(): array|bool
    {
        if (
            config('statamic-livewire-filters.custom_query_string') !== false &&
            config('statamic-livewire-filters.enable_query_string') === false &&
            request()->has('params')
        ) {
            // Get and validate params
            $params = request()->validate([
                'params' => 'array',
                'params.*' => 'string|max:255',
            ])['params'] ?? [];

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

        return false;
    }

    protected function getParamsCount(): int
    {
        return collect($this->params)->reject(function ($value, $key) {
            if ($key === 'sort' || $key === 'query_scope') {
                return true;
            }
            if ($key === 'resrv_search:resrv_availability') {
                if (isset($value['dates']) && count($value['dates']) === 0) {
                    return true;
                }
            }
        })->count();
    }

    protected function extractCollectionKeys($paramsCollection)
    {
        $collectionKeys = ['from', 'in', 'folder', 'use', 'collection'];

        foreach ($collectionKeys as $key) {
            if ($paramsCollection->has($key)) {
                $this->collections = $paramsCollection->pull($key);
            }
        }
    }

    protected function extractView($paramsCollection)
    {
        if ($paramsCollection->has('view')) {
            $this->view = $paramsCollection->pull('view');
        }
    }

    protected function extractPagination($paramsCollection)
    {
        if ($paramsCollection->has('paginate')) {
            $this->paginate = $paramsCollection->pull('paginate');
        }
    }

    protected function extractAllowedFilters($paramsCollection)
    {
        if ($paramsCollection->has('allowed_filters')) {
            $this->allowedFilters = collect(explode('|', $paramsCollection->pull('allowed_filters')));
        }
    }

    protected function handleCondition($field, $condition, $payload)
    {
        $paramKey = $this->generateParamKey($field, $condition);
        $this->params[$paramKey] = $this->toPipeSeparatedString($payload);

        $this->dispatchParamsUpdated();
    }

    protected function handleTaxonomyCondition($field, $payload, $modifier)
    {
        $paramKey = $this->generateParamKey($field, 'taxonomy', $modifier);
        $this->params[$paramKey] = $this->toPipeSeparatedString($payload);

        $this->dispatchParamsUpdated();
    }

    // TODO: improve Resrv detection
    protected function handleQueryScopeCondition($field, $payload, $modifier)
    {
        $queryScopeKey = 'query_scope';
        $paramKey = $this->generateParamKey($field, 'query_scope', $modifier);

        if (isset($this->params[$queryScopeKey])) {
            $existingScopes = collect(explode('|', $this->params[$queryScopeKey]));
            if (! $existingScopes->contains($modifier)) {
                $existingScopes->push($modifier);
            }
            $this->params[$queryScopeKey] = $existingScopes->implode('|');
        } else {
            $this->params[$queryScopeKey] = $modifier;
        }

        $this->params[$paramKey] = $field === 'resrv_availability' ? $payload : $this->toPipeSeparatedString($payload);

        $this->dispatchParamsUpdated();
    }

    protected function handleDualRangeCondition($field, $payload, $modifier)
    {
        $paramKeys = $this->generateParamKey($field, 'dual_range', $modifier);

        $this->params[$paramKeys['min']] = $payload['min'];
        $this->params[$paramKeys['max']] = $payload['max'];

        $this->dispatchParamsUpdated();
    }

    #[On('clear-filter')]
    public function clearFilter($field, $condition, $modifier): void
    {
        if (! $this->fieldExistsInParams($field, $condition, $modifier)) {
            return;
        }

        if ($condition === 'query_scope') {
            $queryScopeKey = 'query_scope';

            // First unset the field's data
            $paramKey = $this->generateParamKey($field, 'query_scope', $modifier);
            unset($this->params[$paramKey]);

            $existingScopes = collect(explode('|', $this->params[$queryScopeKey]));
            $existingParams = collect($this->params)->filter(function ($value, $key) use ($modifier) {
                return Str::startsWith($key, $modifier.':');
            });

            // If there no more fields using this scope, let's remove it
            if ($existingParams->isEmpty()) {
                $existingScopes = $existingScopes->filter(function ($scope) use ($modifier) {
                    return $scope !== $modifier;
                });

                // If there are no more scopes, let's remove the whole query_scope key,
                // otherwise, let's update the query_scope key with the remaining scopes
                if ($existingScopes->isEmpty()) {
                    unset($this->params[$queryScopeKey]);
                } else {
                    $this->params[$queryScopeKey] = $existingScopes->implode('|');
                }
            }

            $this->dispatchParamsUpdated();

            return;
        }

        $paramKey = $this->generateParamKey($field, $condition, $modifier);

        if (is_array($paramKey)) {
            // Handle dual_range case which returns an array of keys
            unset($this->params[$paramKey['min']], $this->params[$paramKey['max']]);
        } else {
            unset($this->params[$paramKey]);
        }

        $this->dispatchParamsUpdated();
    }

    public function fieldExistsInParams($field, $condition, $modifier): bool
    {
        $paramKey = $this->generateParamKey($field, $condition, $modifier);

        if (is_array($paramKey)) {
            // Handle dual_range case which returns an array of keys
            return isset($this->params[$paramKey['min']]) || isset($this->params[$paramKey['max']]);
        }

        return isset($this->params[$paramKey]);
    }

    protected function mergeParameters($params, $urlParams): array
    {
        if (isset($params['query_scope']) && isset($urlParams['query_scope'])) {
            $urlParams['query_scope'] = collect(explode('|', $params['query_scope']))
                ->merge(explode('|', $urlParams['query_scope']))
                ->unique()
                ->implode('|');
        }

        return array_merge($params, $urlParams);
    }

    protected function toPipeSeparatedString($payload): string
    {
        return is_array($payload) ? implode('|', $payload) : $payload;
    }

    protected function handlePresetParams()
    {
        $params = collect($this->params);
        $collectionKeys = ['from', 'in', 'folder', 'use', 'collection'];
        $restOfParams = $params->except($collectionKeys);
        if ($restOfParams->isNotEmpty()) {
            $this->dispatch('preset-params', $restOfParams->all());
        }
    }

    #[Renderless, On('preset-params')]
    public function updateCustomQueryStringUrl(): void
    {
        if (config('statamic-livewire-filters.custom_query_string') === false) {
            return;
        }

        $aliases = $this->getConfigAliases();

        $prefix = config('statamic-livewire-filters.custom_query_string', 'filters');

        // Only include params that have aliases configured
        $segments = collect($this->params)
            ->filter(fn ($value, $key) => isset($aliases[$key]))
            ->map(function ($value, $key) use ($aliases) {
                $urlKey = $aliases[$key];

                // Convert pipe-separated values to comma-separated
                $urlValue = str_contains($value, '|')
                    ? str_replace('|', ',', $value)
                    : $value;

                return [$urlKey, $urlValue];
            })
            ->flatten()
            ->values();

        $path = $segments->isEmpty()
            ? ''
            : $prefix.'/'.$segments->implode('/');

        // Trim and sanitize the current path.
        $currentPathTrimmed = trim($this->currentPath, '/');

        // If the currentPath already includes the prefix, strip it and anything after.
        $prefixMarker = $prefix.'/';
        $prefixPos = strpos($currentPathTrimmed, $prefixMarker);
        if ($prefixPos !== false) {
            // Keep only the part before the prefix marker.
            $currentPathTrimmed = rtrim(substr($currentPathTrimmed, 0, $prefixPos), '/');
        }

        $fullPath = $path
            ? ($currentPathTrimmed ? $currentPathTrimmed.'/' : '').trim($path, '/')
            : $this->currentPath;

        $this->dispatch('update-url', newUrl: url($fullPath));
    }

    protected function getConfigAliases(): array
    {
        return collect(config('statamic-livewire-filters.custom_query_string_aliases', []))->transform(function ($value, $key) {
            if (str_contains($value, 'query_scope')) {
                [$scopeString, $scopeKey] = explode(':', $value, 2);

                return $scopeKey;
            }

            return $value;
        })->merge(['sort' => 'sort'])
            ->flip()
            ->all();
    }

    protected function getDualRangeConditions($modifer): array
    {
        $modifiers = ['gte', 'lte'];

        if ($modifer === 'any') {
            return $modifiers;
        }

        return explode('|', $modifer);
    }

    protected function dispatchParamsUpdated(): void
    {
        if (config('statamic-livewire-filters.enable_filter_values_count')) {
            $this->dispatch('params-updated', $this->params);
        }

        // Dispatching to the tags component
        $this->dispatch('tags-updated', $this->params)->to(LfTags::class);
    }

    protected function generateParamKey(string $field, string $condition, ?string $modifier = null): string|array
    {
        if ($condition === 'query_scope') {
            return $modifier.':'.$field;
        }

        if ($condition === 'taxonomy') {
            return 'taxonomy:'.$field.':'.$modifier;
        }

        if ($condition === 'dual_range') {
            [$minModifier, $maxModifier] = $this->getDualRangeConditions($modifier);

            return [
                'min' => $field.':'.$minModifier,
                'max' => $field.':'.$maxModifier,
            ];
        }

        return $field.':'.$condition;
    }
}
