<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Statamic\Support\Traits\Hookable;
use Statamic\Tags\Collection\Entries;

class LivewireCollection extends Component
{
    use Hookable,
        Traits\GenerateParams,
        Traits\HandleParams,
        Traits\HandleTotalEntriesCount,
        Traits\RestoreSite,
        Traits\WithPagination;

    public $params;

    #[Locked]
    public $collections;

    #[Locked]
    public $entriesCount;

    #[Locked]
    public $activeFilters;

    #[Locked]
    public $allowedFilters;

    #[Locked]
    public $currentPath;

    #[Locked]
    public $paginate;

    #[Locked]
    public $view = 'livewire-collection';

    #[Locked]
    public $lazyLoadView = 'lazyload-placeholder';

    #[Locked]
    public $scrollTo = null;

    public function mount($params)
    {
        $this->currentPath = request()->path() === 'livewire/update' ? url()->previous() : request()->path();
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
    public function filterUpdated($field, $condition, $payload, $modifier)
    {
        $this->resetPagination();
        if ($payload === '' || $payload === null || $payload === []) {
            $this->clearFilter($field, $condition, $modifier);

            return;
        }
        if ($condition === 'query_scope') {
            $this->handleQueryScopeCondition($field, $payload, $modifier);

            return;
        }
        if ($condition === 'taxonomy') {
            $this->handleTaxonomyCondition($field, $payload, $modifier);

            return;
        }
        if ($condition === 'dual_range') {
            $this->handleDualRangeCondition($field, $payload, $modifier);

            return;
        }
        $this->handleCondition($field, $condition, $payload);
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

    public function clearAll()
    {
        $this->dispatch('clear-all-filters');
    }

    public function entries()
    {
        $entries = (new Entries($this->generateParams()))->get();

        $entries = $this->runHooks('livewire-fetched-entries', $entries);

        // Update the URL if using custom query string
        $this->updateCustomQueryStringUrl();

        if ($this->paginate) {
            return $this->withPagination('entries', $entries);
        }

        return ['entries' => $entries];
    }

    public function render()
    {
        $entries = $this->entries();

        $this->entriesCount = $this->countAllEntries($entries);
        $this->activeFilters = $this->getParamsCount();

        return view('statamic-livewire-filters::livewire.'.$this->view)->with([
            ...$entries,
        ]);
    }

    public function placeholder()
    {
        return view('statamic-livewire-filters::livewire.ui.'.$this->lazyLoadView);
    }

    public function rendered()
    {
        $this->dispatch('entries-updated', count: $this->entriesCount, active: $this->activeFilters);
    }
}
