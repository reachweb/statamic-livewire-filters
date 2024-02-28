<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Statamic\Tags\Collection\Entries;

trait HandleEntriesCount
{
    use GenerateParams;

    #[Computed]
    public function counts()
    {
        return $this->statamic_field['counts'];
    }

    #[On('params-updated')]
    public function updateCounts($params)
    {
        foreach ($this->statamic_field['options'] as $option => $label) {
            $params = array_merge($params, $this->getOptionParam($option));
            $this->statamic_field['counts'][$option] = (new Entries($this->generateParamsForCount($this->collection, $params)))->count();
        }
    }

    protected function getOptionParam($option)
    {
        if ($this->condition === 'query_scope') {
            return [
                'query_scope' => $this->modifier,
                $this->getParamKey() => $option,
            ];
        }

        return [$this->getParamKey() => $option];
    }
}
