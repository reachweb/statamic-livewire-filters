<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

trait HandleParams
{
    protected function handleCondition($field, $condition, $payload, $command)
    {
        $paramKey = $field.':'.$condition;
        if ($command === 'add') {
            $this->addValueToParam($paramKey, $payload);
        } elseif ($command === 'replace') {
            $this->replaceValueOfParam($paramKey, $payload);
        } elseif ($command === 'remove') {
            $this->removeValueFromParam($paramKey, $payload);
        }
    }

    protected function handleTaxonomyCondition($payload, $command, $modifier)
    {
        [$taxonomy, $term] = explode('::', $payload);
        $paramKey = 'taxonomy:'.$taxonomy.':'.$modifier;
        if ($command === 'add') {
            $this->addValueToParam($paramKey, $term);
        } elseif ($command === 'replace') {
            $this->replaceValueOfParam($paramKey, $term);
        } elseif ($command === 'remove') {
            $this->removeValueFromParam($paramKey, $term);
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
