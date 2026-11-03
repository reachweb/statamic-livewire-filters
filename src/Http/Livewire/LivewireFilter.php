<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Illuminate\Support\Arr;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Shared behaviour for the filter components. Subclasses declare their own
 * `#[Locked] public $view`; Livewire reads property and method attributes from
 * the declaring class only, so a subclass that redeclares a property or #[On]
 * method must repeat its attribute.
 *
 * updatedSelected(), clear() and the default payload(), emptyValue() and
 * applyPreset() assume a `$selected` property. A filter without one, like
 * LfDualRangeFilter with `selectedMin`/`selectedMax`, must override clear(),
 * payload() and applyPreset().
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

    /**
     * Deferred mode: the collection dropped this filter's queued change, so
     * restore the selection from the applied params, or clear it when none apply.
     *
     * @param  array<int, array{field: string, condition: string, modifier: ?string}>  $filters
     * @param  array<string, mixed>  $params
     */
    #[On('pending-filters-discarded')]
    public function discardPendingSelection($filters, $params)
    {
        $queuedByThisFilter = collect($filters)->contains(fn ($filter) => is_array($filter)
            && ($filter['field'] ?? null) === $this->field
            && ($filter['condition'] ?? null) === $this->condition
            && ($filter['modifier'] ?? null) == $this->modifier);

        if (! $queuedByThisFilter || ! is_array($params)) {
            $this->skipRender();

            return;
        }

        $hasAppliedParams = collect(Arr::wrap($this->getParamKey()))
            ->contains(fn ($key) => array_key_exists($key, $params));

        $hasAppliedParams ? $this->applyPreset($params) : $this->clear();
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.filters.'.$this->view);
    }
}
