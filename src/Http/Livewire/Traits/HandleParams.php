<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Reach\StatamicLivewireFilters\Http\Livewire\LfTags;
use Reach\StatamicLivewireFilters\Support\BlockedQueryParams;
use Reach\StatamicLivewireFilters\Support\CustomQueryString;

trait HandleParams
{
    /** Request-scoped (never persisted): whether this request already applied a real state change. */
    protected bool $collectionStateChangedThisRequest = false;

    /**
     * Protected, not public: Livewire exposes every public method as a
     * client-callable action, and this method's $params argument is trusted
     * (it feeds locked properties like $collections and $allowedFilters via the
     * extract* helpers). It is only ever called from mount() with params the
     * caller has already run through sanitizeClientParams(); a public method
     * here would let a client call setParameters(['from' => 'secrets']) to
     * repoint the query or re-permit a blocked visibility param.
     */
    protected function setParameters($params)
    {
        if ($customUrlParams = $this->handleCustomQueryStringParams()) {
            $params = $this->mergeParameters($params, $this->sanitizeClientParams($customUrlParams, $params));
        }
        $paramsCollection = collect($params);

        $this->extractCollectionKeys($paramsCollection);
        $this->extractView($paramsCollection);
        $this->extractLazyPlaceholder($paramsCollection);
        $this->extractPagination($paramsCollection);
        $this->extractInfiniteScroll($paramsCollection);
        $this->extractAllowedFilters($paramsCollection);

        $this->params = $paramsCollection->all();
        $this->handlePresetParams();
    }

    protected function handleCustomQueryStringParams(): array|bool
    {
        if (CustomQueryString::enabled() && request()->has('params')) {
            // Get and validate params
            $params = request()->validate([
                'params' => 'array',
                'params.*' => 'string|max:255',
            ])['params'] ?? [];

            return collect($params)
                ->map(function ($value) {
                    $value = htmlspecialchars($value);
                    $value = strip_tags($value);
                    $value = Str::before($value, '?');

                    // Additional sanitization
                    return preg_replace('/[<>\'";]/', '', $value);
                })
                ->filter()
                ->all();
        }

        return false;
    }

    protected function getParamsCount(): int
    {
        return collect($this->params)->reject(function ($value, $key) {
            if ($key === 'sort' || $key === 'query_scope') {
                return true;
            }
            if ($key === 'resrv_search:resrv_availability') {
                if (isset($value['dates']) && count($value['dates']) === 0) {
                    return true;
                }
            }
        })->count();
    }

    protected function extractCollectionKeys($paramsCollection)
    {
        $collectionKeys = ['from', 'in', 'folder', 'use', 'collection'];

        foreach ($collectionKeys as $key) {
            if ($paramsCollection->has($key)) {
                $this->collections = $paramsCollection->pull($key);
            }
        }
    }

    protected function extractView($paramsCollection)
    {
        if ($paramsCollection->has('view')) {
            $this->view = $paramsCollection->pull('view');
        }
    }

    protected function extractLazyPlaceholder($paramsCollection)
    {
        if ($paramsCollection->has('lazy-placeholder')) {
            $this->lazyPlaceholder = $paramsCollection->pull('lazy-placeholder');
        }
    }

    protected function extractPagination($paramsCollection)
    {
        if ($paramsCollection->has('paginate')) {
            $this->paginate = $paramsCollection->pull('paginate');
        }

        // Resolve the legacy `paginate="true"` form: the page size comes from `limit`.
        // Without a limit Statamic treats paginate=true as no pagination, so match that
        // (mirrors allowLegacyStylePaginationLimiting) rather than handing a bare boolean
        // to the paginator, which would crash withPagination() on a plain collection.
        if ($this->paginate === true) {
            $this->paginate = $paramsCollection->has('limit') ? (int) $paramsCollection->pull('limit') : false;
        }
    }

    protected function paginationPageName(): string
    {
        return $this->params['page_name'] ?? 'page';
    }

    protected function extractInfiniteScroll($paramsCollection)
    {
        if ($paramsCollection->has('infinite_scroll')) {
            $this->infiniteScroll = filter_var($paramsCollection->pull('infinite_scroll'), FILTER_VALIDATE_BOOLEAN);
        }
    }

