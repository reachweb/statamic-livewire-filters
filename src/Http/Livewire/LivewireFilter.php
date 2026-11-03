<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

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
 *
 * Listeners skip the render when nothing the view prints changed in the request.
 * An override of clear(), the owning branch of clearOption(), or any other handler
 * that changes rendered state outside a client property update must call
 * markFilterStateChanged(), or a no-op event pooled into the same request will
 * suppress its render.
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
        // Server-side callers such as clearOption() bypass the updating hook; mark before
        // validate() can throw so the new selection and its error still render.
        $this->markFilterStateChanged();

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
    public function clear($keptParamKeys = null)
    {
        $this->selected = $this->emptyValue();
        $this->markFilterStateChanged();

        if ($this->clearAllNeedsRelay($keptParamKeys)) {
            $this->clearFilters();
        }
    }

    #[On('clear-option')]
    public function clearOption($tag)
    {
        if ($tag['field'] === $this->field) {
            $this->clear();

            return;
        }

        $this->skipRenderIfFilterStateUnchanged();
    }

    #[On('preset-params')]
    public function setPresetValues($params)
    {
        $this->markFilterStateChanged();
        $this->applyPreset($params);
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.filters.'.$this->view);
    }
}
