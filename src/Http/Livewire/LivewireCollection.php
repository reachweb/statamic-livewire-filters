<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Reach\StatamicLivewireFilters\Support\CustomQueryString;
use Reach\StatamicLivewireFilters\Support\Nocache;
use Statamic\Support\Traits\Hookable;
use Statamic\Tags\Collection\Entries;

class LivewireCollection extends Component
{
    use Hookable,
        Traits\GenerateParams,
        Traits\HandleParams,
        Traits\HandleTotalEntriesCount,
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
    public $infiniteScroll = false;

    #[Locked]
    public $initialPaginate = null;

    #[Locked]
    public $hasMorePages = false;

    #[Locked]
    public $view = 'livewire-collection';

    #[Locked]
    public $lazyPlaceholder = 'lazyload-placeholder';

    #[Locked]
    public $scrollTo = null;

    public function mount($params)
    {
        $this->currentPath = $this->resolveCurrentPath();
        $this->allowedFilters = false;
        if (is_null($this->params)) {
            $this->setParameters($params);
        } else {
            $this->setParameters(array_merge($params, $this->params));
        }
        $this->initialPaginate = (int) $this->paginate;

        if ($this->infiniteScroll && $this->initialPaginate < 1) {
            $this->infiniteScroll = false;
        }

        if ($this->infiniteScroll) {
            $this->resetPage($this->paginationPageName());
        }

        $this->dispatchParamsUpdated();

        $this->runHooks('init');
    }

    protected function resolveCurrentPath(): string
    {
        if (! request()->hasHeader('X-Livewire')) {
            if ($nocacheUrl = $this->resolveStatamicNocacheUrl()) {
                $parsed = parse_url($nocacheUrl);

                if (is_array($parsed)) {
                    $path = $this->stripCustomQueryStringPrefix($parsed['path'] ?? '/');

                    return $this->combinePathAndQuery($path, $parsed['query'] ?? null);
                }
            }

            return $this->combinePathAndQuery(request()->path(), request()->getQueryString());
        }

        $referer = request()->headers->get('referer');

        if (! $referer) {
            return '/';
        }

        $parsed = parse_url($referer);

        if (! is_array($parsed)) {
            return '/';
        }

        $path = $this->stripCustomQueryStringPrefix($parsed['path'] ?? '/');

        return $this->combinePathAndQuery($path, $parsed['query'] ?? null);
    }

    protected function resolveStatamicNocacheUrl(): ?string
    {
        if (! Nocache::matches(request())) {
            return null;
        }

        $url = request()->input('url');

        return is_string($url) && $url !== '' ? $url : null;
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
        if (CustomQueryString::livewireQueryStringEnabled()) {
            return [
                'params' => ['except' => []],
            ];
        }

        // Suppress Livewire's URL handling for the configured paginator; we manage the
        // URL ourselves in updateCustomQueryStringUrl().
        if (CustomQueryString::enabled()) {
            return [
                'paginators.'.$this->paginationPageName() => ['except' => '', 'history' => false],
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
        if ($this->infiniteScroll) {
            $this->paginate = $this->initialPaginate;
        }
        if ($this->paginate) {
            $this->resetPage($this->paginationPageName());
        }
    }

    public function clearAll()
    {
        $this->dispatch('clear-all-filters');
    }

    #[On('clear-all-filters')]
    public function resetPaginationOnClearAll(): void
    {
        $this->resetPagination();
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

    public function placeholder(array $params = [])
    {
        $lazyPlaceholder = $params['params']['lazy-placeholder'] ?? $this->lazyPlaceholder;

        return view('statamic-livewire-filters::livewire.ui.'.$lazyPlaceholder);
    }

    public function rendered()
    {
        $this->dispatch('entries-updated', count: $this->entriesCount, active: $this->activeFilters);
    }
}
