<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Livewire\Attributes\On;
use Reach\StatamicLivewireFilters\Http\Livewire\LfTags;

trait HandleParams
{
    public function setParameters($params)
    {
        if ($customUrlParams = $this->handleCustomQueryStringParams()) {
            $params = array_merge($params, $customUrlParams);
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
            return request()->get('params');
        }

        return false;
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
        $paramKey = $field.':'.$condition;
        $this->params[$paramKey] = $this->toPipeSeparatedString($payload);

        $this->dispatchParamsUpdated();
    }

    protected function handleTaxonomyCondition($field, $payload, $modifier)
    {
        $paramKey = 'taxonomy:'.$field.':'.$modifier;
        $this->params[$paramKey] = $this->toPipeSeparatedString($payload);

        $this->dispatchParamsUpdated();
    }

    protected function handleQueryScopeCondition($field, $payload, $modifier)
    {
        $queryScopeKey = 'query_scope';
        $modifierKey = $modifier.':'.$field;

        $this->params[$queryScopeKey] = $modifier;
        $this->params[$modifierKey] = $this->toPipeSeparatedString($payload);

        $this->dispatchParamsUpdated();
    }

    protected function handleDualRangeCondition($field, $payload, $modifier)
    {
        [$minModifier, $maxModifier] = $this->getDualRangeConditions($modifier);

        $minParamKey = $field.':'.$minModifier;
        $maxParamKey = $field.':'.$maxModifier;

        $this->params[$minParamKey] = $payload['min'];
        $this->params[$maxParamKey] = $payload['max'];

        $this->dispatchParamsUpdated();
    }

    #[On('clear-filter')]
    public function clearFilter($field, $condition, $modifier): void
    {
        if ($condition === 'query_scope') {
            $queryScopeKey = 'query_scope';
            $modifierKey = $modifier.':'.$field;
            unset($this->params[$queryScopeKey], $this->params[$modifierKey]);

            return;
        }
        if ($condition === 'taxonomy') {
            $paramKey = 'taxonomy:'.$field.':'.$modifier;
            unset($this->params[$paramKey]);

            return;
        }
        if ($condition === 'dual_range') {
            [$minModifier, $maxModifier] = $this->getDualRangeConditions($modifier);

            $minParamKey = $field.':'.$minModifier;
            $maxParamKey = $field.':'.$maxModifier;

            unset($this->params[$minParamKey], $this->params[$maxParamKey]);

            return;
        }
        unset($this->params[$field.':'.$condition]);
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

    #[On('preset-params')]
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

        $fullPath = $path
            ? trim($this->currentPath, '/').'/'.trim($path, '/')
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
}
