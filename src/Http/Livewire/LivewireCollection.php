<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Jonassiewertsen\Livewire\WithPagination;
use Livewire\Component;
use Statamic\Tags\Collection\Entries;

class LivewireCollection extends Component
{
    use Traits\GenerateParams, WithPagination;

    public $params;

    public $view = 'livewire-collection';

    public function mount($params)
    {
        $this->setParameters($params);
    }

    public function setParameters($params)
    {
        if (array_key_exists('view', $params)) {
            $this->view = $params['view'];
            unset($params['view']);
        }
        $this->params = $params;
    }

    public function entries()
    {
        $entries = (new Entries($this->generateParams($this->params)))->get();
        $this->dispatch('entriesUpdated');
        if (isset($this->params['paginate'])) {
            return $this->withPagination('entries', $entries);
        }

        return ['entries' => $entries];
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.'.$this->view)->with([
            ...$this->entries(),
        ]);
    }
}
