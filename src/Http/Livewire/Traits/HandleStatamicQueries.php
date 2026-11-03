<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Illuminate\Support\Str;
use Reach\StatamicLivewireFilters\Exceptions\BlueprintNotFoundException;
use Reach\StatamicLivewireFilters\Exceptions\FieldNotFoundException;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Stache;
use Statamic\Facades\Taxonomy;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Stache\Query\TermQueryBuilder;

trait HandleStatamicQueries
{
    protected function getTaxonomyTerms($taxonomy_handle)
    {
        $siteHandle = Site::current()->handle();

        return Blink::once("statamic-livewire-filters.taxonomy-terms.{$siteHandle}.{$taxonomy_handle}", function () use ($taxonomy_handle, $siteHandle) {
            $taxonomy = Taxonomy::findByHandle($taxonomy_handle);

            $query = $taxonomy->queryTerms();

            if ($query instanceof TermQueryBuilder) {
                $indexSite = $taxonomy->sites()->contains($siteHandle) ? $siteHandle : $taxonomy->sites()->first();

                return $query->where('site', $indexSite)->pluck('title', 'id')
                    ->mapWithKeys(fn ($title, $id) => [Str::after($id, '::') => $title]);
            }

            return $query->get()
                ->unique(fn ($term) => $term->inDefaultLocale()->slug())
                ->mapWithKeys(function ($term) use ($siteHandle) {
                    $default = $term->inDefaultLocale();

                    return [
                        $default->slug() => ($term->in($siteHandle) ?? $default)->title(),
                    ];
                });
        });
    }

    protected function getCollectionEntries($collection_handle)
    {
        $siteHandle = Site::current()->handle();
        $useOriginId = config('statamic-livewire-filters.use_origin_id_for_entries_field');
        $cacheKey = sprintf(
            'statamic-livewire-filters.collection-entries.%s.%s.%s',
            $siteHandle,
            $collection_handle,
            $useOriginId ? 'origin' : 'entry'
        );

        return Blink::once($cacheKey, function () use ($collection_handle, $siteHandle, $useOriginId) {
            $query = Entry::query()
                ->where('collection', $collection_handle)
                ->where('site', $siteHandle)
                ->whereStatus('published');

            // Read titles from the collection's own store: a keyed pluck through the
            // aggregate entries store would renumber numeric entry ids.
            if ($query instanceof EntryQueryBuilder) {
                $ids = $query->pluck('id');
                $store = Stache::store('entries')->store($collection_handle);
                $titles = $store->getItemValues($ids, 'title', 'id');

                if (! $useOriginId) {
                    return $titles;
                }

                $origins = $store->getItemValues($ids, 'origin', 'id');

                return $titles->mapWithKeys(fn ($title, $id) => [($origins[$id] ?? null) ?: $id => $title]);
            }

            return $query
                ->get()
                ->mapWithKeys(function ($entry) use ($useOriginId) {
                    if ($useOriginId) {
                        return [
                            $entry->hasOrigin() ? $entry->origin()->id() : $entry->id() => $entry->title,
                        ];
                    }

                    return [
                        $entry->id() => $entry->title,
                    ];
                });
        });
    }

    public function getStatamicBlueprint()
    {
        $blueprint = Blink::once(
            'statamic-livewire-filters.blueprint.'.$this->collection.'.'.$this->blueprint,
            fn () => Blueprint::find('collections.'.$this->collection.'.'.$this->blueprint)
        );

        if ($blueprint) {
            return $blueprint;
        }

        throw new BlueprintNotFoundException($this->blueprint);
    }

    public function getStatamicField($blueprint, $field_handle = null)
    {
        $handle = $field_handle ?? $this->field;
        if ($field = $blueprint->field($handle)) {
            return $field;
        }
        throw new FieldNotFoundException($handle, $this->blueprint);
    }
}
