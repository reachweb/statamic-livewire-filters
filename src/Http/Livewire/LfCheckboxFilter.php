<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Reach\StatamicLivewireFilters\Support\ParamKey;

class LfCheckboxFilter extends LivewireFilter
{
    use Traits\HandleEntriesCount, Traits\IsSortable {
        removeCurrentFieldFromParams as removeFieldFromCountParams;
    }

    #[Locked]
    public $view = 'lf-checkbox';

    #[Locked]
    public $options;

    public array $selected = [];

    public bool $searchable = false;

    public string $placeholder = '';

    /** Renders the "Match any / Match all" switch for taxonomy and `overlaps` filters. */
    #[Locked]
    public $logic_toggle = false;

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

    /** 'any' or 'all' while the logic toggle is shown, otherwise null. */
    public function logic(): ?string
    {
        if (! filter_var($this->logic_toggle, FILTER_VALIDATE_BOOLEAN) || $this->logicCounterpart() === null) {
            return null;
        }

        return ParamKey::isMatchAll($this->getParamKey()) ? 'all' : 'any';
    }

    /**
     * Moves the filter to its logic counterpart key (`taxonomy:field:any` <=> `:all`,
     * `field:overlaps` <=> `field:contains_all`). The locked condition and modifier
     * only ever change to that counterpart, never to a client-supplied value.
     */
    public function setLogic($logic): void
    {
        $current = $this->logic();

        if ($current === null || ! in_array($logic, ['any', 'all'], true) || $logic === $current) {
            return;
        }

        $this->switchToLogicCounterpart();

        if ($this->selected !== []) {
            $this->dispatchFilterUpdated(['logicSwitched' => true]);
        }
    }

    protected function logicCounterpart(): ?string
    {
        $paramKey = $this->getParamKey();

        return is_string($paramKey) ? ParamKey::logicCounterpart($paramKey) : null;
    }

    protected function switchToLogicCounterpart(): void
    {
        $counterpart = ParamKey::parse($this->logicCounterpart());

        $this->condition = $counterpart->condition;
        $this->modifier = $counterpart->modifier ?? $this->modifier;
    }

    /**
     * Match any counts each option as if this filter were unset. Match all narrows
     * with every ticked value, so its counts keep them: each count is what ticking
     * that option would leave.
     */
    protected function removeCurrentFieldFromParams($params, $fieldHandle)
    {
        if ($this->logic() === 'all') {
            return $params;
        }

        return $this->removeFieldFromCountParams($params, $fieldHandle);
    }

    protected function applyPreset(array $params): void
    {
        // A URL or preset may hold the other logic's key, e.g. `brand-all` in the URL
        if (! array_key_exists($this->getParamKey(), $params) && $this->logic() !== null
            && array_key_exists($this->logicCounterpart(), $params)) {
            $this->switchToLogicCounterpart();
        }

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
