<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;
use Reach\StatamicLivewireFilters\Support\CountQueryPool;
use Reach\StatamicLivewireFilters\Support\FacetSpec;

/**
 * Faceted-query helpers shared by HandleEntriesCount and HandleFieldBounds;
 * a component must not compose both.
 */
trait HandleFieldValuesQuery
{
    protected function resolveCountFieldHandle(): string
    {
        $blueprint = $this->getStatamicBlueprint();

        return $this->getStatamicField($blueprint)->handle();
    }

    protected function removeCurrentFieldFromParams($params, $fieldHandle)
    {
        return FacetSpec::baseParams($params, $fieldHandle, $this->facetScope());
    }

    protected function facetScope(): ?string
    {
        return $this->condition === 'query_scope' ? $this->modifier : null;
    }

    /**
     * Facet engine: tells the collection this filter's facet so its queries batch it
     * with the other facets sharing a filter state. Called once, at mount.
     */
    protected function registerFacet(): void
    {
        if (! config('statamic-livewire-filters.enable_facet_engine') || ! $this->facetFeatureEnabled()) {
            return;
        }

        if (! $spec = FacetSpec::from($this->resolveCountFieldHandle(), $this->facetScope())) {
            return;
        }

        $pool = app(CountQueryPool::class);

        // A collection mounted later in this request reads the pool; one mounted earlier
        // has dehydrated already and learns the facet from the event, which travels in
        // the page load's first fan-out request.
        $pool->registerFacet($this->collection, $spec);

        if ($pool->collectionMountedFor($this->collection)) {
            $this->dispatch('register-facet', field: $spec[0], scope: $spec[1], collection: $this->collection)
                ->to(LivewireCollection::class);
        }
    }

    /**
     * Facet engine: batches the values of every facet the token carries, plus this
     * one, before this component reads its own.
     */
    protected function prefetchFacets(array $query): void
    {
        if (! config('statamic-livewire-filters.enable_facet_engine')) {
            return;
        }

        $specs = $query['facets'] ?? [];

        // Listeners whose spec the token carries share one memoized prefetch.
        if (($own = FacetSpec::from($this->resolveCountFieldHandle(), $this->facetScope())) && ! in_array($own, $specs, true)) {
            $specs[] = $own;
        }

        app(CountQueryPool::class)->prefetch($query['params'], $specs);
    }

    /**
     * Queries the tag's collections that have the field in a blueprint, falling back
     * to the locked collection. Pass only server-authenticated collections, never event params.
     */
    protected function getCountFieldValues(array $baseParams, string $fieldHandle, string|array|null $collections = null)
    {
        return app(CountQueryPool::class)->getFieldValues(
            $this->collectionsWithField($collections ?? $this->collection, $fieldHandle),
            $baseParams,
            $fieldHandle
        );
    }

    /**
     * @return array<int, string>
     */
    protected function collectionsWithField(string|array $collections, string $fieldHandle): array
    {
        return FacetSpec::collectionsWithField($collections, $fieldHandle) ?: [$this->collection];
    }
}
