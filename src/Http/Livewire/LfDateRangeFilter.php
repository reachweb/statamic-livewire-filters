<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Statamic\Facades\Site;

class LfDateRangeFilter extends LivewireFilter
{
    /**
     * gte/lte compare dates as strings, and Statamic's front end stringifies
     * Carbon with the site's date_format, so they can't bound a date range.
     * is_after/is_before parse the value and compare instants, but are
     * exclusive: the start is sent as the second before its local day and the
     * end as the midnight after it, in UTC.
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

    /** Set once lf-tags has been told this field's chip format. */
    #[Locked]
    public bool $registeredWithTags = false;

    public function mount()
    {
        $this->condition = 'dual_range';
        $this->modifier = self::MODIFIER;
    }

    /**
     * lf-tags only needs the format once there is a chip to label, so this goes out
     * with the first selection or restored range, in the same browser tick as the
     * request it already causes, never as an extra request on page load.
     */
    protected function registerWithTags(): void
    {
        if ($this->registeredWithTags) {
            return;
        }

        $this->registeredWithTags = true;
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

    public function selectPreset($preset)
    {
        if (! in_array($preset, $this->presetOptions, true)) {
            return;
        }

        $this->markFilterStateChanged();
        [$this->selectedFrom, $this->selectedTo] = $this->presetRange($preset);

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

        $this->registerWithTags();
        $this->dispatchFilterUpdated();
    }

    protected function payload(): mixed
    {
        return [
            'min' => $this->selectedFrom === '' ? null : self::dateToParam($this->selectedFrom, isStart: true),
            'max' => $this->selectedTo === '' ? null : self::dateToParam($this->selectedTo, isStart: false),
        ];
    }

    #[Computed]
    public function filterOptions()
    {
        return collect($this->statamic_field)->only(['earliest_date', 'latest_date'])->all();
    }

    #[Computed]
    public function presetOptions(): array
    {
        return array_values(array_filter(
            array_intersect(explode('|', (string) $this->presets), self::PRESETS),
            fn ($preset) => $this->presetRange($preset) !== null,
        ));
    }

    #[Computed]
    public function activePreset(): ?string
    {
        foreach ($this->presetOptions as $preset) {
            if ($this->presetRange($preset) === [$this->selectedFrom, $this->selectedTo]) {
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
        $this->markFilterStateChanged();

        if ($this->clearAllNeedsRelay(...func_get_args())) {
            $this->clearFilters();
        }
    }

    #[On('clear-option')]
    public function clearOption($tag)
    {
        if (($tag['field'] ?? null) !== $this->field) {
            $this->skipRenderIfFilterStateUnchanged();

            return;
        }

        $this->markFilterStateChanged();

        $paramKeys = $this->getParamKey();
        $condition = $tag['condition'] ?? null;

        // A tag without a condition (user code) can't name its end: clear the range.
        if (! is_string($condition) || $paramKeys['min'] === $this->field.':'.$condition) {
            $this->selectedFrom = '';
        }

        if (! is_string($condition) || $paramKeys['max'] === $this->field.':'.$condition) {
            $this->selectedTo = '';
        }

        $this->dispatchEvent();
    }

    /**
     * Restores both ends from URL params (at mount) or tag presets (`preset-params`).
     */
    protected function applyPreset(array $params): void
    {
        $paramKeys = $this->getParamKey();

        if (isset($params[$paramKeys['min']])) {
            $this->selectedFrom = self::paramToDate($params[$paramKeys['min']], isStart: true)?->toDateString() ?? '';
        }

        if (isset($params[$paramKeys['max']])) {
            $this->selectedTo = self::paramToDate($params[$paramKeys['max']], isStart: false)?->toDateString() ?? '';
        }

        // The calendars drop a day outside the blueprint limits, and a reversed range,
        // which would leave an empty input over an active filter: fix the range and
        // send it, so the query matches what the inputs show.
        if ($this->selectedFrom !== '' || $this->selectedTo !== '') {
            $this->registerWithTags();
        }

        $from = $this->clampDate($this->selectedFrom);
        $to = $this->clampDate($this->selectedTo);

        if ($from !== $this->selectedFrom || $to !== $this->selectedTo || ($from !== '' && $to !== '' && $from > $to)) {
            [$this->selectedFrom, $this->selectedTo] = [$from, $to];
            $this->dispatchEvent();
        }
    }

    protected function clampDate(string $date): string
    {
        if ($date === '') {
            return '';
        }

        return min(max($date, $this->boundDate('earliest_date') ?? ''), $this->boundDate('latest_date') ?? '9999-12-31');
    }

    /**
     * The preset's `Y-m-d` days, clamped to the blueprint's earliest/latest dates
     * (the calendars can't show a day outside them), or null when none are left.
     *
     * @return array{0: string, 1: string}|null
     */
    protected function presetRange(string $preset): ?array
    {
        $today = Carbon::now(config('app.timezone'))->startOfDay();

        [$from, $to] = match ($preset) {
            'today' => [$today, $today],
            '7days' => [$today->copy()->subDays(6), $today],
            '30days' => [$today->copy()->subDays(29), $today],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'this_year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
        };

        $from = max($from->toDateString(), $this->boundDate('earliest_date') ?? '');
        $to = min($to->toDateString(), $this->boundDate('latest_date') ?? '9999-12-31');

        return $from <= $to ? [$from, $to] : null;
    }

    protected function boundDate(string $key): ?string
    {
        $value = $this->filterOptions[$key] ?? null;

        try {
            return $value ? Carbon::parse($value, config('app.timezone'))->toDateString() : null;
        } catch (\Throwable) {
            return null;
        }
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

        // startOfDay() again: where DST skips midnight the day starts at 01:00, and
        // addDay() would carry that hour into the next day. The one-second steps here
        // and in paramToDate() run in UTC: Carbon 2 does them in wall-clock time, which
        // lands an hour off across a DST gap.
        return ($isStart ? $day->utc()->subSecond() : $day->addDay()->startOfDay()->utc())->format(self::PARAM_FORMAT);
    }

    /**
     * The first (is_after) or last (is_before) local day an exclusive bound lets
     * through. Values without a zone, such as a tag's `field:is_after="2026-01-01"`,
     * are read in the app timezone like Statamic reads them.
     */
    public static function paramToDate($value, bool $isStart): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $bound = Carbon::parse($value, config('app.timezone'))->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }

        return $isStart
            ? $bound->startOfDay()->addDay()->startOfDay()
            : $bound->utc()->subSecond()->setTimezone(config('app.timezone'))->startOfDay();
    }
}
