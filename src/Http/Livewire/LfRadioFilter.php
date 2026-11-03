<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

class LfRadioFilter extends LivewireFilter
{
    use Traits\HandleEntriesCount, Traits\IsSortable;

    #[Locked]
    public $view = 'lf-radio';

    #[Locked]
    public $options;

    /**
     * Range list buckets, e.g. `0-100|100-200|200-`. Each bucket is one option
     * that filters the field with a min/max pair through the dual_range condition;
     * an empty side leaves that end open.
     */
    #[Locked]
    public $buckets;

    public $selected = '';

    // Runs before the trait mount hooks, so initiateField() sees the bucket options.
    public function mount()
    {
        if (! is_string($this->buckets) || $this->buckets === '') {
            return;
        }

        $this->condition = 'dual_range';
        $this->options = array_map(fn ($bucket) => $this->bucketLabel($bucket), $this->rangeBuckets());

        if ($this->view === 'lf-radio') {
            $this->view = 'lf-range-list';
        }
    }

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
            // The range list's "All" option selects '' to clear the bucket.
            'selected' => [$this->rangeBuckets() ? 'nullable' : 'required', Rule::in(array_keys($this->filterOptions()))],
        ];
    }

    /**
     * @return array<string, array{min: ?string, max: ?string}>
     */
    protected function rangeBuckets(): array
    {
        if (! is_string($this->buckets) || $this->buckets === '') {
            return [];
        }

        $number = '-?\d+(?:\.\d+)?';
        $buckets = [];

        foreach (explode('|', $this->buckets) as $bucket) {
            $bucket = trim($bucket);

            if (! preg_match("/^($number)?-($number)?$/", $bucket, $matches) || $bucket === '-') {
                throw new InvalidArgumentException("Invalid range list bucket [{$bucket}]. Use min-max, min- or -max.");
            }

            $buckets[$bucket] = [
                'min' => ($matches[1] ?? '') === '' ? null : $matches[1],
                'max' => ($matches[2] ?? '') === '' ? null : $matches[2],
            ];
        }

        return $buckets;
    }

    protected function bucketLabel(array $bucket): string
    {
        if ($bucket['min'] === null || ($bucket['min'] == 0 && $bucket['max'] !== null)) {
            return __('statamic-livewire-filters::ui.range_under', ['max' => $bucket['max']]);
        }

        if ($bucket['max'] === null) {
            return __('statamic-livewire-filters::ui.range_over', ['min' => $bucket['min']]);
        }

        return __('statamic-livewire-filters::ui.range_between', ['min' => $bucket['min'], 'max' => $bucket['max']]);
    }

    protected function payload(): mixed
    {
        if (! $this->rangeBuckets() || $this->selected === '') {
            return $this->selected;
        }

        // An unknown bucket (validation turned off) clears the filter instead of sending a bad range.
        return $this->rangeBuckets()[$this->selected] ?? '';
    }

    // A URL selects the bucket whose bounds match its min/max params exactly.
    protected function applyPreset(array $params): void
    {
        if (! $buckets = $this->rangeBuckets()) {
            parent::applyPreset($params);

            return;
        }

        $paramKeys = $this->getParamKey();

        if (! isset($params[$paramKeys['min']]) && ! isset($params[$paramKeys['max']])) {
            return;
        }

        $preset = [
            'min' => isset($params[$paramKeys['min']]) ? (string) $params[$paramKeys['min']] : null,
            'max' => isset($params[$paramKeys['max']]) ? (string) $params[$paramKeys['max']] : null,
        ];

        $this->selected = (string) array_search($preset, $buckets, true);
    }
}
