<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfDualRangeFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfRangeFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;
use Reach\StatamicLivewireFilters\Http\Livewire\Traits\HandleFieldBounds;
use Reach\StatamicLivewireFilters\Support\CountQueryPool;
use Reach\StatamicLivewireFilters\Support\RangeBoundsQuery;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class HandleFieldBoundsTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    protected $collection;

    protected $blueprint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collection = Facades\Collection::make('yachts')->save();
        $this->blueprint = Facades\Blueprint::make()->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        [
                            'handle' => 'title',
                            'field' => [
                                'type' => 'text',
                                'display' => 'Title',
                            ],
                        ],
                        [
                            'handle' => 'cabins',
                            'field' => [
                                'type' => 'text',
                                'display' => 'Cabins',
                                'listable' => 'hidden',
                            ],
                        ],
                        [
                            'handle' => 'year',
                            'field' => [
                                'type' => 'date',
                                'display' => 'Year',
                                'listable' => 'hidden',
                            ],
                        ],
                        [
                            'handle' => 'category',
                            'field' => [
                                'type' => 'text',
                                'display' => 'Category',
                                'listable' => 'hidden',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->blueprint->setHandle('yachts')->setNamespace('collections.'.$this->collection->handle())->save();

        $this->makeEntry($this->collection, 'yacht-a')->set('title', 'Luxury Yacht A')->set('cabins', 4)->set('year', '2020-05-22')->set('category', 'catamaran')->save();
        $this->makeEntry($this->collection, 'yacht-b')->set('title', 'Luxury Yacht B')->set('cabins', 6)->set('year', '2022-02-02')->set('category', 'catamaran')->save();
        $this->makeEntry($this->collection, 'yacht-c')->set('title', 'Luxury Yacht C')->set('cabins', 8)->set('year', '2024-12-30')->set('category', 'monohull')->save();
    }

    #[Test]
    public function it_ignores_a_params_updated_dispatch_when_the_bounds_feature_is_disabled()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', false);

        Livewire::test(LfDualRangeFilter::class, $this->dualRangeArguments())
            ->dispatch('params-updated', ['category:is' => 'catamaran'])
            ->assertNotDispatched('range-bounds-updated')
            ->assertSet('dynamicMin', null)
            ->assertSet('dynamicMax', null);
    }

    #[Test]
    public function it_rejects_forged_visibility_params_before_querying_bounds()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);
        Config::set('statamic-livewire-filters.enable_filter_values_count', false);

        $this->makeEntry($this->collection, 'draft-yacht')
            ->set('cabins', 9)->published(false)->save();

        $this->mock(CountQueryPool::class)->shouldNotReceive('getFieldValues');

        foreach ([LfRangeFilter::class => $this->rangeArguments(), LfDualRangeFilter::class => $this->dualRangeArguments()] as $component => $arguments) {
            Livewire::test($component, $arguments)
                ->dispatch('params-updated', ['status:is' => 'draft', 'slug:is' => 'draft-yacht'])
                ->assertNotDispatched('range-bounds-updated')
                ->assertSet('dynamicMin', null)
                ->assertSet('dynamicMax', null);
        }
    }

    #[Test]
    public function it_uses_the_authenticated_query_when_plain_event_params_are_tampered_with()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        $this->makeEntry($this->collection, 'draft-yacht')
            ->set('cabins', 9)->published(false)->save();

        $event = $this->collectionBoundsEvent(['from' => 'yachts', 'category:is' => 'catamaran']);
        $event[0] = ['status:is' => 'draft', 'slug:is' => 'draft-yacht'];

        foreach ([LfRangeFilter::class => $this->rangeArguments(), LfDualRangeFilter::class => $this->dualRangeArguments()] as $component => $arguments) {
            Livewire::test($component, $arguments)
                ->dispatch('params-updated', ...$event)
                ->assertSet('dynamicMin', 4)
                ->assertSet('dynamicMax', 6);
        }
    }

    #[Test]
    public function it_rejects_modified_or_malformed_query_tokens()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);
        $event = $this->collectionBoundsEvent(['from' => 'yachts']);
        $token = json_decode(base64_decode($event[1]), true);
        $token['mac'] = str_repeat('0', 64);

        $this->mock(CountQueryPool::class)->shouldNotReceive('getFieldValues');

        foreach ([null, '', [], 'invalid', base64_encode(json_encode($token))] as $boundsQuery) {
            Livewire::test(LfRangeFilter::class, $this->rangeArguments())
                ->call('updateBounds', ['status:is' => 'draft'], $boundsQuery)
                ->assertNotDispatched('range-bounds-updated')
                ->assertSet('dynamicMin', null);
        }
    }

    #[Test]
    public function it_rejects_query_tokens_for_a_different_collection()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);
        Facades\Collection::make('other')->save();
        $event = $this->collectionBoundsEvent(['from' => 'other', 'status:is' => 'any']);

        $this->mock(CountQueryPool::class)->shouldNotReceive('getFieldValues');

        Livewire::test(LfRangeFilter::class, $this->rangeArguments())
            ->dispatch('params-updated', ...$event)
            ->assertNotDispatched('range-bounds-updated')
            ->assertSet('dynamicMin', null);
    }

    #[Test]
    public function it_accepts_authenticated_queries_that_include_its_collection()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);
        $other = Facades\Collection::make('other')->save();
        $this->makeEntry($other, 'other-yacht')->set('cabins', 9)->save();

        foreach (['other|yachts', ['other', 'yachts'], '*'] as $collections) {
            $event = $this->collectionBoundsEvent(['from' => $collections]);

            Livewire::test(LfRangeFilter::class, $this->rangeArguments())
                ->dispatch('params-updated', ...$event)
                ->assertSet('dynamicMin', 4)
                ->assertSet('dynamicMax', 9);
        }
    }

    #[Test]
    public function adjusting_a_dual_range_keeps_matches_from_all_authenticated_collections()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);
        $other = Facades\Collection::make('other')->save();
        $this->makeEntry($other, 'other-yacht')->set('cabins', 9)->save();

        $collection = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'yachts|other']])
            ->assertSet('entriesCount', 4);
        $event = collect($collection->effects['dispatches'])->firstWhere('name', 'params-updated')['params'];

        $filter = Livewire::test(LfDualRangeFilter::class, $this->dualRangeArguments())
            ->dispatch('params-updated', ...$event)
            ->set('selectedMin', 6)
            ->assertDispatched('filter-updated',
                field: 'cabins',
                condition: 'dual_range',
                payload: ['min' => 6, 'max' => 9],
            );
        $update = collect($filter->effects['dispatches'])->firstWhere('name', 'filter-updated')['params'];

        $collection->dispatch('filter-updated', ...$update)
            ->assertSet('entriesCount', 3);
    }

    #[Test]
    public function multi_collection_bounds_preserve_query_constraints_and_visibility()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);
        $other = Facades\Collection::make('other')->save();
        $outside = Facades\Collection::make('outside')->save();
        $this->makeEntry($other, 'other-yacht')->set('cabins', 9)->set('category', 'catamaran')->save();
        $this->makeEntry($other, 'nonmatching-yacht')->set('cabins', 3)->set('category', 'monohull')->save();
        $this->makeEntry($other, 'draft-yacht')->set('cabins', 10)->set('category', 'catamaran')->published(false)->save();
        $this->makeEntry($outside, 'outside-yacht')->set('cabins', 10)->set('category', 'catamaran')->save();

        $event = $this->collectionBoundsEvent(['from' => 'yachts|other', 'category:is' => 'catamaran']);
        $event[0] = ['from' => '*', 'status:is' => 'any'];

        Livewire::test(LfDualRangeFilter::class, $this->dualRangeArguments())
            ->dispatch('params-updated', ...$event)
            ->assertSet('dynamicMin', 4)
            ->assertSet('dynamicMax', 9);
    }

    #[Test]
    public function it_preserves_template_authored_visibility_params_in_authenticated_queries()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        $this->makeEntry($this->collection, 'draft-yacht')
            ->set('cabins', 9)->published(false)->save();

        $event = $this->collectionBoundsEvent(['from' => 'yachts', 'status:is' => 'any']);

        foreach ([LfRangeFilter::class => $this->rangeArguments(), LfDualRangeFilter::class => $this->dualRangeArguments()] as $component => $arguments) {
            Livewire::test($component, $arguments)
                ->dispatch('params-updated', ...$event)
                ->assertSet('dynamicMin', 4)
                ->assertSet('dynamicMax', 9);
        }
    }

    #[Test]
    public function the_collection_authenticates_only_sanitized_client_params()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        $this->makeEntry($this->collection, 'draft-yacht')
            ->set('cabins', 9)->published(false)->save();

        $collection = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'yachts']])
            ->dispatch('filter-updated', field: 'status', condition: 'is', payload: 'any', modifier: null);
        $event = collect($collection->effects['dispatches'])->firstWhere('name', 'params-updated')['params'];

        Livewire::test(LfRangeFilter::class, $this->rangeArguments())
            ->dispatch('params-updated', ...$event)
            ->assertSet('dynamicMin', 4)
            ->assertSet('dynamicMax', 8);
    }

    #[Test]
    public function it_updates_bounds_to_match_entries_narrowed_by_other_filters()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfDualRangeFilter::class, $this->dualRangeArguments())
            ->dispatch('params-updated', ...$this->boundsEventArguments(['category:is' => 'catamaran']))
            ->assertSet('dynamicMin', 4)
            ->assertSet('dynamicMax', 6)
            ->assertDispatched('range-bounds-updated', min: 4, max: 6, disabled: false);
    }

    #[Test]
    public function it_excludes_its_own_dual_range_params_when_computing_bounds()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfDualRangeFilter::class, $this->dualRangeArguments())
            ->dispatch('params-updated', ...$this->boundsEventArguments(['cabins:gte' => 6, 'cabins:lte' => 8]))
            ->assertSet('dynamicMin', 4)
            ->assertSet('dynamicMax', 8);
    }

    #[Test]
    public function it_excludes_its_own_date_params_when_computing_bounds()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfDualRangeFilter::class, $this->dateDualRangeArguments())
            ->dispatch('params-updated', ...$this->boundsEventArguments(['year:is_after' => '2022-01-01', 'year:is_before' => '2024-12-31']))
            ->assertSet('dynamicMin', 2020)
            ->assertSet('dynamicMax', 2024);
    }

    #[Test]
    public function it_computes_year_bounds_for_date_fields()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfDualRangeFilter::class, $this->dateDualRangeArguments())
            ->dispatch('params-updated', ...$this->boundsEventArguments(['category:is' => 'catamaran']))
            ->assertSet('dynamicMin', 2020)
            ->assertSet('dynamicMax', 2022)
            ->assertDispatched('range-bounds-updated', min: 2020, max: 2022, disabled: false);
    }

    #[Test]
    public function it_falls_back_to_absolute_bounds_when_no_entries_match()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfDualRangeFilter::class, $this->dualRangeArguments())
            ->dispatch('params-updated', ...$this->boundsEventArguments(['category:is' => 'nonexistent']))
            ->assertSet('dynamicMin', 2)
            ->assertSet('dynamicMax', 10)
            ->assertDispatched('range-bounds-updated', min: 2, max: 10, disabled: false);
    }

    #[Test]
    public function it_rounds_bounds_outward_to_the_nearest_step_multiple_anchored_at_min()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        $this->makeEntry($this->collection, 'yacht-d')->set('title', 'Luxury Yacht D')->set('cabins', 5)->set('category', 'special')->save();
        $this->makeEntry($this->collection, 'yacht-e')->set('title', 'Luxury Yacht E')->set('cabins', 7)->set('category', 'special')->save();

        Livewire::test(LfDualRangeFilter::class, array_merge($this->dualRangeArguments(), ['step' => 2]))
            ->dispatch('params-updated', ...$this->boundsEventArguments(['category:is' => 'special']))
            ->assertSet('dynamicMin', 4)
            ->assertSet('dynamicMax', 8);
    }

    #[Test]
    public function it_flags_disabled_and_widens_the_span_below_min_range()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfDualRangeFilter::class, $this->dualRangeArguments())
            ->dispatch('params-updated', ...$this->boundsEventArguments(['category:is' => 'monohull']))
            ->assertSet('dynamicMin', 8)
            ->assertSet('dynamicMax', 10)
            ->assertSet('boundsDisabled', true)
            ->assertDispatched('range-bounds-updated', min: 8, max: 10, disabled: true);
    }

    #[Test]
    public function it_pins_an_inactive_selection_to_the_new_bounds_without_dispatching_filter_updated()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfDualRangeFilter::class, $this->dualRangeArguments())
            ->dispatch('params-updated', ...$this->boundsEventArguments(['category:is' => 'catamaran']))
            ->assertSet('selectedMin', 4)
            ->assertSet('selectedMax', 6)
            ->assertNotDispatched('filter-updated')
            ->assertDispatched('range-bounds-updated', min: 4, max: 6, selectedMin: 4, selectedMax: 6, disabled: false)
            ->dispatch('params-updated', ...$this->boundsEventArguments([]))
            ->assertSet('selectedMin', 4)
            ->assertSet('selectedMax', 8)
            ->assertNotDispatched('filter-updated');
    }

    #[Test]
    public function it_widens_bounds_to_contain_an_active_selection()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfDualRangeFilter::class, $this->dualRangeArguments())
            ->set('selectedMin', 5)
            ->assertDispatched('filter-updated',
                field: 'cabins',
                condition: 'dual_range',
                payload: ['min' => 5, 'max' => 10],
            )
            ->dispatch('params-updated', ...$this->boundsEventArguments(['category:is' => 'catamaran', 'cabins:gte' => 5, 'cabins:lte' => 10]))
            ->assertSet('selectedMin', 5)
            ->assertSet('selectedMax', 10)
            ->assertNotDispatched('filter-updated')
            ->assertDispatched('range-bounds-updated', min: 4, max: 10, selectedMin: 5, selectedMax: 10, disabled: false);
    }

    #[Test]
    public function it_keeps_the_displayed_selection_stable_as_other_filters_change()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfDualRangeFilter::class, $this->dualRangeArguments())
            ->set('selectedMin', 5)
            ->set('selectedMax', 8)
            ->dispatch('params-updated', ...$this->boundsEventArguments(['category:is' => 'catamaran', 'cabins:gte' => 5, 'cabins:lte' => 8]))
            ->assertDispatched('range-bounds-updated', min: 4, max: 8, selectedMin: 5, selectedMax: 8, disabled: false)
            ->dispatch('params-updated', ...$this->boundsEventArguments(['cabins:gte' => 5, 'cabins:lte' => 8]))
            ->assertSet('selectedMin', 5)
            ->assertSet('selectedMax', 8)
            ->assertNotDispatched('filter-updated')
            ->assertDispatched('range-bounds-updated', min: 4, max: 8, selectedMin: 5, selectedMax: 8, disabled: false);
    }

    #[Test]
    #[DataProvider('rangeSelectionEventOrders')]
    public function it_preserves_a_new_selection_while_an_older_bounds_event_is_in_flight(bool $dualRange, bool $reverseEvents)
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        $collection = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'yachts']]);
        $initialEvent = collect($collection->effects['dispatches'])->firstWhere('name', 'params-updated')['params'];
        $filter = Livewire::test(
            $dualRange ? LfDualRangeFilter::class : LfRangeFilter::class,
            $dualRange ? $this->dualRangeArguments() : $this->rangeArguments(),
        )->dispatch('params-updated', ...$initialEvent);

        $collection->dispatch('filter-updated', field: 'category', condition: 'is', payload: 'catamaran', modifier: null);
        $olderEvent = collect($collection->effects['dispatches'])->firstWhere('name', 'params-updated')['params'];

        $filter->set($dualRange ? 'selectedMin' : 'selected', $dualRange ? 5 : 8);
        $selectionUpdate = collect($filter->effects['dispatches'])->firstWhere('name', 'filter-updated')['params'];
        $collection->dispatch('filter-updated', ...$selectionUpdate)
            ->assertSet('params.cabins:gte', $dualRange ? 5 : 8)
            ->assertSet('entriesCount', $dualRange ? 1 : 0);
        $currentEvent = collect($collection->effects['dispatches'])->firstWhere('name', 'params-updated')['params'];

        foreach ($reverseEvents ? [$currentEvent, $olderEvent] : [$olderEvent, $currentEvent] as $event) {
            $filter->dispatch('params-updated', ...$event)
                ->assertSet($dualRange ? 'selectedMin' : 'selected', $dualRange ? 5 : 8)
                ->assertSet('dynamicMin', 4)
                ->assertSet('dynamicMax', 8)
                ->assertSet('boundsDisabled', false)
                ->assertNotDispatched('filter-updated');

            if ($dualRange) {
                $filter->assertSet('selectedMax', 8);
            }

            if ($reverseEvents && $event === $olderEvent) {
                $filter->assertNotDispatched('range-bounds-updated');
            } elseif ($dualRange) {
                $filter->assertDispatched('range-bounds-updated', min: 4, max: 8, selectedMin: 5, selectedMax: 8, disabled: false);
            } else {
                $filter->assertDispatched('range-bounds-updated', min: 4, max: 8, selected: 8, disabled: false);
            }
        }
    }

    public static function rangeSelectionEventOrders(): array
    {
        return [
            'dual range, older event first' => [true, false],
            'single range, older event first' => [false, false],
            'dual range, newer event first' => [true, true],
            'single range, newer event first' => [false, true],
        ];
    }

    #[Test]
    #[DataProvider('rangeSelectionCases')]
    public function it_preserves_a_clear_while_an_older_active_bounds_event_is_in_flight(bool $dualRange)
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        $collection = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'yachts', 'category:is' => 'catamaran']]);
        $filter = Livewire::test(
            $dualRange ? LfDualRangeFilter::class : LfRangeFilter::class,
            $dualRange ? $this->dualRangeArguments() : $this->rangeArguments(),
        )->set($dualRange ? 'selectedMin' : 'selected', 5);

        $selectionUpdate = collect($filter->effects['dispatches'])->firstWhere('name', 'filter-updated')['params'];
        $collection->dispatch('filter-updated', ...$selectionUpdate);
        $olderEvent = collect($collection->effects['dispatches'])->firstWhere('name', 'params-updated')['params'];

        $filter->dispatch('clear-all-filters');
        $clearUpdate = collect($filter->effects['dispatches'])->firstWhere('name', 'clear-filter')['params'];
        $collection->dispatch('clear-filter', ...$clearUpdate)
            ->assertSet('entriesCount', 2);
        $currentEvent = collect($collection->effects['dispatches'])->firstWhere('name', 'params-updated')['params'];

        foreach ([$olderEvent, $currentEvent] as $event) {
            $filter->dispatch('params-updated', ...$event)
                ->assertSet($dualRange ? 'selectedMin' : 'selected', 4)
                ->assertSet('dynamicMin', 4)
                ->assertSet('dynamicMax', 6)
                ->assertNotDispatched('filter-updated');

            if ($dualRange) {
                $filter->assertSet('selectedMax', 6)
                    ->assertDispatched('range-bounds-updated', min: 4, max: 6, selectedMin: 4, selectedMax: 6, disabled: false);
            } else {
                $filter->assertDispatched('range-bounds-updated', min: 4, max: 6, selected: 4, disabled: false);
            }
        }
    }

    public static function rangeSelectionCases(): array
    {
        return [
            'dual range' => [true],
            'single range' => [false],
        ];
    }

    #[Test]
    #[DataProvider('rangeSelectionCases')]
    public function it_does_not_acknowledge_a_reselection_with_an_older_identical_selection(bool $dualRange)
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        $collection = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'yachts', 'category:is' => 'catamaran']]);
        $filter = Livewire::test(
            $dualRange ? LfDualRangeFilter::class : LfRangeFilter::class,
            $dualRange ? $this->dualRangeArguments() : $this->rangeArguments(),
        );
        $events = [];

        foreach ([true, false, true] as $select) {
            if ($select) {
                $filter->set($dualRange ? 'selectedMin' : 'selected', $dualRange ? 5 : 8);
                if ($dualRange) {
                    $filter->set('selectedMax', 8);
                }
            } else {
                $filter->call('clear');
            }

            $name = $select ? 'filter-updated' : 'clear-filter';
            $update = collect($filter->effects['dispatches'])->firstWhere('name', $name)['params'];
            $collection->dispatch($name, ...$update);
            $events[] = collect($collection->effects['dispatches'])->firstWhere('name', 'params-updated')['params'];
        }

        foreach ($events as $event) {
            $filter->dispatch('params-updated', ...$event)
                ->assertSet($dualRange ? 'selectedMin' : 'selected', $dualRange ? 5 : 8)
                ->assertSet('dynamicMax', 8)
                ->assertSet('boundsDisabled', false)
                ->assertNotDispatched('filter-updated');
            if ($dualRange) {
                $filter->assertSet('selectedMax', 8)
                    ->assertDispatched('range-bounds-updated', selectedMin: 5, selectedMax: 8);
            } else {
                $filter->assertDispatched('range-bounds-updated', selected: 8);
            }
        }
    }

    #[Test]
    #[DataProvider('rangeSelectionCases')]
    public function it_acknowledges_redundant_selection_changes_on_the_next_bounds_event(bool $dualRange)
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        $collection = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'yachts', 'category:is' => 'catamaran']]);
        $filter = Livewire::test(
            $dualRange ? LfDualRangeFilter::class : LfRangeFilter::class,
            $dualRange ? $this->dualRangeArguments() : $this->rangeArguments(),
        );

        foreach (['filter-updated' => 'monohull', 'clear-filter' => 'catamaran'] as $name => $category) {
            foreach ([false, true] as $redundant) {
                if ($name === 'filter-updated') {
                    $filter->set($dualRange ? 'selectedMin' : 'selected', 5);
                } else {
                    $filter->call('clear');
                }

                $update = collect($filter->effects['dispatches'])->firstWhere('name', $name)['params'];
                $collection->dispatch($name, ...$update);
                if ($redundant) {
                    $collection->assertNotDispatched('params-updated');
                }
            }

            $collection->dispatch('filter-updated', field: 'category', condition: 'is', payload: $category, modifier: null);
            $event = collect($collection->effects['dispatches'])->firstWhere('name', 'params-updated')['params'];
            $filter->dispatch('params-updated', ...$event)
                ->assertSet('pendingBoundsSelection', null);
        }

        $filter->assertSet($dualRange ? 'selectedMin' : 'selected', 4)
            ->assertSet('dynamicMax', 6);
    }

    #[Test]
    #[DataProvider('pendingSelectionCases')]
    public function it_uses_the_latest_selection_until_the_matching_query_arrives(string $kind)
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        $collection = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'yachts', 'category:is' => 'catamaran']]);

        if ($kind === 'single') {
            $filter = Livewire::test(LfRangeFilter::class, $this->rangeArguments())->set('selected', 6);
            $property = 'selected';
            [$min, $latest] = [4, 8];
        } elseif ($kind === 'date') {
            $filter = Livewire::test(LfDualRangeFilter::class, $this->dateDualRangeArguments())
                ->set('selectedMin', 2021)->set('selectedMax', 2023);
            $property = 'selectedMax';
            [$min, $latest] = [2020, 2025];
        } else {
            $filter = Livewire::test(LfDualRangeFilter::class, array_merge($this->dualRangeArguments(), ['modifier' => 'gt|lt']))
                ->set('selectedMin', 5)->set('selectedMax', 7);
            $property = 'selectedMax';
            [$min, $latest] = [3, 8];
        }

        $update = collect($filter->effects['dispatches'])->firstWhere('name', 'filter-updated')['params'];
        $collection->dispatch('filter-updated', ...$update);
        $olderEvent = collect($collection->effects['dispatches'])->firstWhere('name', 'params-updated')['params'];

        $filter->set($property, $latest);
        $update = collect($filter->effects['dispatches'])->firstWhere('name', 'filter-updated')['params'];
        $collection->dispatch('filter-updated', ...$update);
        $currentEvent = collect($collection->effects['dispatches'])->firstWhere('name', 'params-updated')['params'];

        foreach ([$olderEvent, $currentEvent] as $event) {
            $filter->dispatch('params-updated', ...$event)
                ->assertSet($property, $latest)
                ->assertSet('dynamicMin', $min)
                ->assertSet('dynamicMax', $latest)
                ->assertDispatched('range-bounds-updated', ...[$property => $latest])
                ->assertNotDispatched('filter-updated');
        }

        // After acknowledgment, a newer authoritative clear can make the range inactive.
        $collection->dispatch('clear-filter', field: $update['field'], condition: $update['condition'], modifier: $update['modifier']);
        $clearEvent = collect($collection->effects['dispatches'])->firstWhere('name', 'params-updated')['params'];
        $filter->dispatch('params-updated', ...$clearEvent)
            ->assertSet($property, match ($kind) {
                'single' => 4,
                'date' => 2022,
                default => 7,
            });
    }

    public static function pendingSelectionCases(): array
    {
        return [
            'single range with a string query value' => ['single'],
            'dual range with custom comparisons' => ['dual'],
            'dual range with date values' => ['date'],
        ];
    }

    #[Test]
    public function it_keeps_the_single_range_display_stable_as_other_filters_change()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfRangeFilter::class, $this->rangeArguments())
            ->set('selected', 8)
            ->dispatch('params-updated', ...$this->boundsEventArguments(['category:is' => 'catamaran', 'cabins:gte' => 8]))
            ->assertSet('selected', 8)
            ->assertDispatched('range-bounds-updated', min: 4, max: 8, selected: 8, disabled: false)
            ->dispatch('params-updated', ...$this->boundsEventArguments(['cabins:gte' => 8]))
            ->assertSet('selected', 8)
            ->assertNotDispatched('filter-updated')
            ->assertDispatched('range-bounds-updated', min: 4, max: 8, selected: 8, disabled: false);
    }

    #[Test]
    public function it_keeps_an_active_dual_range_enabled_when_bounds_collapse()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfDualRangeFilter::class, $this->dualRangeArguments())
            ->set('selectedMin', 5)
            ->set('selectedMax', 7)
            ->dispatch('params-updated', ...$this->boundsEventArguments(['category:is' => 'monohull', 'cabins:gte' => 5, 'cabins:lte' => 7]))
            ->assertSet('boundsDisabled', false)
            ->assertDispatched('range-bounds-updated', min: 5, max: 8, selectedMin: 5, selectedMax: 7, disabled: false);
    }

    #[Test]
    public function it_keeps_an_active_single_range_enabled_when_bounds_collapse_to_its_value()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfRangeFilter::class, $this->rangeArguments())
            ->set('selected', 8)
            ->dispatch('params-updated', ...$this->boundsEventArguments(['category:is' => 'monohull', 'cabins:gte' => 8]))
            ->assertSet('boundsDisabled', false)
            ->assertDispatched('range-bounds-updated', min: 1, max: 10, selected: 8, disabled: false);
    }

    #[Test]
    public function it_falls_back_to_absolute_bounds_when_matches_lie_outside_them()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        $this->makeEntry($this->collection, 'yacht-tiny')->set('title', 'Tiny Yacht')->set('cabins', 1)->set('category', 'tiny')->save();

        Livewire::test(LfDualRangeFilter::class, $this->dualRangeArguments())
            ->dispatch('params-updated', ...$this->boundsEventArguments(['category:is' => 'tiny']))
            ->assertSet('dynamicMin', 2)
            ->assertSet('dynamicMax', 10)
            ->assertSet('boundsDisabled', false)
            ->assertDispatched('range-bounds-updated', min: 2, max: 10, disabled: false);
    }

    #[Test]
    public function it_falls_back_to_absolute_bounds_when_single_range_matches_exceed_them()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        $this->makeEntry($this->collection, 'yacht-huge')->set('title', 'Huge Yacht')->set('cabins', 15)->set('category', 'huge')->save();

        Livewire::test(LfRangeFilter::class, $this->rangeArguments())
            ->dispatch('params-updated', ...$this->boundsEventArguments(['category:is' => 'huge']))
            ->assertSet('dynamicMin', 1)
            ->assertSet('dynamicMax', 10)
            ->assertSet('boundsDisabled', false);
    }

    #[Test]
    public function it_clears_the_filter_when_the_selection_returns_to_the_dynamic_bounds()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfDualRangeFilter::class, $this->dualRangeArguments())
            ->dispatch('params-updated', ...$this->boundsEventArguments([]))
            ->assertSet('dynamicMin', 4)
            ->assertSet('dynamicMax', 8)
            ->set('selectedMin', 6)
            ->assertDispatched('filter-updated',
                field: 'cabins',
                condition: 'dual_range',
                payload: ['min' => 6, 'max' => 8],
            )
            ->set('selectedMin', 4)
            ->assertDispatched('clear-filter',
                field: 'cabins',
                condition: 'dual_range',
            );
    }

    #[Test]
    public function it_clears_the_single_range_filter_when_selection_returns_to_the_clamped_default()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfRangeFilter::class, $this->rangeArguments())
            ->dispatch('params-updated', ...$this->boundsEventArguments(['category:is' => 'catamaran']))
            ->assertSet('selected', 4)
            ->set('selected', 5)
            ->assertDispatched('filter-updated',
                field: 'cabins',
                condition: 'gte',
                payload: 5,
            )
            ->set('selected', 4)
            ->assertDispatched('clear-filter',
                field: 'cabins',
                condition: 'gte',
            );
    }

    #[Test]
    public function exclusive_dual_range_endpoints_remain_selectable_with_dynamic_bounds()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        $filter = Livewire::test(LfDualRangeFilter::class, array_merge($this->dualRangeArguments(), [
            'modifier' => 'gt|lt',
            'step' => 2,
        ]))
            ->dispatch('params-updated', ...$this->collectionBoundsEvent(['from' => 'yachts']))
            ->set('selectedMin', 6)
            ->set('selectedMin', 4)
            ->set('selectedMax', 8)
            ->assertNotDispatched('clear-filter')
            ->assertDispatched('filter-updated',
                field: 'cabins',
                condition: 'dual_range',
                payload: ['min' => 4, 'max' => 8],
                modifier: 'gt|lt',
            );
        $update = collect($filter->effects['dispatches'])->firstWhere('name', 'filter-updated')['params'];

        Livewire::test(LivewireCollection::class, ['params' => ['from' => 'yachts']])
            ->dispatch('filter-updated', ...$update)
            ->assertSet('entriesCount', 1)
            ->assertSee('Luxury Yacht B')
            ->assertDontSee('Luxury Yacht A')
            ->assertDontSee('Luxury Yacht C');
    }

    #[Test]
    public function exclusive_single_range_endpoints_remain_selectable_with_dynamic_bounds()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        $filter = Livewire::test(LfRangeFilter::class, array_merge($this->rangeArguments(), [
            'condition' => 'gt',
            'min' => 2,
            'step' => 2,
        ]))
            ->dispatch('params-updated', ...$this->collectionBoundsEvent(['from' => 'yachts']))
            ->set('selected', 6)
            ->set('selected', 4)
            ->assertNotDispatched('clear-filter')
            ->assertDispatched('filter-updated', field: 'cabins', condition: 'gt', payload: 4);
        $update = collect($filter->effects['dispatches'])->firstWhere('name', 'filter-updated')['params'];

        Livewire::test(LivewireCollection::class, ['params' => ['from' => 'yachts']])
            ->dispatch('filter-updated', ...$update)
            ->assertSet('entriesCount', 2)
            ->assertDontSee('Luxury Yacht A');
    }

    #[Test]
    #[DataProvider('exclusiveDualRangeBounds')]
    public function exclusive_dual_ranges_can_clear_at_step_aligned_bounds($modifier, $min, $step, $dynamicMin, $dynamicMax)
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfDualRangeFilter::class, array_merge($this->dualRangeArguments(), [
            'modifier' => $modifier,
            'min' => $min,
            'step' => $step,
        ]))
            ->dispatch('params-updated', ...$this->boundsEventArguments([]))
            ->assertSet('dynamicMin', $dynamicMin)
            ->assertSet('dynamicMax', $dynamicMax)
            ->set('selectedMin', 6)
            ->assertDispatched('filter-updated')
            ->set('selectedMin', $dynamicMin)
            ->assertDispatched('clear-filter');
    }

    public static function exclusiveDualRangeBounds(): array
    {
        return [
            'both strict' => ['gt|lt', 0, 2, 2, 10],
            'comparison aliases' => ['greater_than|less_than', 0, 2, 2, 10],
            'strict minimum' => ['gt|lte', 0, 2, 2, 8],
            'strict maximum' => ['gte|lt', 0, 2, 4, 10],
            'fractional step' => ['gt|lt', 0, 0.5, 3.5, 8.5],
            'already rounded outward' => ['gt|lt', 1, 2, 3, 9],
        ];
    }

    #[Test]
    #[DataProvider('clampedExclusiveDualRangeBounds')]
    public function strict_dual_range_endpoints_do_not_clear_when_absolute_limits_prevent_padding($modifier, $min, $max)
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfDualRangeFilter::class, array_merge($this->dualRangeArguments(), [
            'modifier' => $modifier,
            'min' => $min,
            'max' => $max,
            'step' => 2,
        ]))
            ->dispatch('params-updated', ...$this->boundsEventArguments([]))
            ->set('selectedMin', 6)
            ->set('selectedMin', 4)
            ->assertNotDispatched('clear-filter')
            ->assertDispatched('filter-updated', payload: ['min' => 4, 'max' => 8], modifier: $modifier)
            ->call('clear')
            ->assertDispatched('clear-filter');
    }

    public static function clampedExclusiveDualRangeBounds(): array
    {
        return [
            'strict minimum' => ['gt|lte', 4, 10],
            'strict maximum' => ['gte|lt', 2, 8],
        ];
    }

    #[Test]
    #[DataProvider('exclusiveSingleRangeBounds')]
    public function exclusive_single_ranges_can_filter_endpoints_and_clear_beyond_them($condition, $default, $endpoint, $clearValue)
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfRangeFilter::class, array_merge($this->rangeArguments(), [
            'condition' => $condition,
            'default' => $default,
        ]))
            ->dispatch('params-updated', ...$this->boundsEventArguments([]))
            ->assertSet('selected', $clearValue)
            ->set('selected', 6)
            ->set('selected', $endpoint)
            ->assertNotDispatched('clear-filter')
            ->assertDispatched('filter-updated', condition: $condition, payload: $endpoint)
            ->set('selected', $clearValue)
            ->assertDispatched('clear-filter');
    }

    public static function exclusiveSingleRangeBounds(): array
    {
        return [
            'strict minimum' => ['gt', null, 4, 3],
            'strict maximum' => ['lt', 10, 8, 9],
        ];
    }

    #[Test]
    public function date_ranges_can_clear_when_the_displayed_years_include_all_matches()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfDualRangeFilter::class, $this->dateDualRangeArguments())
            ->dispatch('params-updated', ...$this->boundsEventArguments([]))
            ->set('selectedMin', 2022)
            ->assertDispatched('filter-updated')
            ->set('selectedMin', 2020)
            ->assertDispatched('clear-filter');
    }

    #[Test]
    public function exclusive_date_endpoints_remain_selectable_at_year_boundaries()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);
        Facades\Entry::find('yacht-a')->set('year', '2020-01-01')->save();
        Facades\Entry::find('yacht-c')->set('year', '2024-12-31')->save();

        $filter = Livewire::test(LfDualRangeFilter::class, $this->dateDualRangeArguments())
            ->dispatch('params-updated', ...$this->collectionBoundsEvent(['from' => 'yachts']))
            ->assertSet('dynamicMin', 2019)
            ->assertSet('dynamicMax', 2025)
            ->set('selectedMin', 2020)
            ->set('selectedMax', 2024)
            ->assertNotDispatched('clear-filter')
            ->assertDispatched('filter-updated',
                payload: ['min' => '2020-01-01', 'max' => '2024-12-31'],
                modifier: 'is_after|is_before',
            );
        $update = collect($filter->effects['dispatches'])->firstWhere('name', 'filter-updated')['params'];

        Livewire::test(LivewireCollection::class, ['params' => ['from' => 'yachts']])
            ->dispatch('filter-updated', ...$update)
            ->assertSet('entriesCount', 1)
            ->assertSee('Luxury Yacht B');
    }

    #[Test]
    public function it_updates_bounds_for_the_single_range_filter()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfRangeFilter::class, $this->rangeArguments())
            ->dispatch('params-updated', ...$this->boundsEventArguments(['category:is' => 'catamaran']))
            ->assertSet('dynamicMin', 4)
            ->assertSet('dynamicMax', 6)
            ->assertSet('selected', 4)
            ->assertNotDispatched('filter-updated')
            ->assertDispatched('range-bounds-updated', min: 4, max: 6, selected: 4, disabled: false);
    }

    #[Test]
    public function it_disables_the_single_range_filter_when_bounds_are_equal_and_clamps_selected()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfRangeFilter::class, $this->rangeArguments())
            ->dispatch('params-updated', ...$this->boundsEventArguments(['category:is' => 'monohull']))
            ->assertSet('dynamicMin', 8)
            ->assertSet('dynamicMax', 8)
            ->assertSet('boundsDisabled', true)
            ->assertSet('selected', 8)
            ->assertNotDispatched('filter-updated')
            ->assertDispatched('range-bounds-updated', min: 8, max: 8, selected: 8, disabled: true);
    }

    #[Test]
    public function it_refreshes_bounds_when_params_are_cleared()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfDualRangeFilter::class, $this->dualRangeArguments())
            ->dispatch('params-updated', ...$this->boundsEventArguments(['category:is' => 'catamaran']))
            ->assertSet('dynamicMin', 4)
            ->assertSet('dynamicMax', 6)
            ->dispatch('params-updated', ...$this->boundsEventArguments([]))
            ->assertSet('dynamicMin', 4)
            ->assertSet('dynamicMax', 8)
            ->assertSet('boundsDisabled', false);
    }

    #[Test]
    public function it_normalizes_string_tag_bounds_to_numbers()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfDualRangeFilter::class, array_merge($this->dualRangeArguments(), ['min' => '2', 'max' => '10']))
            ->dispatch('params-updated', ...$this->boundsEventArguments(['category:is' => 'nonexistent']))
            ->assertSet('dynamicMin', 2, strict: true)
            ->assertSet('dynamicMax', 10, strict: true);
    }

    #[Test]
    public function it_rounds_down_and_up_to_step_multiples()
    {
        $component = $this->makeBoundsStub();

        $this->assertSame(4, $component->roundDown(5));
        $this->assertSame(8, $component->roundUp(7));
        $this->assertSame(4, $component->roundDown(4));
        $this->assertSame(8, $component->roundUp(8));
    }

    #[Test]
    public function it_widens_bounds_within_absolute_limits()
    {
        $component = $this->makeBoundsStub();

        $this->assertSame([8, 10], $component->widen(8, 8, 2));
        $this->assertSame([8, 10], $component->widen(9, 9, 2));
        $this->assertSame([2, 4], $component->widen(2, 2, 2));
    }

    #[Test]
    public function it_keeps_scoped_params_whose_scope_name_matches_the_field_handle()
    {
        $component = $this->makeBoundsStub();

        $params = [
            'query_scope' => 'cabins',
            'cabins:category' => 'catamaran',
            'cabins:gte' => 5,
            'other:is' => 'x',
        ];

        $this->assertSame($params, $component->baseParams($params, 'cabins'));
    }

    #[Test]
    public function it_still_strips_own_field_params_when_no_scope_collides()
    {
        $component = $this->makeBoundsStub();

        $params = [
            'query_scope' => 'multiselect',
            'multiselect:brand' => 'x',
            'cabins:gte' => 5,
            'cabins:lte' => 7,
            'category:is' => 'catamaran',
        ];

        $this->assertSame([
            'query_scope' => 'multiselect',
            'multiselect:brand' => 'x',
            'category:is' => 'catamaran',
        ], $component->baseParams($params, 'cabins'));
    }

    protected function makeBoundsStub()
    {
        return new class
        {
            use HandleFieldBounds;

            public $condition = 'dual_range';

            public $modifier = 'any';

            public $statamic_field = [];

            public $min = 2;

            public $max = 10;

            public $step = 2;

            public $minRange = 2;

            protected function minimumBoundsSpan(): int|float
            {
                return $this->minRange;
            }

            protected function clampedSelectionPayload($min, $max): array
            {
                return [];
            }

            protected function pinSelectionToBounds($min, $max): void {}

            protected function selectionEventPayload(): array
            {
                return [];
            }

            public function roundDown($value)
            {
                return $this->roundDownToStep($value);
            }

            public function roundUp($value)
            {
                return $this->roundUpToStep($value);
            }

            public function widen($min, $max, $minimumSpan)
            {
                return $this->widenBoundsToMinimumSpan($min, $max, $minimumSpan);
            }

            public function baseParams($params, $fieldHandle)
            {
                return $this->removeCurrentFieldFromParams($params, $fieldHandle);
            }
        };
    }

    protected function boundsEventArguments(array $params): array
    {
        return [$params, RangeBoundsQuery::encode('yachts', $params)];
    }

    protected function collectionBoundsEvent(array $params): array
    {
        $component = Livewire::test(LivewireCollection::class, ['params' => $params]);

        return collect($component->effects['dispatches'])->firstWhere('name', 'params-updated')['params'];
    }

    protected function dualRangeArguments(): array
    {
        return [
            'field' => 'cabins',
            'blueprint' => 'yachts.yachts',
            'condition' => 'dual_range',
            'min' => 2,
            'max' => 10,
            'minRange' => 2,
        ];
    }

    protected function dateDualRangeArguments(): array
    {
        return [
            'field' => 'year',
            'blueprint' => 'yachts.yachts',
            'condition' => 'dual_range',
            'min' => 2018,
            'max' => 2026,
            'minRange' => 1,
        ];
    }

    protected function rangeArguments(): array
    {
        return [
            'field' => 'cabins',
            'blueprint' => 'yachts.yachts',
            'condition' => 'gte',
            'min' => 1,
            'max' => 10,
        ];
    }

    protected function makeEntry($collection, $slug)
    {
        return EntryFactory::id($slug)->collection($collection)->slug($slug)->make();
    }
}
