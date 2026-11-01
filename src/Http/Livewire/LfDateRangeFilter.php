<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Statamic\Facades\Site;

class LfDateRangeFilter extends Component
{
    use Traits\IsLivewireFilter;

    /**
     * gte/lte compare dates as strings, and Statamic's front end stringifies
     * Carbon with the site's date_format, so they can't bound a date range.
     * is_after/is_before parse the value and compare instants, but are
     * exclusive: each end is sent one second outside its local day, in UTC.
     */
    public const MODIFIER = 'is_after|is_before';

    public const PARAM_FORMAT = 'Y-m-d\TH:i:s\Z';

    public const PRESETS = ['today', '7days', '30days', 'this_month', 'this_year'];

    #[Locked]
    public $view = 'lf-date-range';

    /** Local (app timezone) `Y-m-d` date, or '' when the end is open. */
    public $selectedFrom = '';

    public $selectedTo = '';

    #[Locked]
    public $presets = '';

    /** Alpine Calendar / Carbon isoFormat tokens, used by the inputs and the tag chips. */
    #[Locked]
    public $format = 'DD/MM/YYYY';

    public function mount()
    {
        $this->condition = 'dual_range';
        $this->modifier = self::MODIFIER;

        $this->dispatch('date-range-filter-registered', field: $this->field, format: $this->format)
            ->to(LfTags::class);
    }

    public function updatedSelectedFrom()
    {
        $this->dispatchEvent();
    }

    public function updatedSelectedTo()
    {
        $this->dispatchEvent();
    }

    public function applyPreset($preset)
    {
        if (! in_array($preset, $this->presetOptions, true)) {
            return;
        }

        [$from, $to] = $this->presetRange($preset);
        $this->selectedFrom = $from->toDateString();
        $this->selectedTo = $to->toDateString();

        $this->dispatchEvent();
    }

    public function dispatchEvent()
    {
        $this->selectedFrom = $this->normalizeDate($this->selectedFrom);
        $this->selectedTo = $this->normalizeDate($this->selectedTo);

        if ($this->selectedFrom === '' && $this->selectedTo === '') {
            $this->clearFilters();

            return;
        }

        if ($this->selectedFrom !== '' && $this->selectedTo !== '' && $this->selectedFrom > $this->selectedTo) {
            [$this->selectedFrom, $this->selectedTo] = [$this->selectedTo, $this->selectedFrom];
        }

        $this->dispatch('filter-updated',
            field: $this->field,
            condition: $this->condition,
            payload: [
                'min' => $this->selectedFrom === '' ? null : self::dateToParam($this->selectedFrom, isStart: true),
                'max' => $this->selectedTo === '' ? null : self::dateToParam($this->selectedTo, isStart: false),
            ],
            modifier: $this->modifier,
        )
            ->to(LivewireCollection::class);
    }

    #[Computed(persist: true)]
    public function filterOptions()
    {
        return collect($this->statamic_field)->only(['earliest_date', 'latest_date'])->all();
    }

    #[Computed]
    public function presetOptions(): array
    {
        return array_values(array_intersect(explode('|', (string) $this->presets), self::PRESETS));
    }

    #[Computed]
    public function activePreset(): ?string
    {
        foreach ($this->presetOptions as $preset) {
            [$from, $to] = $this->presetRange($preset);

            if ($from->toDateString() === $this->selectedFrom && $to->toDateString() === $this->selectedTo) {
                return $preset;
            }
        }

        return null;
    }

    public function calendarLocale(): string
    {
        return str_replace('_', '-', Site::current()->locale());
    }

    #[On('clear-all-filters')]
    public function clear()
    {
        $this->selectedFrom = '';
        $this->selectedTo = '';
        $this->clearFilters();
    }

    #[On('clear-option')]
    public function clearOption($tag)
    {
        if ($tag['field'] !== $this->field) {
            return;
        }

        $paramKeys = $this->getParamKey();

        if ($paramKeys['min'] === $tag['field'].':'.$tag['condition']) {
            $this->selectedFrom = '';
        }

        if ($paramKeys['max'] === $tag['field'].':'.$tag['condition']) {
            $this->selectedTo = '';
        }

        $this->dispatchEvent();
    }

    #[On('preset-params')]
    public function setPresetValues($params)
    {
        $paramKeys = $this->getParamKey();

        if (isset($params[$paramKeys['min']])) {
            $this->selectedFrom = self::paramToDate($params[$paramKeys['min']], isStart: true)?->toDateString() ?? '';
        }

        if (isset($params[$paramKeys['max']])) {
            $this->selectedTo = self::paramToDate($params[$paramKeys['max']], isStart: false)?->toDateString() ?? '';
        }
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function presetRange(string $preset): array
    {
        $today = Carbon::now(config('app.timezone'))->startOfDay();

        return match ($preset) {
            'today' => [$today, $today->copy()],
            '7days' => [$today->copy()->subDays(6), $today],
            '30days' => [$today->copy()->subDays(29), $today],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'this_year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
        };
    }

    protected function normalizeDate($value): string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }

        $date = Carbon::createFromFormat('!Y-m-d', $value, config('app.timezone'));

        return $date && $date->toDateString() === $value ? $value : '';
    }

    /**
     * A local `Y-m-d` day as its exclusive bound: the second before it starts,
     * or the moment it ends, in UTC.
     */
    public static function dateToParam(string $date, bool $isStart): string
    {
        $day = Carbon::createFromFormat('!Y-m-d', $date, config('app.timezone'));

        return ($isStart ? $day->subSecond() : $day->addDay())->utc()->format(self::PARAM_FORMAT);
    }

    /**
     * The local day an exclusive bound from dateToParam() stands for.
     */
    public static function paramToDate($value, bool $isStart): ?Carbon
    {
        if (! is_string($value)) {
            return null;
        }

        try {
            $bound = Carbon::parse($value, 'UTC');
        } catch (\Throwable) {
            return null;
        }

        return ($isStart ? $bound->addSecond() : $bound->subSecond())
            ->setTimezone(config('app.timezone'))
            ->startOfDay();
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.filters.'.$this->view);
    }
}
