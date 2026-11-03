<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

class LfRadioFilter extends LivewireFilter
{
    use Traits\HandleDependentOptions, Traits\IsSortable;

    #[Locked]
    public $view = 'lf-radio';

    #[Locked]
    public $options;

    public $selected = '';

    #[Computed(persist: true)]
    public function filterOptions(): array
    {
        if ($this->options !== null && is_array($this->options)) {
            return $this->options;
        }

        if (isset($this->statamic_field['options'])) {
            return $this->statamic_field['options'];
        }
    }

    public function rules()
    {
        return [
            'selected' => ['required', Rule::in(array_keys($this->filterOptions()))],
        ];
    }
}
