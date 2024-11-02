<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Livewire\Attributes\On;
use Reach\StatamicLivewireFilters\Exceptions\CommandNotFoundException;
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

    protected function handleCondition($field, $condition, $payload, $command)
    {
        $paramKey = $field.':'.$condition;
        $this->runCommand($command, $paramKey, $payload);
    }

    protected function handleTaxonomyCondition($field, $payload, $command, $modifier)
    {
        $paramKey = 'taxonomy:'.$field.':'.$modifier;
        $this->runCommand($command, $paramKey, $payload);
    }

    protected function handleQueryScopeCondition($field, $payload, $command, $modifier)
    {
        $queryScopeKey = 'query_scope';
        $modifierKey = $modifier.':'.$field;

        switch ($command) {
            case 'add':
                $this->params[$queryScopeKey] = $modifier;
                if (! isset($this->params[$modifierKey])) {
                    $this->params[$modifierKey] = $payload;
                } else {
                    $payloads = collect(explode('|', $this->params[$modifierKey]));
                    if (! $payloads->contains($payload)) {
                        $payloads->push($payload);
                        $this->params[$modifierKey] = $payloads->implode('|');
                    }
                }
                break;

            case 'remove':
                if (isset($this->params[$modifierKey])) {
                    $payloads = collect(explode('|', $this->params[$modifierKey]));
                    $payloads = $payloads->reject(fn ($item) => $item === $payload);
                    if ($payloads->isNotEmpty()) {
                        $this->params[$modifierKey] = $payloads->implode('|');
                    } else {
                        unset($this->params[$modifierKey], $this->params[$queryScopeKey]);
                    }
                }
                break;

            case 'replace':
                $this->params[$queryScopeKey] = $modifier;
                $this->params[$modifierKey] = $payload;
                break;

            case 'clear':
                unset($this->params[$queryScopeKey], $this->params[$modifierKey]);
                break;

            default:
                throw new CommandNotFoundException($command);
        }
        $this->dispatchParamsUpdated();
    }

    protected function runCommand($command, $paramKey, $value)
    {
        switch ($command) {
            case 'add':
                $this->addValueToParam($paramKey, $value);
                break;
            case 'replace':
                $this->replaceValueOfParam($paramKey, $value);
                break;
            case 'remove':
                $this->removeValueFromParam($paramKey, $value);
                break;
            case 'clear':
                $this->clearParam($paramKey);
                break;
            default:
                throw new CommandNotFoundException($command);
                break;
        }

        $this->dispatchParamsUpdated();
    }

    protected function addValueToParam($paramKey, $value)
    {
        if (! isset($this->params[$paramKey])) {
            $this->params[$paramKey] = $value;
        } else {
            $values = collect(explode('|', $this->params[$paramKey]));
            if (! $values->contains($value)) {
                $values->push($value);
                $this->params[$paramKey] = $values->implode('|');
            }
        }
    }

    protected function replaceValueOfParam($paramKey, $value)
    {
        $this->params[$paramKey] = $value;
    }

    protected function clearParam($paramKey)
    {
        unset($this->params[$paramKey]);
    }

    protected function removeValueFromParam($paramKey, $value)
    {
        if (isset($this->params[$paramKey])) {
            $values = collect(explode('|', $this->params[$paramKey]));

            $values = $values->reject(fn ($item) => $item === $value);

            if ($values->isNotEmpty()) {
                $this->params[$paramKey] = $values->implode('|');
            } else {
                unset($this->params[$paramKey]);
            }
        }
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

    protected function dispatchParamsUpdated(): void
    {
        if (config('statamic-livewire-filters.enable_filter_values_count')) {
            $this->dispatch('params-updated', $this->params);
        }

        // Dispatching to the tags component
        $this->dispatch('tags-updated', $this->params)->to(LfTags::class);
    }
}
