<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Statamic\Entries\EntryCollection;
use Statamic\Tags\Collection\Entries;

trait HandleEntriesCount
{
    use GenerateParams;

    #[Computed]
    public function counts()
    {
        if ($this->options !== null && is_array($this->options)) {
            return collect($this->options)->keys()->flatMap(fn ($option) => [$option => null])->all();
        }

        return $this->statamic_field['counts'];
    }

    #[On('params-updated')]
    public function updateCounts($params)
    {
        foreach ($this->statamic_field['options'] as $option => $label) {
            $params = array_merge($params, $this->getOptionParam($option));
            $this->statamic_field['counts'][$option] = (new Entries($this->generateParamsForCount($this->collection, $params)))->count();
        }
        $this->dispatch('counts-updated', $this->counts());
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

    protected function countAllEntries(array $entries): int
    {
        if (isset($entries['pagination_total'])) {
            return (int) $entries['pagination_total'];
        } elseif ($entries['entries'] instanceof EntryCollection) {
            return $entries['entries']->count();
        }

        return 0;
    }
}
