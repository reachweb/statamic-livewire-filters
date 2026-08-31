<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfDualRangeFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfRangeFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\Traits\HandleFieldBounds;
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
    public function it_updates_bounds_to_match_entries_narrowed_by_other_filters()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfDualRangeFilter::class, $this->dualRangeArguments())
            ->dispatch('params-updated', ['category:is' => 'catamaran'])
            ->assertSet('dynamicMin', 4)
            ->assertSet('dynamicMax', 6)
            ->assertDispatched('range-bounds-updated', min: 4, max: 6, disabled: false);
    }

    #[Test]
    public function it_excludes_its_own_dual_range_params_when_computing_bounds()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfDualRangeFilter::class, $this->dualRangeArguments())
            ->dispatch('params-updated', ['cabins:gte' => 6, 'cabins:lte' => 8])
            ->assertSet('dynamicMin', 4)
            ->assertSet('dynamicMax', 8);
    }

    #[Test]
    public function it_excludes_its_own_date_params_when_computing_bounds()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfDualRangeFilter::class, $this->dateDualRangeArguments())
            ->dispatch('params-updated', ['year:is_after' => '2022-01-01', 'year:is_before' => '2024-12-31'])
            ->assertSet('dynamicMin', 2020)
            ->assertSet('dynamicMax', 2024);
    }

    #[Test]
    public function it_computes_year_bounds_for_date_fields()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfDualRangeFilter::class, $this->dateDualRangeArguments())
            ->dispatch('params-updated', ['category:is' => 'catamaran'])
            ->assertSet('dynamicMin', 2020)
            ->assertSet('dynamicMax', 2022)
            ->assertDispatched('range-bounds-updated', min: 2020, max: 2022, disabled: false);
    }

    #[Test]
    public function it_falls_back_to_absolute_bounds_when_no_entries_match()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfDualRangeFilter::class, $this->dualRangeArguments())
            ->dispatch('params-updated', ['category:is' => 'nonexistent'])
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
            ->dispatch('params-updated', ['category:is' => 'special'])
            ->assertSet('dynamicMin', 4)
            ->assertSet('dynamicMax', 8);
    }

    #[Test]
    public function it_flags_disabled_and_widens_the_span_below_min_range()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfDualRangeFilter::class, $this->dualRangeArguments())
            ->dispatch('params-updated', ['category:is' => 'monohull'])
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
            ->dispatch('params-updated', ['category:is' => 'catamaran'])
            ->assertSet('selectedMin', 4)
            ->assertSet('selectedMax', 6)
            ->assertNotDispatched('filter-updated')
            ->assertDispatched('range-bounds-updated', min: 4, max: 6, selectedMin: 4, selectedMax: 6, disabled: false)
            ->dispatch('params-updated', [])
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
            ->dispatch('params-updated', ['category:is' => 'catamaran', 'cabins:gte' => 5, 'cabins:lte' => 10])
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
            ->dispatch('params-updated', ['category:is' => 'catamaran', 'cabins:gte' => 5, 'cabins:lte' => 8])
            ->assertDispatched('range-bounds-updated', min: 4, max: 8, selectedMin: 5, selectedMax: 8, disabled: false)
            ->dispatch('params-updated', ['cabins:gte' => 5, 'cabins:lte' => 8])
            ->assertSet('selectedMin', 5)
            ->assertSet('selectedMax', 8)
            ->assertNotDispatched('filter-updated')
            ->assertDispatched('range-bounds-updated', min: 4, max: 8, selectedMin: 5, selectedMax: 8, disabled: false);
    }

    #[Test]
    public function it_keeps_the_single_range_display_stable_as_other_filters_change()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfRangeFilter::class, $this->rangeArguments())
            ->set('selected', 8)
            ->dispatch('params-updated', ['category:is' => 'catamaran', 'cabins:gte' => 8])
            ->assertSet('selected', 8)
            ->assertDispatched('range-bounds-updated', min: 4, max: 8, selected: 8, disabled: false)
            ->dispatch('params-updated', ['cabins:gte' => 8])
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
            ->dispatch('params-updated', ['category:is' => 'monohull', 'cabins:gte' => 5, 'cabins:lte' => 7])
            ->assertSet('boundsDisabled', false)
            ->assertDispatched('range-bounds-updated', min: 5, max: 8, selectedMin: 5, selectedMax: 7, disabled: false);
    }

    #[Test]
    public function it_keeps_an_active_single_range_enabled_when_bounds_collapse_to_its_value()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfRangeFilter::class, $this->rangeArguments())
            ->set('selected', 8)
            ->dispatch('params-updated', ['category:is' => 'monohull', 'cabins:gte' => 8])
            ->assertSet('boundsDisabled', false)
            ->assertDispatched('range-bounds-updated', min: 1, max: 10, selected: 8, disabled: false);
    }

    #[Test]
    public function it_falls_back_to_absolute_bounds_when_matches_lie_outside_them()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        $this->makeEntry($this->collection, 'yacht-tiny')->set('title', 'Tiny Yacht')->set('cabins', 1)->set('category', 'tiny')->save();

        Livewire::test(LfDualRangeFilter::class, $this->dualRangeArguments())
            ->dispatch('params-updated', ['category:is' => 'tiny'])
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
            ->dispatch('params-updated', ['category:is' => 'huge'])
            ->assertSet('dynamicMin', 1)
            ->assertSet('dynamicMax', 10)
            ->assertSet('boundsDisabled', false);
    }

    #[Test]
    public function it_clears_the_filter_when_the_selection_returns_to_the_dynamic_bounds()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfDualRangeFilter::class, $this->dualRangeArguments())
            ->dispatch('params-updated', [])
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
            ->dispatch('params-updated', ['category:is' => 'catamaran'])
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
    public function it_updates_bounds_for_the_single_range_filter()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfRangeFilter::class, $this->rangeArguments())
            ->dispatch('params-updated', ['category:is' => 'catamaran'])
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
            ->dispatch('params-updated', ['category:is' => 'monohull'])
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
            ->dispatch('params-updated', ['category:is' => 'catamaran'])
            ->assertSet('dynamicMin', 4)
            ->assertSet('dynamicMax', 6)
            ->dispatch('params-updated', [])
            ->assertSet('dynamicMin', 4)
            ->assertSet('dynamicMax', 8)
            ->assertSet('boundsDisabled', false);
    }

    #[Test]
    public function it_normalizes_string_tag_bounds_to_numbers()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Livewire::test(LfDualRangeFilter::class, array_merge($this->dualRangeArguments(), ['min' => '2', 'max' => '10']))
            ->dispatch('params-updated', ['category:is' => 'nonexistent'])
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
