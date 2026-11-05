<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Attributes\Locked;

class LfSelectFilter extends LfRadioFilter
{
    #[Locked]
    public $view = 'lf-select';

    public bool $searchable = false;

    public string $placeholder = '';

    // Unlike a radio group, a select can return to its empty placeholder option,
    // which must clear the filter instead of failing the `required` rule.
    public function updatedSelected()
    {
        if ($this->selected === '') {
            $this->clear();

            return;
        }

        parent::updatedSelected();
    }
}
