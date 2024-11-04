<?php

namespace Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfDualRangeFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class LfDualRangeFilterTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    protected $collection;

    protected $blueprint;

    public function setUp(): void
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
                                'type' => 'text',
                                'display' => 'Year',
                                'listable' => 'hidden',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->blueprint->setHandle('yachts')->setNamespace('collections.'.$this->collection->handle())->save();

        $this->makeEntry($this->collection, 'yacht-a')->set('title', 'Luxury Yacht A')->set('cabins', 4)->set('year', 2020)->save();
        $this->makeEntry($this->collection, 'yacht-b')->set('title', 'Luxury Yacht B')->set('cabins', 6)->set('year', 2022)->save();
        $this->makeEntry($this->collection, 'yacht-c')->set('title', 'Luxury Yacht C')->set('cabins', 8)->set('year', 2024)->save();
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
            'defaultMin' => 4,
            'defaultMax' => 8,
            'minRange' => 2,
        ])
            ->assertSet('selectedMin', 4)
            ->assertSet('selectedMax', 8)
            ->assertSet('minRange', 2)
            ->assertSee('10');
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
            'defaultMin' => 4,
            'defaultMax' => 8,
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
            'defaultMin' => 4,
            'defaultMax' => 8,
        ]);
    }

    // #[Test]
    // public function it_enforces_minimum_range_between_handles()
    // {
    //     $component = Livewire::test(LfDualRangeFilter::class, [
    //         'field' => 'cabins',
    //         'blueprint' => 'yachts.yachts',
    //         'condition' => 'between',
    //         'min' => 2,
    //         'max' => 10,
    //         'defaultMin' => 4,
    //         'defaultMax' => 8,
    //         'minRange' => 2,
    //     ]);

    //     // Try to set min too close to max
    //     $component->set('selectedMin', 7)
    //         ->assertSet('selectedMin', 6) // Should be forced to max - minRange
    //         ->assertSet('selectedMax', 8);

    //     // Try to set max too close to min
    //     $component->set('selectedMax', 5)
    //         ->assertSet('selectedMin', 4)
    //         ->assertSet('selectedMax', 6); // Should be forced to min + minRange
    // }

    #[Test]
    public function it_dispatches_filter_updated_event_when_values_change()
    {
        Livewire::test(LfDualRangeFilter::class, [
            'field' => 'cabins',
            'blueprint' => 'yachts.yachts',
            'condition' => 'dual-range',
            'min' => 2,
            'max' => 10,
            'minRange' => 2,
        ])
            ->set('selectedMin', 5)
            ->assertDispatched('filter-updated',
                field: 'cabins',
                condition: 'dual-range',
                payload: ['min' => 5, 'max' => 10],
                command: 'replace',
            );
    }

    #[Test]
    public function collection_component_handles_dual_range_filter_events()
    {
        Livewire::test(LivewireCollection::class, ['params' => ['from' => 'yachts']])
            ->dispatch('filter-updated',
                field: 'cabins',
                condition: 'dual-range',
                payload: ['min' => 5, 'max' => 10],
                command: 'replace',
                modifier: null,
            )
            ->assertSet('params', [
                'cabins:gte' => 5,
                'cabins:lte' => 10,
            ])
            ->dispatch('filter-updated',
                field: 'cabins',
                condition: 'dual-range',
                payload: ['min' => 5, 'max' => 8],
                command: 'replace',
                modifier: null,
            )
            ->assertSet('params', [
                'cabins:gte' => 5,
                'cabins:lte' => 8,
            ]);
    }

    #[Test]
    public function collection_component_handles_different_conditions_by_modifier()
    {
        Livewire::test(LivewireCollection::class, ['params' => ['from' => 'yachts']])
            ->dispatch('filter-updated',
                field: 'cabins',
                condition: 'dual-range',
                payload: ['min' => 5, 'max' => 10],
                command: 'replace',
                modifier: 'gt|lt',
            )
            ->assertSet('params', [
                'cabins:gt' => 5,
                'cabins:lt' => 10,
            ]);
    }

    // #[Test]
    // public function it_loads_preset_params_correctly()
    // {
    //     Livewire::test(LfDualRangeFilter::class, [
    //         'field' => 'cabins',
    //         'blueprint' => 'yachts.yachts',
    //         'condition' => 'between',
    //         'min' => 2,
    //         'max' => 10,
    //         'defaultMin' => 4,
    //         'defaultMax' => 8,
    //     ])
    //         ->assertSet('selectedMin', 4)
    //         ->assertSet('selectedMax', 8)
    //         ->dispatch('preset-params', ['cabins:between' => ['min' => 5, 'max' => 7]])
    //         ->assertSet('selectedMin', 5)
    //         ->assertSet('selectedMax', 7);
    // }

    protected function makeEntry($collection, $slug)
    {
        return EntryFactory::id($slug)->collection($collection)->slug($slug)->make();
    }
}
