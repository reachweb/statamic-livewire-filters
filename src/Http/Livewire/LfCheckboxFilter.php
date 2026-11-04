<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

class LfCheckboxFilter extends LivewireFilter
{
    use Traits\HandleEntriesCount, Traits\IsSortable;

    #[Locked]
    public $view = 'lf-checkbox';

    #[Locked]
    public $options;

    public array $selected = [];

    public bool $searchable = false;

    public string $placeholder = '';

    /** Set by the bundled view in the commit whose filter-updated it already sent to the collection. */
    public bool $syncedByView = false;

    #[Computed]
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

    /**
     * The bundled view dispatches filter-updated from the browser, pooled into this
     * commit's request, so the server relay (and its extra round trip) is skipped.
     * A view without the flag, such as one published before it, still relays.
     */
    protected function dispatchFilterUpdated(array $extra = []): void
    {
        if ($this->syncedByView && $this->dispatchesFromView()) {
            $this->syncedByView = false;
            // The browser sent $selected as is (dispatchesFromView() rules out a custom payload).
            // Its filter-updated waits while a clearAll() is in flight, so a clear-all can still miss it.
            $this->lastSentPayload = $this->payload();

            return;
        }

        parent::dispatchFilterUpdated($extra);
    }

    /**
     * The browser can only send `$selected` as is, so a subclass that changes how the
     * selection becomes filter-updated keeps the server relay, even with the bundled view.
     */
    protected function dispatchesFromView(): bool
    {
        foreach (['payload', 'updatedSelected', 'dispatchFilterUpdated'] as $method) {
            $declaringClass = (new \ReflectionMethod($this, $method))->getDeclaringClass()->getName();

            if (! in_array($declaringClass, [self::class, LivewireFilter::class], true)) {
                return false;
            }
        }

        return true;
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

            return;
        }

        $this->skipRenderIfFilterStateUnchanged();
    }
}
