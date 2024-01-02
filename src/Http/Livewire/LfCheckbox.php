<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;

class LfCheckbox extends Component
{
    use Traits\IsLivewireFilter;

    public $view = 'lf-checkbox';
    public $options;
    public $selected = [];
    
    #[Computed]
    public function filterOptions()
    {
        if (is_array($this->options)) {
            return $this->options;
        } elseif ($this->options === 'auto') {
            return $this->statamic_field['options'];
        }
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.filters.'.$this->view);
    }
}
