<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;

class LfRadioFilter extends Component
{
    use Traits\IsLivewireFilter;

    public $view = 'lf-radio';

    public $options;

    public $selected = '';

    #[Computed]
    public function filterOptions()
    {
        if (isset($this->statamic_field['options'])) {
            return $this->statamic_field['options'];
        } elseif (is_array($this->options)) {
            return $this->options;
        }
    }

    public function updatedSelected()
    {
        $this->dispatch('filter-updated',
            field: $this->field,
            condition: $this->condition,
            payload: $this->selected,
            command: 'replace',
            modifier: $this->modifier,
        )
            ->to(LivewireCollection::class);
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.filters.'.$this->view);
    }
}
