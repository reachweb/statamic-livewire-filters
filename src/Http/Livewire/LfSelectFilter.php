<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

class LfSelectFilter extends LfRadioFilter
{
    public $view = 'lf-select';

    public bool $searchable = false;

    public string $placeholder = '';

    public function updatedSelected()
    {
        if ($this->selected === '') {
            $this->clear();

            return;
        }

        parent::updatedSelected();
    }
}
