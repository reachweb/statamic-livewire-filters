<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Reach\StatamicLivewireFilters\Exceptions\BlueprintNotFoundException;
use Reach\StatamicLivewireFilters\Exceptions\FieldNotFoundException;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Taxonomy;

trait HandleStatamicQueries
{
    protected function getTaxonomyTerms($taxonomy_handle)
    {
        $taxonomy = Taxonomy::findByHandle($taxonomy_handle);

        return $taxonomy->queryTerms()->get()->flatMap(function ($term) {
            return [
                $term->slug() => $term->title(),
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