    protected function extractAllowedFilters($paramsCollection)
    {
        if ($paramsCollection->has('allowed_filters')) {
            $this->allowedFilters = collect(explode('|', $paramsCollection->pull('allowed_filters')));
        }
    }

    protected function handleCondition($field, $condition, $payload)
    {
        $paramKey = $this->generateParamKey($field, $condition);
        $this->params[$paramKey] = $this->toPipeSeparatedString($payload);

        $this->dispatchParamsUpdated();
    }

    protected function handleTaxonomyCondition($field, $payload, $modifier)
    {
        $paramKey = $this->generateParamKey($field, 'taxonomy', $modifier);
        $this->params[$paramKey] = $this->toPipeSeparatedString($payload);

        $this->dispatchParamsUpdated();
    }

    // TODO: improve Resrv detection
    protected function handleQueryScopeCondition($field, $payload, $modifier)
    {
        $queryScopeKey = 'query_scope';
        $paramKey = $this->generateParamKey($field, 'query_scope', $modifier);

        if (isset($this->params[$queryScopeKey])) {
            $existingScopes = collect(explode('|', $this->params[$queryScopeKey]));
            if (! $existingScopes->contains($modifier)) {
                $existingScopes->push($modifier);
            }
            $this->params[$queryScopeKey] = $existingScopes->implode('|');
        } else {
            $this->params[$queryScopeKey] = $modifier;
        }

        $this->params[$paramKey] = $field === 'resrv_availability' ? $payload : $this->toPipeSeparatedString($payload);

        $this->dispatchParamsUpdated();
    }

    protected function handleDualRangeCondition($field, $payload, $modifier)
    {
        $paramKeys = $this->generateParamKey($field, 'dual_range', $modifier);

        $this->params[$paramKeys['min']] = $payload['min'];
        $this->params[$paramKeys['max']] = $payload['max'];

        $this->dispatchParamsUpdated();
    }

    #[On('clear-filter')]
    public function clearFilter($field, $condition, $modifier): void
    {
        if (! $this->fieldExistsInParams($field, $condition, $modifier)) {
            $this->skipRenderIfCollectionStateUnchanged();

            return;
        }

        // Reset pagination when clearing a filter
        if (method_exists($this, 'resetPagination')) {
            $this->resetPagination();
        }

        if ($condition === 'query_scope') {
            $paramKey = $this->generateParamKey($field, 'query_scope', $modifier);
            unset($this->params[$paramKey]);

            $this->removeScopeFromRegistryIfUnused($modifier);

            $this->dispatchParamsUpdated();

            return;
        }

        $paramKey = $this->generateParamKey($field, $condition, $modifier);

        if (is_array($paramKey)) {
            // Handle dual_range case which returns an array of keys
            unset($this->params[$paramKey['min']], $this->params[$paramKey['max']]);
        } else {
            unset($this->params[$paramKey]);
        }

        $this->dispatchParamsUpdated();
    }

    protected function removeScopeFromRegistryIfUnused(string $modifier): void
    {
        if (! isset($this->params['query_scope']) || ! is_string($this->params['query_scope'])) {
            return;
        }

        $existingParams = collect($this->params)->filter(function ($value, $key) use ($modifier) {
            return Str::startsWith($key, $modifier.':');
        });

        if ($existingParams->isNotEmpty()) {
            return;
        }

        $existingScopes = collect(explode('|', $this->params['query_scope']))
            ->filter(fn ($scope) => $scope !== $modifier);

        // If there are no more scopes, let's remove the whole query_scope key,
        // otherwise, let's update the query_scope key with the remaining scopes
        if ($existingScopes->isEmpty()) {
            unset($this->params['query_scope']);
        } else {
            $this->params['query_scope'] = $existingScopes->implode('|');
        }
    }

    public function fieldExistsInParams($field, $condition, $modifier): bool
    {
        $paramKey = $this->generateParamKey($field, $condition, $modifier);

        if (is_array($paramKey)) {
            // Handle dual_range case which returns an array of keys
            return isset($this->params[$paramKey['min']]) || isset($this->params[$paramKey['max']]);
        }

        return isset($this->params[$paramKey]);
    }

