<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Statamic\Facades\Blink;
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
    public $view = 'livewire-collection';

    #[Locked]
    public $lazyPlaceholder = 'lazyload-placeholder';

    #[Locked]
    public $scrollTo = null;

    public function mount($params)
    {
        if (request()->hasHeader('X-Livewire')) {
            $referer = request()->headers->get('referer');
            if ($referer) {
                $parsed = parse_url($referer);
                if (! is_array($parsed)) {
                    $this->currentPath = '/';
                } else {
                    $path = ltrim($parsed['path'] ?? '/', '/');

                    // Strip custom query string filter segments using segment matching
                    // to avoid false positives on partial matches (e.g. /myfilters/).
                    $prefix = config('statamic-livewire-filters.custom_query_string', 'filters');
                    if ($prefix) {
                        $segments = explode('/', $path);
                        $prefixIndex = array_search($prefix, $segments);
                        if ($prefixIndex !== false) {
                            $path = implode('/', array_slice($segments, 0, $prefixIndex));
                        }
                    }

                    $query = isset($parsed['query']) ? '?'.$parsed['query'] : '';
                    $this->currentPath = $path.$query;
                }
            } else {
                $this->currentPath = '/';
            }
        } else {
            $this->currentPath = request()->path();
        }
        $this->allowedFilters = false;
        if (is_null($this->params)) {
            $this->setParameters($params);
        } else {
            $this->setParameters(array_merge($params, $this->params));
        }
        // Store params in Blink for sibling filter components to read during
        // their mount(), avoiding a double HTTP request for initial count loading.
        if (config('statamic-livewire-filters.enable_filter_values_count')) {
            Blink::store('livewire-filters')->put('initial-params', $this->params);
        }

        $this->dispatch('tags-updated', $this->params)->to(LfTags::class);

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

        // When using custom query string, disable Livewire's pagination URL handling
        // to prevent a double pushState (which causes an extra render).
        // We handle pagination URL ourselves in updateCustomQueryStringUrl().
        if (config('statamic-livewire-filters.custom_query_string') !== false) {
            return [
                'paginators.page' => ['except' => '', 'history' => false],
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
