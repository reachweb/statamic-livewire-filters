<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Reach\StatamicLivewireFilters\Exceptions\InvalidRangeListBucketException;
use Reach\StatamicLivewireFilters\Exceptions\InvalidRangeListModifierException;

class LfRadioFilter extends LivewireFilter
{
    use Traits\HandleDependentOptions, Traits\IsSortable;

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

    /** What `$selected` holds while the collection has range list bounds that match no bucket. */
    public const UNMATCHED_RANGE = 'custom';

    public $selected = '';

    // Runs before the trait mount hooks, so initiateField() sees the bucket options.
    public function mount()
    {
        if (! is_string($this->buckets) || $this->buckets === '') {
            return;
        }

        // The counts only know these comparisons, so anything else must fail here, not in a counts request.
        if ($this->modifier !== null && $this->modifier !== 'any' && ! preg_match('/^gte?\|lte?$/', (string) $this->modifier)) {
            throw new InvalidRangeListModifierException($this->modifier);
        }

        $labels = array_map(fn ($bucket) => $this->bucketLabel($bucket), $this->rangeBuckets());

        // `options` keyed by bucket replaces the generated labels, e.g. to add a currency.
        $this->options = is_array($this->options)
            ? array_replace($labels, array_intersect_key($this->options, $labels))
            : $labels;

        $this->condition = 'dual_range';

        if ($this->view === 'lf-radio') {
            $this->view = 'lf-range-list';
        }
    }

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

    /**
     * The option the select views show while `$selected` is UNMATCHED_RANGE, keyed by that
     * value. Empty without buckets. A select has to show some option as chosen, and with
     * none for this value it falls back to its placeholder, which then can't be chosen.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function unmatchedRangeOption(): array
    {
        return $this->rangeBuckets()
            ? [self::UNMATCHED_RANGE => __('statamic-livewire-filters::ui.range_custom')]
            : [];
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
                throw new InvalidRangeListBucketException($bucket);
            }

            $min = ($matches[1] ?? '') === '' ? null : $matches[1];
            $max = ($matches[2] ?? '') === '' ? null : $matches[2];

            if ($min !== null && $max !== null && $min > $max) {
                throw new InvalidRangeListBucketException($bucket, 'The min is greater than the max.');
            }

            $buckets[$bucket] = ['min' => $min, 'max' => $max];
        }

        return $buckets;
    }

    protected function bucketLabel(array $bucket): string
    {
        // A range from 0 reads "Under max", except 0-0, which is a single value.
        if ($bucket['min'] === null || ($bucket['min'] == 0 && $bucket['max'] !== null && $bucket['max'] != 0)) {
            return __('statamic-livewire-filters::ui.range_under', ['max' => $bucket['max']]);
        }

        if ($bucket['max'] === null) {
            return __('statamic-livewire-filters::ui.range_over', ['min' => $bucket['min']]);
        }

        return __('statamic-livewire-filters::ui.range_between', ['min' => $bucket['min'], 'max' => $bucket['max']]);
    }

    protected function payload(): mixed
    {
        $buckets = $this->rangeBuckets();

        if (! $buckets || $this->selected === '') {
            return $this->selected;
        }

        // Anything but a known bucket (validation turned off) clears the filter instead of sending a bad range.
        return is_string($this->selected) || is_int($this->selected) ? ($buckets[$this->selected] ?? '') : '';
    }

    // A URL selects the bucket whose bounds match its min/max params exactly.
    protected function applyPreset(array $params): void
    {
        if (! $buckets = $this->rangeBuckets()) {
            parent::applyPreset($params);

            return;
        }

        $paramKeys = $this->getParamKey();

        // `preset-params` can come from the client, so a non-scalar bound counts as missing.
        $preset = array_map(
            fn ($key) => is_scalar($params[$key] ?? null) ? (string) $params[$key] : null,
            $paramKeys,
        );

        if ($preset === ['min' => null, 'max' => null]) {
            return;
        }

        $bucket = array_search($preset, $buckets, true);

        // Bounds no bucket has (an old link, a tag preset) keep a value no option has: "All" is
        // then not checked over a filtered list, and choosing it sends the update that clears them.
        $this->selected = $bucket === false ? self::UNMATCHED_RANGE : (string) $bucket;
    }
}