    /**
     * Fast path for the tags component: remove the tag's value from params in the
     * same round trip that handles the ✕ click, instead of waiting for the owning
     * filter to hear `clear-option` and relay the change back. The filters still
     * receive `clear-option` and still relay after resetting their UI — those
     * relays land as no-ops via the redundancy guards. When the tag cannot be
     * resolved to exactly one param key (range/dual-range params reset to bound
     * values only the filter knows, or the key is genuinely ambiguous), nothing is
     * touched here and the relay handles it exactly as before.
     */
    #[On('clear-option')]
    public function clearOptionParam($tag): void
    {
        $target = is_array($tag) ? $this->resolveTagParamTarget($tag) : null;

        if ($target === null) {
            $this->skipRenderIfCollectionStateUnchanged();

            return;
        }

        if (method_exists($this, 'resetPagination')) {
            $this->resetPagination();
        }

        $this->removeValueFromParam($target['key'], (string) $tag['value'], $target['scope']);

        $this->dispatchParamsUpdated();
    }

    /**
     * Map a tag (field/value/condition) to the single param key it came from.
     * Taxonomy tags carry the modifier in `condition`, so `taxonomy:field:modifier`
     * is checked before the plain `field:condition` key; query-scope tags drop the
     * scope name, so it is recovered from the query_scope registry. Returns null
     * unless exactly one candidate matches — ambiguity falls back to the filter relay.
     *
     * @return array{key: string, scope: ?string}|null
     */
    protected function resolveTagParamTarget(array $tag): ?array
    {
        $field = $tag['field'] ?? null;
        $value = $tag['value'] ?? null;
        $condition = $tag['condition'] ?? null;

        if (! is_string($field) || $field === '' || ! is_string($condition) || $condition === '' || ! is_scalar($value)) {
            return null;
        }

        $value = (string) $value;

        if ($condition === 'query_scope') {
            return $this->resolveQueryScopeTagTarget($field, $value);
        }

        $candidates = [];

        $taxonomyKey = 'taxonomy:'.$field.':'.$condition;
        if ($this->paramContainsValue($taxonomyKey, $value)) {
            $candidates[] = ['key' => $taxonomyKey, 'scope' => null];
        }

        $conditionKey = $field.':'.$condition;
        if ($this->paramContainsValue($conditionKey, $value) && ! $this->fieldHasMultipleConditionParams($field)) {
            $candidates[] = ['key' => $conditionKey, 'scope' => null];
        }

        return count($candidates) === 1 ? $candidates[0] : null;
    }

