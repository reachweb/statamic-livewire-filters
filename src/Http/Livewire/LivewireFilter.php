<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Shared behaviour for the filter components. Subclasses declare their own
 * `#[Locked] public $view` and `$selected`; Livewire reads property and method
 * attributes from the declaring class only, so a subclass that redeclares one
 * of these properties or #[On] methods must repeat its attribute.
 */
abstract class LivewireFilter extends Component implements FilterContract
{
    use Traits\IsLivewireFilter;

    /** The value sent as `payload` in `filter-updated`. */
    protected function payload(): mixed
    {
        return $this->selected;
    }

    /** The value `$selected` takes when the filter is cleared. */
    protected function emptyValue(): mixed
    {
        return '';
    }

    /** Restore `$selected` from the collection's preset params. */
    protected function applyPreset(array $params): void
    {
        if (array_key_exists($this->getParamKey(), $params)) {
            $this->selected = $params[$this->getParamKey()];
        }
    }

    public function updatedSelected()
    {
        if (method_exists($this, 'rules') && config('statamic-livewire-filters.validate_filter_values')) {
            $this->validate();
        }

        $this->dispatchFilterUpdated();
    }

    /** @param  array<string, mixed>  $extra  Additional named event params, such as `boundsSelection`. */
    protected function dispatchFilterUpdated(array $extra = []): void
    {
        $this->dispatch('filter-updated', ...[
            'field' => $this->field,
            'condition' => $this->condition,
            'payload' => $this->payload(),
            'modifier' => $this->modifier,
            ...$extra,
        ])
            ->to(LivewireCollection::class);
    }

    #[On('clear-all-filters')]
    public function clear()
    {
        $this->selected = $this->emptyValue();
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
        $this->applyPreset($params);
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.filters.'.$this->view);
    }
}
