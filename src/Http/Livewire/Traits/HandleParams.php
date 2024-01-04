<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Reach\StatamicLivewireFilters\Exceptions\CommandNotFoundException;

trait HandleParams
{
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
}
