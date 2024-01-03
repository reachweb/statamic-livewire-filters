<?php 

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

trait HandleConditions
{
    public function handleExceptions($field, $condition, $payload)
    {
        if ($condition === 'is') {
            $this->handleIsCondition($payload);
        }
    }

    public function handleIsCondition($payload)
    {
        if (is_array($payload)) {
            throw new \Exception('The [is] condition does not accept an array as payload. Maybe use a radio filter instead?');
        }
    }
}
