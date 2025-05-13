<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class LfCheckboxFilter extends Component
{
    use Traits\HandleEntriesCount, Traits\IsLivewireFilter, Traits\IsSortable;

    public $view = 'lf-checkbox';

    #[Locked]
    public $options;

    public array $selected = [];

    public bool $searchable = false;

    public string $placeholder = '';

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

    #[On('clear-all-filters')]
    public function clear()
    {
        $this->selected = [];
        $this->clearFilters();
    }

    public function rules()
    {
        return [
            'selected' => ['array', Rule::in(array_keys($this->filterOptions()))],
        ];
    }

    #[On('clear-option')]
    public function clearOption($tag)
    {
        if ($tag['field'] === $this->field) {
            if (in_array($tag['value'], $this->selected)) {
                $this->selected = array_values(array_diff($this->selected, [$tag['value']]));
                $this->updatedSelected();
            }
        }
    }

    #[On('preset-params')]
    public function setPresetValues($params)
    {
        if (array_key_exists($this->getParamKey(), $params)) {
            $this->selected = explode('|', $params[$this->getParamKey()]);
        }
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.filters.'.$this->view);
    }
}
