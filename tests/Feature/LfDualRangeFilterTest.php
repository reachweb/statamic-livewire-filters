<?php

namespace Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfDualRangeFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfTags;
use Reach\StatamicLivewireFilters\Http\Livewire\LfToggleFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class LfDualRangeFilterTest extends TestCase
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
                    ],
                ],
            ],
        ]);
        $this->blueprint->setHandle('yachts')->setNamespace('collections.'.$this->collection->handle())->save();

        $this->makeEntry($this->collection, 'yacht-a')->set('title', 'Luxury Yacht A')->set('cabins', 4)->set('year', '2020-05-22')->save();
        $this->makeEntry($this->collection, 'yacht-b')->set('title', 'Luxury Yacht B')->set('cabins', 6)->set('year', '2022-02-02')->save();
        $this->makeEntry($this->collection, 'yacht-c')->set('title', 'Luxury Yacht C')->set('cabins', 8)->set('year', '2024-12-30')->save();
    }

    #[Test]
    public function it_renders_the_component_with_correct_min_and_max_values()
    {
        Livewire::test(LfDualRangeFilter::class, [
            'field' => 'cabins',
            'blueprint' => 'yachts.yachts',
            'condition' => 'gte|lte',
            'min' => 2,
            'max' => 10,
            'minRange' => 2,
        ])
            ->assertSet('selectedMin', 2)
            ->assertSet('selectedMax', 10)
            ->assertSet('minRange', 2)
            ->assertSee('10');
    }

    #[Test]
    public function it_renders_histogram_bars_only_when_requested()
    {
        $arguments = [
            'field' => 'cabins',
            'blueprint' => 'yachts.yachts',
            'condition' => 'dual_range',
            'min' => 2,
            'max' => 10,
        ];

        Livewire::test(LfDualRangeFilter::class, $arguments)
            ->assertDontSeeHtml('x-for="bin in bins"');

        Livewire::test(LfDualRangeFilter::class, array_merge($arguments, ['histogram' => 20]))
            ->assertSeeHtml('x-for="bin in bins"');
    }

    #[Test]
    public function it_throws_a_field_not_found_exception_if_the_field_doesnt_exist()
    {
        $this->expectExceptionMessage('Field [not-a-field] not found');

        Livewire::test(LfDualRangeFilter::class, [
            'field' => 'not-a-field',
            'blueprint' => 'yachts.yachts',
            'condition' => 'between',
            'min' => 2,
            'max' => 10,
        ]);
    }

    #[Test]
    public function it_throws_a_blueprint_not_found_exception_if_the_blueprint_doesnt_exist()
    {
        $this->expectExceptionMessage('Blueprint [not-a-blueprint] not found');

        Livewire::test(LfDualRangeFilter::class, [
            'field' => 'cabins',
            'blueprint' => 'yachts.not-a-blueprint',
            'condition' => 'between',
            'min' => 2,
            'max' => 10,
        ]);
    }

    #[Test]
    public function it_enforces_minimum_range_between_handles()
    {
        $component = Livewire::test(LfDualRangeFilter::class, [
            'field' => 'cabins',
            'blueprint' => 'yachts.yachts',
            'condition' => 'dual_range',
            'min' => 2,
            'max' => 8,
            'minRange' => 2,
        ]);

        // Try to set min too close to max
        $component->set('selectedMin', 7)
            ->assertSet('selectedMin', 6) // Should be forced to max - minRange
            ->assertSet('selectedMax', 8);

        // Try to set max too close to min
        $component->set('selectedMax', 7)
            ->assertSet('selectedMin', 6)
            ->assertSet('selectedMax', 8); // Should be forced to min + minRange
    }

    #[Test]
    public function it_dispatches_filter_updated_event_when_values_change()
    {
        Livewire::test(LfDualRangeFilter::class, [
            'field' => 'cabins',
            'blueprint' => 'yachts.yachts',
            'condition' => 'dual_range',
            'min' => 2,
            'max' => 10,
            'minRange' => 2,
        ])
            ->set('selectedMin', 5)
            ->assertDispatched('filter-updated',
                field: 'cabins',
                condition: 'dual_range',
                payload: ['min' => 5, 'max' => 10],
            );
    }

    #[Test]
    public function it_can_be_reset_using_clear_method()
    {
        Livewire::test(LfDualRangeFilter::class, [
            'field' => 'cabins',
            'blueprint' => 'yachts.yachts',
            'condition' => 'dual_range',
            'min' => 2,
            'max' => 10,
            'minRange' => 2,
        ])
            ->set('selectedMin', 5)
            ->call('clear')
            ->assertSet('selectedMin', 2)
            ->assertDispatched('clear-filter',
                field: 'cabins',
                condition: 'dual_range',
            )
            ->assertDispatched('dual-range-preset-values',
                min: 2,
                max: 10,
            );
    }

    #[Test]
    public function collection_component_handles_dual_range_filter_events()
    {
        Livewire::test(LivewireCollection::class, ['params' => ['from' => 'yachts']])
            ->dispatch('filter-updated',
                field: 'cabins',
                condition: 'dual_range',
                payload: ['min' => 5, 'max' => 10],
                modifier: 'any',
            )
            ->assertSet('params', [
                'cabins:gte' => 5,
                'cabins:lte' => 10,
            ])
            ->dispatch('filter-updated',
                field: 'cabins',
                condition: 'dual_range',
                payload: ['min' => 5, 'max' => 8],
                modifier: 'any',
            )
            ->assertSet('params', [
                'cabins:gte' => 5,
                'cabins:lte' => 8,
            ]);
    }

    #[Test]
    public function it_sends_a_clear_filter_event_if_the_values_are_back_to_default()
    {
        Livewire::test(LfDualRangeFilter::class, [
            'field' => 'cabins',
            'blueprint' => 'yachts.yachts',
            'condition' => 'dual_range',
            'min' => 2,
            'max' => 10,
            'minRange' => 2,
        ])
            ->set('selectedMin', 5)
            ->assertDispatched('filter-updated',
                field: 'cabins',
                condition: 'dual_range',
                payload: ['min' => 5, 'max' => 10],
            )
            ->set('selectedMin', 2)
            ->assertDispatched('clear-filter',
                field: 'cabins',
                condition: 'dual_range',
            );
    }

    #[Test]
    public function it_clears_a_filter_using_the_clear_option_event()
    {
        Livewire::test(LfDualRangeFilter::class, [
            'field' => 'cabins',
            'blueprint' => 'yachts.yachts',
            'condition' => 'dual_range',
            'min' => 2,
            'max' => 10,
            'minRange' => 2,
        ])
            ->set('selectedMin', 5)
            ->assertDispatched('filter-updated',
                field: 'cabins',
                condition: 'dual_range',
                payload: ['min' => 5, 'max' => 10],
            )
            ->set('selectedMax', 8)
            ->assertDispatched('filter-updated',
                field: 'cabins',
                condition: 'dual_range',
                payload: ['min' => 5, 'max' => 8],
            )
            ->dispatch('clear-option', ['field' => 'cabins', 'value' => 5])
            ->assertSet('selectedMin', 2)
            ->assertSet('selectedMax', 8)
            ->assertDispatched('filter-updated',
                field: 'cabins',
                condition: 'dual_range',
                payload: ['min' => 2, 'max' => 8],
            )
            ->assertDispatched('dual-range-preset-values',
                min: 2,
                max: 8,
            )
            ->dispatch('clear-option', ['field' => 'cabins', 'value' => 8])
            ->assertSet('selectedMin', 2)
            ->assertSet('selectedMax', 10)
            ->assertDispatched('clear-filter',
                field: 'cabins',
                condition: 'dual_range',
            );
    }

    #[Test]
    public function it_clears_the_value_when_clear_all_filters_event_is_fired()
    {
        Livewire::test(LfDualRangeFilter::class, [
            'field' => 'cabins',
            'blueprint' => 'yachts.yachts',
            'condition' => 'dual_range',
            'min' => 2,
            'max' => 10,
            'minRange' => 2,
        ])
            ->set('selectedMin', 5)
            ->dispatch('clear-all-filters')
            ->assertSet('selectedMin', 2)
            ->assertDispatched('clear-filter',
                field: 'cabins',
                condition: 'dual_range'
            );
    }

    #[Test]
    public function collection_component_handles_different_conditions_by_modifier()
    {
        Livewire::test(LivewireCollection::class, ['params' => ['from' => 'yachts']])
            ->dispatch('filter-updated',
                field: 'cabins',
                condition: 'dual_range',
                payload: ['min' => 5, 'max' => 10],
                command: 'replace',
                modifier: 'gt|lt',
            )
            ->assertSet('params', [
                'cabins:gt' => 5,
                'cabins:lt' => 10,
            ]);
    }

    #[Test]
    public function it_can_handle_date_field_and_dispatch_correct_event()
    {
        Livewire::test(LfDualRangeFilter::class, [
            'field' => 'year',
            'blueprint' => 'yachts.yachts',
            'condition' => 'dual_range',
            'min' => 2018,
            'max' => 2024,
            'minRange' => 1,
        ])
            ->set('selectedMin', 2020)
            ->assertDispatched('filter-updated',
                field: 'year',
                condition: 'dual_range',
                payload: ['min' => '2020-01-01', 'max' => '2024-12-31'],
                modifier: 'is_after|is_before',
            );
    }

    #[Test]
    public function a_date_field_clears_its_date_keys_before_it_has_dispatched()
    {
        Livewire::test(LfDualRangeFilter::class, [
            'field' => 'year',
            'blueprint' => 'yachts.yachts',
            'condition' => 'dual_range',
            'min' => 2018,
            'max' => 2024,
        ])
            ->dispatch('clear-all-filters')
            ->assertDispatched('clear-filter', field: 'year', condition: 'dual_range', modifier: 'is_after|is_before');

        Livewire::test(LivewireCollection::class, ['params' => ['from' => 'yachts', 'year:is_after' => '2020-01-01', 'year:is_before' => '2024-12-31']])
            ->dispatch('clear-filter', field: 'year', condition: 'dual_range', modifier: 'is_after|is_before')
            ->assertSet('params', []);
    }

    #[Test]
    public function the_date_comparisons_only_apply_to_the_dual_range_slider()
    {
        Livewire::test(LfToggleFilter::class, [
            'field' => 'year',
            'blueprint' => 'yachts.yachts',
            'condition' => 'dual_range',
            'modifier' => 'gte|lte',
            'preset_value' => ['min' => '2020-01-01', 'max' => '2024-12-31'],
        ])
            ->set('selected', true)
            ->assertDispatched('filter-updated', field: 'year', condition: 'dual_range', modifier: 'gte|lte');
    }

    #[Test]
    public function collection_component_handles_date_field()
    {
        Livewire::test(LivewireCollection::class, ['params' => ['from' => 'yachts']])
            ->dispatch('filter-updated',
                field: 'year',
                condition: 'dual_range',
                payload: ['min' => '2020-01-01', 'max' => '2024-12-31'],
                modifier: 'is_after|is_before',
            )
            ->assertSet('params', [
                'year:is_after' => '2020-01-01',
                'year:is_before' => '2024-12-31',
            ]);
    }

    #[Test]
    public function it_loads_preset_params_correctly()
    {
        Livewire::test(LfDualRangeFilter::class, [
            'field' => 'cabins',
            'blueprint' => 'yachts.yachts',
            'condition' => 'dual_range',
            'min' => 2,
            'max' => 10,
            'minRange' => 2,
        ])
            ->dispatch('preset-params', ['cabins:gte' => 5, 'cabins:lte' => 8])
            ->assertDispatched('dual-range-preset-values', min: 5, max: 8)
            ->assertSet('selectedMin', 5)
            ->assertSet('selectedMax', 8);
    }

    #[Test]
    public function it_loads_preset_params_with_custom_modifier_correctly()
    {
        Livewire::test(LfDualRangeFilter::class, [
            'field' => 'cabins',
            'blueprint' => 'yachts.yachts',
            'condition' => 'dual_range',
            'min' => 2,
            'max' => 10,
            'minRange' => 2,
            'modifier' => 'gt|lt',
        ])
            ->dispatch('preset-params', ['cabins:gt' => 5])
            ->assertDispatched('dual-range-preset-values', min: 5, max: 10)
            ->assertSet('selectedMin', 5)
            ->assertSet('selectedMax', 10);
    }

    #[Test]
    public function it_loads_date_preset_params_as_years_and_keeps_updating()
    {
        Livewire::test(LfDualRangeFilter::class, [
            'field' => 'year',
            'blueprint' => 'yachts.yachts',
            'condition' => 'dual_range',
            'min' => 2018,
            'max' => 2026,
            'minRange' => 1,
        ])
            ->dispatch('preset-params', ['year:is_after' => '2022-01-01', 'year:is_before' => '2025-12-31'])
            ->assertDispatched('dual-range-preset-values', min: 2022, max: 2025)
            ->assertSet('selectedMin', 2022)
            ->assertSet('selectedMax', 2025)
            ->set('selectedMin', 2019)
            ->assertDispatched('filter-updated',
                field: 'year',
                condition: 'dual_range',
                payload: ['min' => '2019-01-01', 'max' => '2025-12-31'],
                modifier: 'is_after|is_before',
            );
    }

    #[Test]
    public function it_clears_a_date_preset_using_the_clear_option_event()
    {
        Livewire::test(LfDualRangeFilter::class, [
            'field' => 'year',
            'blueprint' => 'yachts.yachts',
            'condition' => 'dual_range',
            'min' => 2018,
            'max' => 2026,
            'minRange' => 1,
        ])
            ->dispatch('preset-params', ['year:is_after' => '2022-01-01', 'year:is_before' => '2025-12-31'])
            ->dispatch('clear-option', ['field' => 'year', 'value' => '2022-01-01'])
            ->assertSet('selectedMin', 2018)
            ->assertSet('selectedMax', 2025)
            ->assertDispatched('filter-updated',
                field: 'year',
                condition: 'dual_range',
                payload: ['min' => '2018-01-01', 'max' => '2025-12-31'],
                modifier: 'is_after|is_before',
            );
    }

    #[Test]
    public function it_clears_only_the_tagged_bound_of_a_same_year_date_preset()
    {
        Livewire::test(LfDualRangeFilter::class, [
            'field' => 'year',
            'blueprint' => 'yachts.yachts',
            'condition' => 'dual_range',
            'min' => 2018,
            'max' => 2026,
            'minRange' => 1,
        ])
            ->dispatch('preset-params', ['year:is_after' => '2022-01-01', 'year:is_before' => '2022-12-31'])
            ->dispatch('clear-option', ['field' => 'year', 'value' => '2022-01-01', 'condition' => 'is_after'])
            ->assertSet('selectedMin', 2018)
            ->assertSet('selectedMax', 2022)
            ->dispatch('preset-params', ['year:is_after' => '2022-01-01', 'year:is_before' => '2022-12-31'])
            ->dispatch('clear-option', ['field' => 'year', 'value' => '2022-12-31', 'condition' => 'is_before'])
            ->assertSet('selectedMin', 2022)
            ->assertSet('selectedMax', 2026);
    }

    #[Test]
    public function it_clears_only_the_tagged_bound_of_an_equal_numeric_range()
    {
        // Both tags share the value 6, so only the condition identifies the clicked one.
        $maxTag = [
            'field' => 'cabins',
            'value' => '6',
            'fieldLabel' => 'Cabins',
            'optionLabel' => '6',
            'condition' => 'lte',
        ];

        Livewire::test(LfTags::class, ['fields' => 'cabins', 'blueprint' => 'yachts.yachts'])
            ->dispatch('tags-updated', ['cabins:gte' => '6', 'cabins:lte' => '6'])
            ->call('removeOption', 'cabins', '6', 'lte')
            ->assertDispatched('clear-option', $maxTag);

        Livewire::test(LfDualRangeFilter::class, [
            'field' => 'cabins',
            'blueprint' => 'yachts.yachts',
            'condition' => 'dual_range',
            'min' => 2,
            'max' => 10,
            'minRange' => 0,
        ])
            ->dispatch('preset-params', ['cabins:gte' => '6', 'cabins:lte' => '6'])
            ->dispatch('clear-option', $maxTag)
            ->assertSet('selectedMin', '6')
            ->assertSet('selectedMax', 10);
    }

    #[Test]
    public function it_clears_by_value_when_a_tag_condition_is_not_a_string()
    {
        Livewire::test(LfDualRangeFilter::class, [
            'field' => 'cabins',
            'blueprint' => 'yachts.yachts',
            'condition' => 'dual_range',
            'min' => 2,
            'max' => 10,
            'minRange' => 2,
        ])
            ->set('selectedMin', 5)
            ->dispatch('clear-option', ['field' => 'cabins', 'value' => 5, 'condition' => ['lte']])
            ->assertSet('selectedMin', 2)
            ->assertSet('selectedMax', 10);
    }

    #[Test]
    public function it_loads_bare_year_date_presets_and_ignores_unparseable_ones()
    {
        Livewire::test(LfDualRangeFilter::class, [
            'field' => 'year',
            'blueprint' => 'yachts.yachts',
            'condition' => 'dual_range',
            'min' => 2018,
            'max' => 2025,
            'minRange' => 1,
        ])
            ->dispatch('preset-params', ['year:is_after' => '2021', 'year:is_before' => 'not-a-date'])
            ->assertSet('selectedMin', 2021)
            ->assertSet('selectedMax', 2025)
            ->assertDispatched('dual-range-preset-values', min: 2021, max: 2025);
    }

    #[Test]
    public function it_forbids_client_tampering_with_the_condition_property()
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(LfDualRangeFilter::class, [
            'field' => 'cabins',
            'blueprint' => 'yachts.yachts',
            'condition' => 'dual_range',
            'min' => 2,
            'max' => 10,
            'minRange' => 2,
        ])
            ->set('condition', 'is');
    }

    protected function makeEntry($collection, $slug)
    {
        return EntryFactory::id($slug)->collection($collection)->slug($slug)->make();
    }
}
