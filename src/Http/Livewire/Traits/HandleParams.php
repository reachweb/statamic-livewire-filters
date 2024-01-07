<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Reach\StatamicLivewireFilters\Exceptions\CommandNotFoundException;

trait HandleParams
{
    public function setParameters($params)
    {
        $paramsCollection = collect($params);

        $this->extractCollectionKeys($paramsCollection);
        $this->extractView($paramsCollection);
        $this->extractPagination($paramsCollection);

        $this->params = $paramsCollection->all();
        $this->handlePresetParams();
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
}
