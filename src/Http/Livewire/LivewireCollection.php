<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Jonassiewertsen\Livewire\WithPagination;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Statamic\Tags\Collection\Entries;

class LivewireCollection extends Component
{
    use Traits\GenerateParams, Traits\HandleParams, WithPagination;

    public $params;

    #[Locked]
    public $collections;

    public $paginate;

    public $view = 'livewire-collection';

    public function mount($params)
    {
        if (is_null($this->params)) {
            $this->setParameters($params);
        } else {
            $this->setParameters(array_merge($params, $this->params));
        }
    }

    #[On('filter-updated')]
    public function filterUpdated($field, $condition, $payload, $command, $modifier)
    {
        if ($condition === 'taxonomy') {
            $this->handleTaxonomyCondition($field, $payload, $command, $modifier);

            return;
        }
        $this->handleCondition($field, $condition, $payload, $command);
    }

    #[On('sort-updated')]
    public function sortUpdated($sort)
    {
        if ($sort === '' || $sort === null) {
            unset($this->params['sort']);

            return;
        }
        $this->params['sort'] = $sort;
    }

    protected function queryString()
    {
        if (config('statamic-livewire-filters.enable_query_string')) {
            return [
                'params' => ['except' => []],
            ];
        }

        return [];
    }

    public function entries()
    {
        $entries = (new Entries($this->generateParams()))->get();
        if ($this->paginate) {
            return $this->withPagination('entries', $entries);
        }

        return ['entries' => $entries];
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.'.$this->view)->with([
            ...$this->entries(),
        ]);
    }
}