    /**
     * @return array{key: string, scope: string}|null
     */
    protected function resolveQueryScopeTagTarget(string $field, string $value): ?array
    {
        if (! isset($this->params['query_scope']) || ! is_string($this->params['query_scope'])) {
            return null;
        }

        $matches = [];

        foreach (array_unique(explode('|', $this->params['query_scope'])) as $scope) {
            if ($this->paramContainsValue($scope.':'.$field, $value)) {
                $matches[] = ['key' => $scope.':'.$field, 'scope' => $scope];
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    protected function paramContainsValue(string $key, string $value): bool
    {
        return isset($this->params[$key])
            && is_string($this->params[$key])
            && in_array($value, explode('|', $this->params[$key]), true);
    }

    /**
     * A field whose params hold more than one `field:condition` key is either a
     * dual-range pair (whose removal semantics only the filter knows) or two
     * independent filters on one field — both must go through the relay.
     */
    protected function fieldHasMultipleConditionParams(string $field): bool
    {
        return collect($this->params)
            ->keys()
            ->filter(fn ($key) => is_string($key) && Str::startsWith($key, $field.':'))
            ->count() > 1;
    }

    protected function removeValueFromParam(string $key, string $value, ?string $scope): void
    {
        $values = collect(explode('|', $this->params[$key]))
            ->filter(fn ($existing) => $existing !== $value);

        if ($values->isNotEmpty()) {
            $this->params[$key] = $values->implode('|');

            return;
        }

        unset($this->params[$key]);

        if ($scope !== null) {
            $this->removeScopeFromRegistryIfUnused($scope);
        }
    }

    /**
     * Fully sanitize client-supplied URL params before they reach mount-time
     * extractions or public component state: drop tag-only control keys, then drop
     * blocked query params (honoring the tag's own allowed_filters). Without this a
     * URL `limit` would be pulled into locked $paginate by the legacy pagination path,
     * and blocked keys would linger in $this->params as phantom active-filter state.
     *
     * @param  array<string, mixed>  $clientParams
     * @param  array<string, mixed>  $tagParams
     * @return array<string, mixed>
     */
    protected function sanitizeClientParams(array $clientParams, array $tagParams): array
    {
        $allowed = isset($tagParams['allowed_filters']) && is_string($tagParams['allowed_filters'])
            ? collect(explode('|', $tagParams['allowed_filters']))
            : null;

        return BlockedQueryParams::strip($this->rejectTagOnlyParams($clientParams), $allowed);
    }

    /**
     * Keys that setParameters() extracts into locked component properties may only
     * come from the tag — URL-hydrated state is client input and must not reach them.
     */
    protected function rejectTagOnlyParams(array $params): array
    {
        return array_diff_key($params, array_flip([
            'from', 'in', 'folder', 'use', 'collection',
            'view', 'lazy-placeholder', 'paginate', 'infinite_scroll', 'allowed_filters',
        ]));
    }

    protected function mergeParameters($params, $urlParams): array
    {
        if (isset($params['query_scope']) && isset($urlParams['query_scope'])) {
            $urlParams['query_scope'] = collect(explode('|', $params['query_scope']))
                ->merge(explode('|', $urlParams['query_scope']))
                ->unique()
                ->implode('|');
        }

        return array_merge($params, $urlParams);
    }

    protected function toPipeSeparatedString($payload): string
    {
        return is_array($payload) ? implode('|', $payload) : $payload;
    }

    protected function handlePresetParams()
    {
        $params = collect($this->params);
        $collectionKeys = ['from', 'in', 'folder', 'use', 'collection'];
        $restOfParams = $params->except($collectionKeys);
        if ($restOfParams->isNotEmpty()) {
            $this->dispatch('preset-params', $restOfParams->all());
        }
    }

    #[Renderless, On('preset-params')]
    public function updateCustomQueryStringUrl(): void
    {
        $prefix = CustomQueryString::prefix();

        if ($prefix === false) {
            return;
        }

        $aliases = $this->getConfigAliases();

        // Only include params that have aliases configured
        $segments = collect($this->params)
            ->filter(fn ($value, $key) => isset($aliases[$key]))
            ->map(function ($value, $key) use ($aliases) {
                $urlKey = $aliases[$key];

                // Convert pipe-separated values to comma-separated
                $urlValue = str_contains($value, '|')
                    ? str_replace('|', ',', $value)
                    : $value;

                return [$urlKey, $urlValue];
            })
            ->flatten()
            ->values();

        $path = $segments->isEmpty()
            ? ''
            : $prefix.'/'.$segments->implode('/');

        // Separate path and query string from currentPath
        $currentPathOnly = Str::before($this->currentPath, '?');
        $existingQueryString = Str::contains($this->currentPath, '?')
            ? Str::after($this->currentPath, '?')
            : '';

        // Normalize the base path and remove any existing custom query string segments.
        $currentPathTrimmed = $this->stripCustomQueryStringPrefix($currentPathOnly);

        $fullPath = $path
            ? ($currentPathTrimmed ? $currentPathTrimmed.'/' : '').trim($path, '/')
            : ($currentPathTrimmed ?: '/');

        $newUrl = url($fullPath);

        // Build query string parameters
        $queryParams = [];

        // Preserve existing query string parameters (e.g., tracking params like _gl)
        if ($existingQueryString) {
            parse_str($existingQueryString, $queryParams);
        }

        // Manage the page param under the configured page_name when we own pagination.
        if ($this->paginate && method_exists($this, 'getPage')) {
            $pageName = $this->paginationPageName();

            // Remove paginator property paths leaked by older releases. PHP normalizes
            // dots in query-string keys to underscores when parse_str() is used.
            unset($queryParams['paginators_'.$pageName]);
            if (isset($queryParams['paginators']) && is_array($queryParams['paginators'])) {
                unset($queryParams['paginators'][$pageName]);

                if ($queryParams['paginators'] === []) {
                    unset($queryParams['paginators']);
                }
            }

            $currentPage = $this->getPage($pageName);
            if ($currentPage > 1) {
                $queryParams[$pageName] = $currentPage;
            } else {
                unset($queryParams[$pageName]);
            }
        }

        // Append query string if we have parameters
        if (! empty($queryParams)) {
            $newUrl .= '?'.http_build_query($queryParams);
        }

        $this->dispatch(
            'update-url',
            newUrl: $newUrl,
            replace: ! request()->hasHeader('X-Livewire'),
        );
    }

    protected function getConfigAliases(): array
    {
        return collect(config('statamic-livewire-filters.custom_query_string_aliases', []))->transform(function ($value, $key) {
            if (str_contains($value, 'query_scope')) {
                [$scopeString, $scopeKey] = explode(':', $value, 2);

                return $scopeKey;
            }

            return $value;
        })->merge(['sort' => 'sort'])
            ->flip()
            ->all();
    }

    protected function stripCustomQueryStringPrefix(string $path): string
    {
        return CustomQueryString::stripPrefix($path);
    }

    protected function combinePathAndQuery(string $path, ?string $queryString = null): string
    {
        $normalizedPath = trim($path, '/');
        $normalizedQueryString = $queryString ? ltrim($queryString, '?') : '';

        if ($normalizedPath === '') {
            return $normalizedQueryString ? '/?'.$normalizedQueryString : '/';
        }

        return $normalizedQueryString
            ? $normalizedPath.'?'.$normalizedQueryString
            : $normalizedPath;
    }

    protected function getDualRangeConditions($modifer): array
    {
        $modifiers = ['gte', 'lte'];

        if ($modifer === 'any') {
            return $modifiers;
        }

        return explode('|', $modifer);
    }

    /**
     * Clear every runtime filter param immediately, without waiting for each
     * filter component to relay a `clear-filter` back. Mount-authored condition
     * params (tag or init-hook) are exempt: a filter component managing one still
     * clears it through its relay exactly as before, and one with no filter is a
     * fixed constraint that must survive — both match the pre-existing clear-all
     * outcome. `sort` is kept because LfSort never listened to `clear-all-filters`.
     */
    protected function clearAllFilterParams(): void
    {
        $removableKeys = collect($this->params)
            ->keys()
            ->filter(fn ($key) => is_string($key) && $this->isClearAllRemovableParamKey($key))
            ->values();

        if ($removableKeys->isEmpty()) {
            if ($this->paginationIsReset()) {
                $this->skipRenderIfCollectionStateUnchanged();
            } elseif (method_exists($this, 'resetPagination')) {
                $this->resetPagination();
            }

            return;
        }

        if (method_exists($this, 'resetPagination')) {
            $this->resetPagination();
        }

        foreach ($removableKeys as $key) {
            unset($this->params[$key]);
        }

        $this->pruneUnusedQueryScopes();

        $this->dispatchParamsUpdated();
    }

    protected function isClearAllRemovableParamKey(string $key): bool
    {
        if ($key === 'sort' || $key === 'query_scope' || $key === 'page_name') {
            return false;
        }

        if (in_array($key, $this->clearAllExemptParamKeys, true)) {
            return false;
        }

        return str_contains($key, ':');
    }

    protected function pruneUnusedQueryScopes(): void
    {
        if (! isset($this->params['query_scope']) || ! is_string($this->params['query_scope'])) {
            return;
        }

        foreach (array_unique(explode('|', $this->params['query_scope'])) as $scope) {
            $this->removeScopeFromRegistryIfUnused($scope);
        }
    }

    /**
     * Condition params present at mount came from the tag, the init hooks, or the
     * URL. Only the first two are exempt from the immediate clear-all sweep: they
     * are site-author constraints that today survive clear-all whenever no filter
     * component manages them. URL-hydrated params are user filter state and clear
     * immediately. Hook-authored means added or changed by the hooks, mirroring
     * captureHookAuthoredQueryParams().
     *
     * @param  array<string, mixed>  $tagParams
     * @param  array<string, mixed>  $paramsBeforeHooks
     */
    protected function captureClearAllExemptParams(array $tagParams, array $paramsBeforeHooks): void
    {
        $tagKeys = collect($tagParams)
            ->keys()
            ->filter(fn ($key) => is_string($key) && str_contains($key, ':'));

        $hookAuthoredKeys = collect($this->params)
            ->filter(fn ($value, $key) => is_string($key) && str_contains($key, ':')
                && (! array_key_exists($key, $paramsBeforeHooks) || $paramsBeforeHooks[$key] !== $value))
            ->keys();

        $this->clearAllExemptParamKeys = $tagKeys
            ->merge($hookAuthoredKeys)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * True when an incoming filter-updated would change neither params nor
     * pagination, so the whole update can be skipped. This is what makes the
     * relay a filter still sends after the collection already applied a direct
     * removal (clear-option / clear-all) harmless: it costs no query and no render.
     * Comparisons that cannot be proven equal report not-redundant, so anything
     * unusual falls through to the full (pre-existing) update path.
     */
    protected function filterUpdateIsRedundant($field, $condition, $payload, $modifier): bool
    {
        if (! $this->paginationIsReset()) {
            return false;
        }

        if ($payload === '' || $payload === null || $payload === []) {
            return ! $this->fieldExistsInParams($field, $condition, $modifier);
        }

        if ($condition === 'dual_range') {
            $paramKeys = $this->generateParamKey($field, $condition, $modifier);

            return is_array($payload)
                && isset($payload['min'], $payload['max'])
                && isset($this->params[$paramKeys['min']], $this->params[$paramKeys['max']])
                && $this->params[$paramKeys['min']] == $payload['min']
                && $this->params[$paramKeys['max']] == $payload['max'];
        }

        $paramKey = $this->generateParamKey($field, $condition, $modifier);

        if ($condition === 'query_scope') {
            $expected = $field === 'resrv_availability' ? $payload : $this->toPipeSeparatedString($payload);

            return isset($this->params['query_scope'])
                && is_string($this->params['query_scope'])
                && in_array($modifier, explode('|', $this->params['query_scope']), true)
                && isset($this->params[$paramKey])
                && $this->params[$paramKey] == $expected;
        }

        return isset($this->params[$paramKey])
            && $this->params[$paramKey] === $this->toPipeSeparatedString($payload);
    }

    protected function paginationIsReset(): bool
    {
        if ($this->infiniteScroll && (int) $this->paginate !== (int) $this->initialPaginate) {
            return false;
        }

        if ($this->paginate && method_exists($this, 'getPage')) {
            return (int) $this->getPage($this->paginationPageName()) === 1;
        }

        return true;
    }

    /**
     * One pooled Livewire request can carry several event calls for this component
     * (e.g. a real clear plus redundant relays from other filters). skipRender()
     * is component-wide for the request, so a no-op must never suppress the render
     * of a real change handled in the same request — regardless of call order.
     * The store reset clears a skip already set by an earlier no-op call (the flag
     * guards later ones) and, unlike forceRender(), exists on Livewire 3 too.
     */
    protected function markCollectionStateChanged(): void
    {
        $this->collectionStateChangedThisRequest = true;

        \Livewire\store($this)->set('skipRender', false);
    }

    protected function skipRenderIfCollectionStateUnchanged(): void
    {
        if (! $this->collectionStateChangedThisRequest) {
            $this->skipRender();
        }
    }

    protected function dispatchParamsUpdated(): void
    {
        $this->markCollectionStateChanged();

        if (config('statamic-livewire-filters.enable_filter_values_count')) {
            $this->dispatch('params-updated', $this->effectiveQueryParams());
        }

        // Dispatching to the tags component
        $this->dispatch('tags-updated', $this->params)->to(LfTags::class);
    }

    protected function generateParamKey(string $field, string $condition, ?string $modifier = null): string|array
    {
        if ($condition === 'query_scope') {
            return $modifier.':'.$field;
        }

        if ($condition === 'taxonomy') {
            return 'taxonomy:'.$field.':'.$modifier;
        }

        if ($condition === 'dual_range') {
            [$minModifier, $maxModifier] = $this->getDualRangeConditions($modifier);

            return [
                'min' => $field.':'.$minModifier,
                'max' => $field.':'.$maxModifier,
            ];
        }

        return $field.':'.$condition;
    }
}
