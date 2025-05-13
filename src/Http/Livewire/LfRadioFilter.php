<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class LfRadioFilter extends Component
{
    use Traits\HandleEntriesCount, Traits\IsLivewireFilter, Traits\IsSortable;

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

    public function updatedSelected()
    {
        if (config('statamic-livewire-filters.validate_filter_values')) {
            $this->validate();
        }

        $this->dispatch('filter-updated',
            field: $this->field,
            condition: $this->condition,
            payload: $this->selected,
            modifier: $this->modifier,
        )
            ->to(LivewireCollection::class);
    }

    public function rules()
    {
        return [
            'selected' => ['required', Rule::in(array_keys($this->filterOptions()))],
        ];
    }

    #[On('clear-all-filters')]
    public function clear()
    {
        $this->selected = '';
        $this->clearFilters();
    }

    #[On('clear-option')]
    public function clearOption($tag)
    {
        if ($tag['field'] === $this->field) {
            $this->clear();
        }
    }

    #[On('preset-params')]
    public function setPresetValues($params)
    {
        if (array_key_exists($this->getParamKey(), $params)) {
            $this->selected = $params[$this->getParamKey()];
        }
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.filters.'.$this->view);
    }
}
