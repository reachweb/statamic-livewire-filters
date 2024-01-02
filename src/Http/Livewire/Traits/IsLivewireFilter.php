<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Statamic\Facades\Blueprint;
use Livewire\Attributes\Computed;
use Reach\StatamicLivewireFilters\Exceptions\BlueprintNotFoundException;
use Reach\StatamicLivewireFilters\Exceptions\FieldNotFoundException;

trait IsLivewireFilter
{
    public $field;
    public $statamic_field;
    public $blueprint;
    public $collection;

    public function mountIsLivewireFilter($blueprint, $field)
    {
        list($collection, $blueprint) = explode('.', $blueprint);
        $this->collection = $collection;
        $this->blueprint = $blueprint;
        $this->field = $field;

        $this->initiateField();
    }

    public function initiateField()
    {
        $blueprint = $this->getStatamicBlueprint();
        $field = $this->getStatamicField($blueprint);
        $this->statamic_field = $field->toArray();
    }

    public function getStatamicBlueprint()
    {
        if ($blueprint = Blueprint::find('collections.'.$this->collection.'.'.$this->blueprint)) {
            return $blueprint;
        }
        throw new BlueprintNotFoundException($this->blueprint);
    }

    public function getStatamicField($blueprint)
    {
        if ($field = $blueprint->field($this->field)) {
            return $field;
        }
        throw new FieldNotFoundException($this->field, $this->blueprint);
    }

}