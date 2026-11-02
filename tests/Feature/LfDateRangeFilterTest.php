<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfDateRangeFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfTags;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class LfDateRangeFilterTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    protected function setUp(): void
    {
        parent::setUp();

        // East of UTC, so every local day starts on the previous UTC date.
        config(['app.timezone' => 'Europe/Athens']);
        date_default_timezone_set('Europe/Athens');

        $collection = Facades\Collection::make('events')->save();
        Facades\Blueprint::make()->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        ['handle' => 'title', 'field' => ['type' => 'text', 'display' => 'Title']],
                        ['handle' => 'starts', 'field' => [
                            'type' => 'date',
                            'display' => 'Starts',
                            'earliest_date' => '2026-01-01',
                            'latest_date' => '2026-12-31',
                        ]],
                        ['handle' => 'starts_at', 'field' => ['type' => 'date', 'display' => 'Starts at', 'time_enabled' => true]],
                        ['handle' => 'seats', 'field' => ['type' => 'integer', 'display' => 'Seats']],
                    ],
                ],
            ],
        ])->setHandle('events')->setNamespace('collections.events')->save();

        // Date-only values and local wall-clock datetimes, saved the way the CP saves them.
        foreach ([
            'before' => ['2026-03-14', '2026-03-14 23:59'],
            'first-day' => ['2026-03-15', '2026-03-15 00:00'],
            'last-day' => ['2026-03-20', '2026-03-20 23:59'],
            'after' => ['2026-03-21', '2026-03-21 00:00'],
        ] as $slug => [$date, $dateTime]) {
            EntryFactory::id($slug)->collection($collection)->slug($slug)
                ->data(['title' => $slug, 'starts' => $date, 'starts_at' => $dateTime])
                ->make()->save();
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Date::resetToStringFormat();
        date_default_timezone_set('UTC');

        parent::tearDown();
    }

    #[Test]
    public function it_renders_two_calendars_bounded_by_the_blueprint_dates()
    {
        Livewire::test(LfDateRangeFilter::class, ['field' => 'starts', 'blueprint' => 'events.events'])
            ->assertSee('data-date-range="from"', false)
            ->assertSee('data-date-range="to"', false)
            ->assertSee('calendar({', false)
            ->assertSee("minDate: '2026-01-01'", false)
            ->assertSee("maxDate: '2026-12-31'", false);
    }

    #[Test]
    public function it_sends_each_end_as_an_exclusive_utc_bound_around_the_local_day()
    {
        Livewire::test(LfDateRangeFilter::class, ['field' => 'starts', 'blueprint' => 'events.events'])
            ->set('selectedFrom', '2026-03-15')
            ->assertDispatched('filter-updated',
                field: 'starts',
                condition: 'dual_range',
                payload: ['min' => '2026-03-14T21:59:59Z', 'max' => null],
                modifier: 'is_after|is_before',
            )
            ->set('selectedTo', '2026-03-20')
            ->assertDispatched('filter-updated',
                payload: ['min' => '2026-03-14T21:59:59Z', 'max' => '2026-03-20T22:00:00Z'],
            );
    }

    #[Test]
    public function it_converts_with_the_app_timezone_west_of_utc_too()
    {
        config(['app.timezone' => 'America/New_York']);

        Livewire::test(LfDateRangeFilter::class, ['field' => 'starts', 'blueprint' => 'events.events'])
            ->set('selectedTo', '2026-03-20')
            ->assertDispatched('filter-updated',
                payload: ['min' => null, 'max' => '2026-03-21T04:00:00Z'],
            );
    }

    #[Test]
    public function the_range_includes_both_end_days_for_date_and_datetime_fields()
    {
        // Statamic's front-end Localize middleware stringifies Carbon with the site's
        // date format, which breaks string comparisons such as gte/lte on dates.
        Date::setToStringFormat('F jS, Y');

        foreach (['starts', 'starts_at'] as $field) {
            $component = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'events']])
                ->dispatch('filter-updated',
                    field: $field,
                    condition: 'dual_range',
                    payload: ['min' => '2026-03-14T21:59:59Z', 'max' => '2026-03-20T22:00:00Z'],
                    modifier: 'is_after|is_before',
                );

            $this->assertEquals(
                ['first-day', 'last-day'],
                $component->viewData('entries')->map->slug()->sort()->values()->all(),
                $field
            );
        }
    }

    #[Test]
    public function an_open_end_is_left_out_of_the_collection_params()
    {
        Livewire::test(LivewireCollection::class, ['params' => ['from' => 'events']])
            ->dispatch('filter-updated',
                field: 'starts',
                condition: 'dual_range',
                payload: ['min' => '2026-03-14T21:59:59Z', 'max' => '2026-03-20T22:00:00Z'],
                modifier: 'is_after|is_before',
            )
            ->dispatch('filter-updated',
                field: 'starts',
                condition: 'dual_range',
                payload: ['min' => null, 'max' => '2026-03-20T22:00:00Z'],
                modifier: 'is_after|is_before',
            )
            ->assertSet('params', ['starts:is_before' => '2026-03-20T22:00:00Z']);
    }

    #[Test]
    public function presets_set_both_ends_in_the_app_timezone()
    {
        // 23:30 UTC on the 31st is already April 1st in Athens.
        Carbon::setTestNow(Carbon::parse('2026-03-31 23:30:00', 'UTC'));

        $component = Livewire::test(LfDateRangeFilter::class, [
            'field' => 'starts',
            'blueprint' => 'events.events',
            'presets' => 'today|7days|30days|this_month|this_year',
        ]);

        $expected = [
            'today' => ['2026-04-01', '2026-04-01'],
            '7days' => ['2026-03-26', '2026-04-01'],
            '30days' => ['2026-03-03', '2026-04-01'],
            'this_month' => ['2026-04-01', '2026-04-30'],
            'this_year' => ['2026-01-01', '2026-12-31'],
        ];

        foreach ($expected as $preset => [$from, $to]) {
            $component->call('applyPreset', $preset)
                ->assertSet('selectedFrom', $from)
                ->assertSet('selectedTo', $to)
                ->assertSee('aria-pressed="true"', false);
        }

        $component->call('applyPreset', 'today')
            ->assertDispatched('filter-updated', payload: ['min' => '2026-03-31T20:59:59Z', 'max' => '2026-04-01T21:00:00Z']);
    }

    #[Test]
    public function presets_are_clamped_to_the_blueprint_dates_and_hidden_outside_them()
    {
        // The blueprint allows 2026-01-01 to 2026-12-31.
        Carbon::setTestNow(Carbon::parse('2026-01-10 12:00:00', 'Europe/Athens'));

        Livewire::test(LfDateRangeFilter::class, ['field' => 'starts', 'blueprint' => 'events.events', 'presets' => '30days'])
            ->call('applyPreset', '30days')
            ->assertSet('selectedFrom', '2026-01-01')
            ->assertSet('selectedTo', '2026-01-10')
            ->assertSee('aria-pressed="true"', false);

        Carbon::setTestNow(Carbon::parse('2027-02-01 12:00:00', 'Europe/Athens'));

        Livewire::test(LfDateRangeFilter::class, ['field' => 'starts', 'blueprint' => 'events.events', 'presets' => 'this_year|30days'])
            ->assertDontSee('This year')
            ->assertDontSee('Last 30 days')
            ->call('applyPreset', 'this_year')
            ->assertSet('selectedFrom', '')
            ->assertNotDispatched('filter-updated');
    }

    #[Test]
    public function the_to_bound_is_the_next_local_midnight_when_dst_skips_midnight()
    {
        // São Paulo skipped 2018-11-04 00:00, so that day started at 01:00 (-02:00).
        config(['app.timezone' => 'America/Sao_Paulo']);

        $this->assertSame('2018-11-05T02:00:00Z', LfDateRangeFilter::dateToParam('2018-11-04', isStart: false));
        $this->assertSame('2018-11-04', LfDateRangeFilter::paramToDate('2018-11-05T02:00:00Z', isStart: false)->toDateString());
        $this->assertSame('2018-11-04', LfDateRangeFilter::paramToDate(LfDateRangeFilter::dateToParam('2018-11-04', isStart: true), isStart: true)->toDateString());
    }

    #[Test]
    public function presets_not_enabled_on_the_tag_are_ignored()
    {
        Livewire::test(LfDateRangeFilter::class, ['field' => 'starts', 'blueprint' => 'events.events', 'presets' => 'today'])
            ->assertSee('Today')
            ->assertDontSee('Last 7 days')
            ->call('applyPreset', '7days')
            ->assertSet('selectedFrom', '')
            ->assertNotDispatched('filter-updated');
    }

    #[Test]
    public function it_hydrates_both_ends_from_url_params()
    {
        Livewire::test(LfDateRangeFilter::class, ['field' => 'starts', 'blueprint' => 'events.events'])
            ->dispatch('preset-params', [
                'starts:is_after' => '2026-03-14T21:59:59Z',
                'starts:is_before' => '2026-03-20T22:00:00Z',
                'seats:gte' => '2',
            ])
            ->assertSet('selectedFrom', '2026-03-15')
            ->assertSet('selectedTo', '2026-03-20');
    }

    #[Test]
    public function a_chip_clears_only_its_own_end()
    {
        Livewire::test(LfDateRangeFilter::class, ['field' => 'starts', 'blueprint' => 'events.events'])
            ->set('selectedFrom', '2026-03-15')
            ->set('selectedTo', '2026-03-20')
            ->dispatch('clear-option', ['field' => 'starts', 'value' => '2026-03-14T21:59:59Z', 'condition' => 'is_after'])
            ->assertSet('selectedFrom', '')
            ->assertSet('selectedTo', '2026-03-20')
            ->assertDispatched('filter-updated', payload: ['min' => null, 'max' => '2026-03-20T22:00:00Z'])
            ->dispatch('clear-option', ['field' => 'starts', 'value' => '2026-03-20T22:00:00Z', 'condition' => 'is_before'])
            ->assertSet('selectedTo', '')
            ->assertDispatched('clear-filter', field: 'starts', condition: 'dual_range', modifier: 'is_after|is_before');
    }

    #[Test]
    public function clear_all_resets_both_ends()
    {
        Livewire::test(LfDateRangeFilter::class, ['field' => 'starts', 'blueprint' => 'events.events'])
            ->set('selectedFrom', '2026-03-15')
            ->set('selectedTo', '2026-03-20')
            ->dispatch('clear-all-filters')
            ->assertSet('selectedFrom', '')
            ->assertSet('selectedTo', '')
            ->assertDispatched('clear-filter', field: 'starts', condition: 'dual_range', modifier: 'is_after|is_before');
    }

    #[Test]
    public function it_rejects_malformed_dates_and_swaps_reversed_ends()
    {
        Livewire::test(LfDateRangeFilter::class, ['field' => 'starts', 'blueprint' => 'events.events'])
            ->set('selectedFrom', '2026-02-30')
            ->assertSet('selectedFrom', '')
            ->set('selectedFrom', '2026-03-20')
            ->set('selectedTo', '2026-03-15')
            ->assertSet('selectedFrom', '2026-03-15')
            ->assertSet('selectedTo', '2026-03-20');
    }

    #[Test]
    public function tags_show_each_end_as_a_formatted_local_date_once_the_filter_registers()
    {
        $params = ['starts:is_after' => '2026-03-14T21:59:59Z', 'starts:is_before' => '2026-03-20T22:00:00Z', 'seats:gte' => '2'];

        Livewire::test(LfTags::class, ['fields' => 'starts|seats', 'blueprint' => 'events.events'])
            ->dispatch('tags-updated', $params)
            ->assertSee('Starts is after 2026-03-14T21:59:59Z')
            ->dispatch('date-range-filter-registered', field: 'starts', format: 'D MMM YYYY')
            ->assertSee('Starts from 15 Mar 2026')
            ->assertSee('Starts to 20 Mar 2026')
            ->assertSee('Seats at least 2')
            ->call('removeOption', 'starts', '2026-03-20T22:00:00Z')
            ->assertDispatched('clear-option', fn ($event, $params) => $params[0]['condition'] === 'is_before'
                && $params[0]['value'] === '2026-03-20T22:00:00Z');
    }

    #[Test]
    public function the_filter_registers_its_format_with_the_tags()
    {
        Livewire::test(LfDateRangeFilter::class, ['field' => 'starts', 'blueprint' => 'events.events', 'format' => 'DD.MM.YYYY'])
            ->assertDispatched('date-range-filter-registered', field: 'starts', format: 'DD.MM.YYYY');
    }
}
