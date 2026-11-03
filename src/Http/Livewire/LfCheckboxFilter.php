<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

class LfCheckboxFilter extends LivewireFilter
{
    use Traits\HandleDependentOptions, Traits\IsSortable;

    #[Locked]
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

    public function rules()
    {
        return [
            'selected' => ['array', Rule::in(array_keys($this->filterOptions()))],
        ];
    }

    protected function emptyValue(): mixed
    {
        return [];
    }

    protected function applyPreset(array $params): void
    {
        if (array_key_exists($this->getParamKey(), $params)) {
            $this->selected = explode('|', $params[$this->getParamKey()]);
        }
    }

    // Removes only the cleared value; the other checked options stay selected.
    #[On('clear-option')]
    public function clearOption($tag)
    {
        if ($tag['field'] === $this->field && in_array($tag['value'], $this->selected)) {
            $this->selected = array_values(array_diff($this->selected, [$tag['value']]));
            $this->updatedSelected();
        }
    }
}
