<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class LfSort extends Component
{
    use Traits\HandleStatamicQueries;

    public $view = 'lf-sort';

    public $collection;

    public $blueprint;

    public $fields;

    public $selected = '';

    public function mount($blueprint, $fields)
    {
        [$collection, $blueprint] = explode('.', $blueprint);
        $this->collection = $collection;
        $this->blueprint = $blueprint;
        $this->fields = $fields;
    }

    #[Computed(persist: true)]
    public function sortOptions()
    {
        return $this->getFieldNames()->flatMap(function ($item) {
            return [
                array_merge($item, ['dir' => 'asc']),
                array_merge($item, ['dir' => 'desc']),
            ];
        });
    }

    #[On('preset-params')]
    public function setPresetSort($params)
    {
        if (array_key_exists('sort', $params)) {
            $this->selected = $params['sort'];
        }
    }

    protected function getFieldNames()
    {
        $blueprint = $this->getStatamicBlueprint();

        return collect(explode('|', $this->fields))
            ->map(function ($field) use ($blueprint) {
                $statamic_field = $this->getStatamicField($blueprint, $field);

                return [
                    'value' => $statamic_field->handle(),
                    'label' => $statamic_field->display(),
                ];
            });
    }

    public function updatedSelected()
    {
        $this->dispatch('sort-updated', sort: $this->selected)
            ->to(LivewireCollection::class);
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.sort.'.$this->view);
    }
}
