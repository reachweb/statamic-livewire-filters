<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Jonassiewertsen\Livewire\WithPagination;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Statamic\Tags\Collection\Entries;
use Statamic\Support\Traits\Hookable;

class LivewireCollection extends Component
{
    use Traits\GenerateParams, Traits\HandleParams, WithPagination, Hookable;

    public $params;

    #[Locked]
    public $collections;

    #[Locked]
    public $allowedFilters;

    public $paginate;

    public $view = 'livewire-collection';

    public function mount($params)
    {
        $this->allowedFilters = false;
        if (is_null($this->params)) {
            $this->setParameters($params);
        } else {
            $this->setParameters(array_merge($params, $this->params));
        }
        $this->dispatchParamsUpdated();

        $this->runHooks('init');
    }

    #[On('filter-updated')]
    public function filterUpdated($field, $condition, $payload, $command, $modifier)
    {
        $this->resetPagination();
        if ($condition === 'query_scope') {
            $this->handleQueryScopeCondition($field, $payload, $command, $modifier);

            return;
        }
        if ($condition === 'taxonomy') {
            $this->handleTaxonomyCondition($field, $payload, $command, $modifier);

            return;
        }
        $this->handleCondition($field, $condition, $payload, $command);
    }

    #[On('sort-updated')]
    public function sortUpdated($sort)
    {
        $this->resetPagination();
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

    public function paginationView()
    {
        return 'statamic-livewire-filters::livewire.ui.pagination';
    }

    protected function resetPagination()
    {
        if ($this->paginate) {
            $this->resetPage();
        }
    }

    public function entries()
    {
        $entries = (new Entries($this->generateParams()))->get();

        $entries = $this->runHooks('livewire-fetched-entries', $entries);

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

    public function rendered()
    {
        $this->dispatch('entries-updated')->self();
    }
}
