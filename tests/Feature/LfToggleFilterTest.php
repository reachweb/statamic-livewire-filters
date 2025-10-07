<?php

namespace Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Livewire\Livewire;
use Reach\StatamicLivewireFilters\Http\Livewire\LfToggleFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;
use PHPUnit\Framework\Attributes\Test;

class LfToggleFilterTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    protected $collection;

    protected $blueprint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collection = Facades\Collection::make('cars')->save();
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
                            'handle' => 'car_type',
                            'field' => [
                                'type' => 'text',
                                'display' => 'Car Type',
                            ],
                        ],
                        [
                            'handle' => 'featured',
                            'field' => [
                                'type' => 'toggle',
                                'display' => 'Featured',
                            ],
                        ],
                        [
                            'handle' => 'price',
                            'field' => [
                                'type' => 'integer',
                                'display' => 'Price',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->blueprint->setHandle('car')->setNamespace('collections.'.$this->collection->handle())->save();

        $this->makeEntry($this->collection, 'suv-1')->set('title', 'Toyota RAV4')->set('car_type', 'SUV')->set('featured', true)->save();
        $this->makeEntry($this->collection, '4x4-1')->set('title', 'Jeep Wrangler')->set('car_type', '4x4')->set('featured', false)->save();
        $this->makeEntry($this->collection, 'sedan-1')->set('title', 'Honda Accord')->set('car_type', 'Sedan')->set('featured', true)->save();
        $this->makeEntry($this->collection, 'cabrio-1')->set('title', 'Mazda MX-5')->set('car_type', 'Cabrio')->set('featured', false)->save();

        // Setup for taxonomy test
        Facades\Taxonomy::make('brands')->save();
        Facades\Term::make()->taxonomy('brands')->inDefaultLocale()->slug('toyota')->data(['title' => 'Toyota'])->save();
        Facades\Term::make()->taxonomy('brands')->inDefaultLocale()->slug('honda')->data(['title' => 'Honda'])->save();
        Facades\Term::make()->taxonomy('brands')->inDefaultLocale()->slug('ford')->data(['title' => 'Ford'])->save();

        Facades\Collection::make('vehicles')->taxonomies(['brands'])->save();
        $vehiclesBlueprint = Facades\Blueprint::make()->setContents([
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
                            'handle' => 'brands',
                            'field' => [
                                'type' => 'terms',
                                'taxonomies' => [
                                    'brands',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $vehiclesBlueprint->setHandle('vehicles')->setNamespace('collections.vehicles')->save();
    }

    #[Test]
    public function it_renders_the_component_with_custom_label()
    {
        Livewire::test(LfToggleFilter::class, [
            'field' => 'car_type',
            'blueprint' => 'cars.car',
            'condition' => 'contains',
            'preset_value' => '4x4|SUV|Cabrio',
            'label' => 'Special Cars',
        ])
            ->assertSee('Special Cars')
            ->assertSet('selected', false);
    }

    #[Test]
    public function it_throws_a_field_not_found_exception_if_the_field_doesnt_exist()
    {
        $this->expectExceptionMessage('Field [not-a-field] not found');

        Livewire::test(LfToggleFilter::class, [
            'field' => 'not-a-field',
            'blueprint' => 'cars.car',
            'condition' => 'contains',
            'preset_value' => 'value',
            'label' => 'Test',
        ]);
    }

    #[Test]
    public function it_throws_a_blueprint_not_found_exception_if_the_blueprint_doesnt_exist()
    {
        $this->expectExceptionMessage('Blueprint [not-a-blueprint] not found');

        Livewire::test(LfToggleFilter::class, [
            'field' => 'car_type',
            'blueprint' => 'cars.not-a-blueprint',
            'condition' => 'contains',
            'preset_value' => 'value',
            'label' => 'Test',
        ]);
    }

    #[Test]
    public function it_toggles_on_and_dispatches_filter_updated_event_with_preset_value()
    {
        Livewire::test(LfToggleFilter::class, [
            'field' => 'car_type',
            'blueprint' => 'cars.car',
            'condition' => 'contains',
            'preset_value' => '4x4|SUV|Cabrio',
            'label' => 'Special Cars',
        ])
            ->assertSet('selected', false)
            ->set('selected', true)
            ->assertSet('selected', true)
            ->assertDispatched('filter-updated',
                field: 'car_type',
                condition: 'contains',
                payload: '4x4|SUV|Cabrio',
                modifier: 'any',
            );
    }

    #[Test]
    public function it_toggles_off_and_clears_filter()
    {
        Livewire::test(LfToggleFilter::class, [
            'field' => 'car_type',
            'blueprint' => 'cars.car',
            'condition' => 'contains',
            'preset_value' => '4x4|SUV|Cabrio',
            'label' => 'Special Cars',
        ])
            ->set('selected', true)
            ->assertDispatched('filter-updated')
            ->set('selected', false)
            ->assertSet('selected', false)
            ->assertDispatched('clear-filter',
                field: 'car_type',
                condition: 'contains',
                modifier: 'any',
            );
    }

    #[Test]
    public function it_works_with_is_condition()
    {
        Livewire::test(LfToggleFilter::class, [
            'field' => 'featured',
            'blueprint' => 'cars.car',
            'condition' => 'is',
            'preset_value' => 'true',
            'label' => 'Featured Only',
        ])
            ->set('selected', true)
            ->assertDispatched('filter-updated',
                field: 'featured',
                condition: 'is',
                payload: 'true',
                modifier: 'any',
            );
    }

    #[Test]
    public function it_works_with_taxonomy_condition_and_modifier()
    {
        Livewire::test(LfToggleFilter::class, [
            'field' => 'brands',
            'blueprint' => 'vehicles.vehicles',
            'condition' => 'taxonomy',
            'preset_value' => 'toyota|honda',
            'label' => 'Asian Brands',
            'modifier' => 'any',
        ])
            ->set('selected', true)
            ->assertDispatched('filter-updated',
                field: 'brands',
                condition: 'taxonomy',
                payload: 'toyota|honda',
                modifier: 'any',
            );
    }

    #[Test]
    public function it_works_with_query_scope_condition()
    {
        Livewire::test(LfToggleFilter::class, [
            'field' => 'car_type',
            'blueprint' => 'cars.car',
            'condition' => 'query_scope',
            'preset_value' => '4x4|SUV',
            'label' => 'Special Cars',
            'modifier' => 'multiselect',
        ])
            ->set('selected', true)
            ->assertDispatched('filter-updated',
                field: 'car_type',
                condition: 'query_scope',
                payload: '4x4|SUV',
                modifier: 'multiselect',
            );
    }

    #[Test]
    public function it_works_with_gte_condition()
    {
        Livewire::test(LfToggleFilter::class, [
            'field' => 'price',
            'blueprint' => 'cars.car',
            'condition' => 'gte',
            'preset_value' => '50000',
            'label' => 'Premium Cars',
        ])
            ->set('selected', true)
            ->assertDispatched('filter-updated',
                field: 'price',
                condition: 'gte',
                payload: '50000',
                modifier: 'any',
            );
    }

    #[Test]
    public function it_handles_preset_parameters_for_simple_conditions()
    {
        Livewire::test(LfToggleFilter::class, [
            'field' => 'car_type',
            'blueprint' => 'cars.car',
            'condition' => 'contains',
            'preset_value' => '4x4|SUV|Cabrio',
            'label' => 'Special Cars',
        ])
            ->assertSet('selected', false)
            ->dispatch('preset-params', ['car_type:contains' => '4x4|SUV|Cabrio', 'another_field:is' => 'value'])
            ->assertSet('selected', true);
    }

    #[Test]
    public function it_handles_preset_parameters_for_taxonomy_condition()
    {
        Livewire::test(LfToggleFilter::class, [
            'field' => 'brands',
            'blueprint' => 'vehicles.vehicles',
            'condition' => 'taxonomy',
            'preset_value' => 'toyota|honda',
            'label' => 'Asian Brands',
            'modifier' => 'any',
        ])
            ->assertSet('selected', false)
            ->dispatch('preset-params', ['taxonomy:brands:any' => 'toyota|honda'])
            ->assertSet('selected', true);
    }

    #[Test]
    public function it_handles_preset_parameters_for_query_scope_condition()
    {
        Livewire::test(LfToggleFilter::class, [
            'field' => 'car_type',
            'blueprint' => 'cars.car',
            'condition' => 'query_scope',
            'preset_value' => '4x4|SUV',
            'label' => 'Special Cars',
            'modifier' => 'multiselect',
        ])
            ->assertSet('selected', false)
            ->dispatch('preset-params', ['multiselect:car_type' => '4x4|SUV', 'query_scope' => 'multiselect'])
            ->assertSet('selected', true);
    }

    #[Test]
    public function it_clears_when_clear_option_event_is_fired()
    {
        Livewire::test(LfToggleFilter::class, [
            'field' => 'car_type',
            'blueprint' => 'cars.car',
            'condition' => 'contains',
            'preset_value' => '4x4|SUV|Cabrio',
            'label' => 'Special Cars',
        ])
            ->set('selected', true)
            ->dispatch('clear-option', [
                'field' => 'car_type',
                'value' => '4x4|SUV|Cabrio',
            ])
            ->assertSet('selected', false)
            ->assertDispatched('clear-filter',
                field: 'car_type',
                condition: 'contains',
            );
    }

    #[Test]
    public function it_clears_when_clear_all_filters_event_is_fired()
    {
        Livewire::test(LfToggleFilter::class, [
            'field' => 'car_type',
            'blueprint' => 'cars.car',
            'condition' => 'contains',
            'preset_value' => '4x4|SUV|Cabrio',
            'label' => 'Special Cars',
        ])
            ->set('selected', true)
            ->dispatch('clear-all-filters')
            ->assertSet('selected', false)
            ->assertDispatched('clear-filter',
                field: 'car_type',
                condition: 'contains',
            );
    }

    #[Test]
    public function it_does_not_clear_when_clear_option_event_is_for_different_field()
    {
        Livewire::test(LfToggleFilter::class, [
            'field' => 'car_type',
            'blueprint' => 'cars.car',
            'condition' => 'contains',
            'preset_value' => '4x4|SUV|Cabrio',
            'label' => 'Special Cars',
        ])
            ->set('selected', true)
            ->dispatch('clear-option', [
                'field' => 'different_field',
                'value' => 'value',
            ])
            ->assertSet('selected', true);
    }

    #[Test]
    public function it_can_clear_via_the_clear_method()
    {
        Livewire::test(LfToggleFilter::class, [
            'field' => 'car_type',
            'blueprint' => 'cars.car',
            'condition' => 'contains',
            'preset_value' => '4x4|SUV|Cabrio',
            'label' => 'Special Cars',
        ])
            ->set('selected', true)
            ->call('clear')
            ->assertSet('selected', false);
    }

    #[Test]
    public function it_works_with_custom_modifier()
    {
        Livewire::test(LfToggleFilter::class, [
            'field' => 'car_type',
            'blueprint' => 'cars.car',
            'condition' => 'contains',
            'preset_value' => '4x4|SUV',
            'label' => 'Special Cars',
            'modifier' => 'all',
        ])
            ->set('selected', true)
            ->assertDispatched('filter-updated',
                field: 'car_type',
                condition: 'contains',
                payload: '4x4|SUV',
                modifier: 'all',
            );
    }

    #[Test]
    public function it_maintains_state_across_updates()
    {
        Livewire::test(LfToggleFilter::class, [
            'field' => 'car_type',
            'blueprint' => 'cars.car',
            'condition' => 'contains',
            'preset_value' => '4x4|SUV|Cabrio',
            'label' => 'Special Cars',
        ])
            ->set('selected', true)
            ->assertSet('selected', true)
            ->call('$refresh')
            ->assertSet('selected', true);
    }

    #[Test]
    public function it_does_not_activate_when_preset_params_value_does_not_match()
    {
        Livewire::test(LfToggleFilter::class, [
            'field' => 'car_type',
            'blueprint' => 'cars.car',
            'condition' => 'contains',
            'preset_value' => '4x4|SUV|Cabrio',
            'label' => 'Special Cars',
        ])
            ->assertSet('selected', false)
            ->dispatch('preset-params', ['car_type:contains' => 'Sedan|Coupe'])
            ->assertSet('selected', false);
    }

    #[Test]
    public function it_activates_only_when_preset_params_value_exactly_matches()
    {
        Livewire::test(LfToggleFilter::class, [
            'field' => 'car_type',
            'blueprint' => 'cars.car',
            'condition' => 'contains',
            'preset_value' => '4x4|SUV|Cabrio',
            'label' => 'Special Cars',
        ])
            ->assertSet('selected', false)
            ->dispatch('preset-params', ['car_type:contains' => '4x4|SUV|Cabrio'])
            ->assertSet('selected', true);
    }

    #[Test]
    public function it_remains_inactive_when_different_field_has_matching_value()
    {
        Livewire::test(LfToggleFilter::class, [
            'field' => 'car_type',
            'blueprint' => 'cars.car',
            'condition' => 'contains',
            'preset_value' => '4x4|SUV|Cabrio',
            'label' => 'Special Cars',
        ])
            ->assertSet('selected', false)
            ->dispatch('preset-params', ['different_field:contains' => '4x4|SUV|Cabrio'])
            ->assertSet('selected', false);
    }

    #[Test]
    public function it_integrates_with_livewire_collection_filter_updates()
    {
        // Test that toggle filter can work alongside LivewireCollection
        $component = Livewire::test(LivewireCollection::class, [
            'params' => ['from' => 'cars'],
        ]);

        // Simulate a toggle being turned on
        $component->dispatch('filter-updated',
            field: 'car_type',
            condition: 'contains',
            payload: '4x4|SUV|Cabrio',
            modifier: 'any'
        );

        $component->assertSet('params', ['car_type:contains' => '4x4|SUV|Cabrio']);
    }

    protected function makeEntry($collection, $slug)
    {
        return EntryFactory::id($slug)->collection($collection)->slug($slug)->make();
    }
}
