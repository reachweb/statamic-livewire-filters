<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
    public array $trustedQueryParams = [];

    #[Locked]
    public int $boundsQueryRevision = 0;

    #[Locked]
    public array $boundsSelectionRevisions = [];

    #[Locked]
    public array $clearAllExemptParamKeys = [];

    #[Locked]
    public array $clearAllExemptScopes = [];

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
        if (is_array($this->params)) {
            $this->setParameters(array_merge($params, $this->sanitizeClientParams($this->params, $params)));
        } else {
            $this->setParameters($params);
        }
        $this->captureTrustedQueryParams($params);
        $this->initialPaginate = (int) $this->paginate;

        if ($this->infiniteScroll && $this->initialPaginate < 1) {
            $this->infiniteScroll = false;
        }

        if ($this->infiniteScroll) {
            $this->resetPage($this->paginationPageName());
        }

        $paramsBeforeHooks = $this->params;

        $this->runHooks('init');

        $this->captureHookAuthoredQueryParams($paramsBeforeHooks);

        $this->captureClearAllExemptParams($params, $paramsBeforeHooks);

        // Dispatched after the init hooks and trust captures so the initial
        // counts/bounds/tags payloads match what the entries query will use.
        $this->dispatchParamsUpdated(initial: true);
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
    public function filterUpdated($field, $condition, $payload, $modifier, $boundsSelection = null)
    {
        $this->rememberBoundsSelectionRevision($boundsSelection);

        if ($this->filterUpdateIsRedundant($field, $condition, $payload, $modifier)) {
            $this->skipRenderIfCollectionStateUnchanged();

            return;
        }
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

        return [];
    }

    public function paginationView()
    {
        return 'statamic-livewire-filters::livewire.ui.pagination';
    }

    protected function resetPagination()
    {
        $this->markCollectionStateChanged();

        if ($this->infiniteScroll) {
            $this->paginate = $this->initialPaginate;
        }
        if ($this->paginate) {
            $this->resetPage($this->paginationPageName());
        }
    }

    /**
     * The sweep removes every runtime param itself. The condition keys it had to keep
     * (tag- or init-hook-authored) and the params it removed ride along positionally, so
     * only a filter managing a kept key, or one whose selection the sweep missed, relays
     * a clear-filter. Listeners without the arguments still work.
     */
    public function clearAll()
    {
        $paramsBeforeSweep = $this->params;

        $this->clearAllFilterParams();

        $this->dispatch('clear-all-filters', $this->keptConditionParamKeys(), array_diff_key($paramsBeforeSweep, $this->params));
    }

    /**
     * @return array<int, string>
     */
    protected function keptConditionParamKeys(): array
    {
        return collect($this->params)
            ->keys()
            ->filter(fn ($key) => is_string($key) && str_contains($key, ':'))
            ->values()
            ->all();
    }

    /**
     * Filters skip their relay unless the event lists their key, but the list only holds
     * the sending collection's kept keys. A collection that kept other condition keys (a
     * second collection on the page) re-sends the list with its own keys added, so only the
     * filters managing those keys relay. The list grows with every re-send and a collection
     * whose keys are all listed doesn't re-send, so it can't loop. Bare events never re-send.
     */
    #[On('clear-all-filters')]
    public function resetPaginationOnClearAll(): void
    {
        $this->clearAllFilterParams();

        $keptParamKeys = func_get_args()[0] ?? null;

        if (is_array($keptParamKeys) && array_diff($this->keptConditionParamKeys(), $keptParamKeys) !== []) {
            $this->dispatch('clear-all-filters', array_values(array_unique([...$keptParamKeys, ...$this->keptConditionParamKeys()])), ...array_slice(func_get_args(), 1));
        }
    }

    public function entries()
    {
        $entries = (new Entries($this->generateParams()))->get();

        $entries = $this->runHooks('livewire-fetched-entries', $entries);

        // Update the URL if using custom query string
        $this->updateCustomQueryStringUrl();

        if ($this->paginate && $entries instanceof LengthAwarePaginator) {
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
        // The label lets the bundled LfCount view update in the browser with the locale's plural form.
        $this->dispatch('entries-updated',
            count: $this->entriesCount,
            active: $this->activeFilters,
            entriesLabel: trans_choice('statamic-livewire-filters::ui.entries', (int) $this->entriesCount),
        );
    }
}
