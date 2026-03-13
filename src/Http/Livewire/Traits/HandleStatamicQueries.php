<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Reach\StatamicLivewireFilters\Exceptions\BlueprintNotFoundException;
use Reach\StatamicLivewireFilters\Exceptions\FieldNotFoundException;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;

trait HandleStatamicQueries
{
    protected function getTaxonomyTerms($taxonomy_handle)
    {
        $siteHandle = Site::current()->handle();

        return Blink::once("statamic-livewire-filters.taxonomy-terms.{$siteHandle}.{$taxonomy_handle}", function () use ($taxonomy_handle, $siteHandle) {
            $taxonomy = Taxonomy::findByHandle($taxonomy_handle);

            return $taxonomy->queryTerms()->get()
                ->unique(fn ($term) => $term->inDefaultLocale()->slug())
                ->flatMap(function ($term) use ($siteHandle) {
                    return [
                        $term->inDefaultLocale()->slug() => ($term->in($siteHandle) ?? $term->inDefaultLocale())->title(),
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
            return Entry::query()
                ->where('collection', $collection_handle)
                ->where('site', $siteHandle)
                ->whereStatus('published')
                ->get()
                ->flatMap(function ($entry) use ($useOriginId) {
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
