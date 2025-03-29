<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Reach\StatamicLivewireFilters\Exceptions\BlueprintNotFoundException;
use Reach\StatamicLivewireFilters\Exceptions\FieldNotFoundException;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;

trait HandleStatamicQueries
{
    protected function getTaxonomyTerms($taxonomy_handle)
    {
        $taxonomy = Taxonomy::findByHandle($taxonomy_handle);

        return $taxonomy->queryTerms()->get()->flatMap(function ($term) {
            return [
                $term->inDefaultLocale()->slug() => $term->in(Site::current()->handle())->title(),
            ];
        });
    }

    protected function getCollectionEntries($collection_handle)
    {
        return Entry::query()
            ->where('collection', $collection_handle)
            ->where('site', Site::current()->handle())
            ->whereStatus('published')
            ->get()
            ->flatMap(function ($entry) {
                if (config('statamic-livewire-filters.use_origin_id_for_entries_field')) {
                    return [
                        $entry->hasOrigin() ? $entry->origin()->id() : $entry->id() => $entry->title,
                    ];
                }

                return [
                    $entry->id() => $entry->title,
                ];
            });
    }

    public function getStatamicBlueprint()
    {
        if ($blueprint = Blueprint::find('collections.'.$this->collection.'.'.$this->blueprint)) {
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
