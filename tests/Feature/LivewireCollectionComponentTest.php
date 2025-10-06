<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection as LivewireCollectionComponent;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class LivewireCollectionComponentTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    private $music;

    protected function setUp(): void
    {
        parent::setUp();

        $this->music = Facades\Collection::make('music')->save();

        Facades\Taxonomy::make('colors')->save();
        Facades\Term::make()->taxonomy('colors')->inDefaultLocale()->slug('red')->data(['title' => 'Red'])->save();
        Facades\Term::make()->taxonomy('colors')->inDefaultLocale()->slug('black')->data(['title' => 'Black'])->save();
        Facades\Term::make()->taxonomy('colors')->inDefaultLocale()->slug('yellow')->data(['title' => 'Yellow'])->save();
        Facades\Collection::make('clothes')->taxonomies(['colors'])->save();

        $clothesBlueprint = $this->blueprint = Facades\Blueprint::make()->setContents([
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

                            'handle' => 'colors',
                            'field' => [
                                'type' => 'terms',
                                'taxonomies' => [
                                    'colors',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $clothesBlueprint->setHandle('clothes')->setNamespace('collections.clothes')->save();

        EntryFactory::collection('clothes')->slug('red-shirt')->data(['title' => 'Red Shirt', 'colors' => ['red']])->create();
        EntryFactory::collection('clothes')->slug('black-shirt')->data(['title' => 'Black Shirt', 'colors' => ['black']])->create();
        EntryFactory::collection('clothes')->slug('yellow-shirt')->data(['title' => 'Yellow Shirt', 'colors' => ['yellow']])->create();
    }

    /** @test */
    public function it_loads_the_livewire_component_with_parameters()
    {
        $params = [
            'from' => 'music',
            'title:is' => 'I Love Guitars',
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('params', ['title:is' => 'I Love Guitars'])
            ->assertSet('collections', 'music');
    }

    /** @test */
    public function it_loads_the_livewire_component_with_parameters_and_changes_them_after_filter_updated_event()
    {
        $params = [
            'from' => 'music',
            'title:is' => 'I Love Guitars',
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('params', ['title:is' => 'I Love Guitars'])
            ->dispatch('filter-updated',
                field: 'item_options',
                condition: 'is',
                payload: 'option1',
                modifier: 'any',
            )
            ->assertSet('params', [
                'title:is' => 'I Love Guitars',
                'item_options:is' => 'option1',
            ])
            ->assertSet('activeFilters', 2)
            ->assertSet('entriesCount', 0)
            ->assertDispatched('entries-updated')
            ->dispatch('filter-updated',
                field: 'title',
                condition: 'is',
                payload: 'Test',
                modifier: 'any',
            )
            ->assertSet('params', [
                'title:is' => 'Test',
                'item_options:is' => 'option1',
            ])
            ->assertDispatched('entries-updated')
            ->dispatch('filter-updated',
                field: 'item_options',
                condition: 'is',
                payload: ['option1', 'option2'],
                modifier: 'any',
            )
            ->assertSet('params', [
                'title:is' => 'Test',
                'item_options:is' => 'option1|option2',
            ])
            ->assertDispatched('entries-updated')
            ->dispatch('filter-updated',
                field: 'item_options',
                condition: 'is',
                payload: ['option2'],
                modifier: 'any',
            )
            ->assertDispatched('entries-updated')
            ->assertSet('params', [
                'title:is' => 'Test',
                'item_options:is' => 'option2',
            ]);
    }

    /** @test */
    public function it_works_for_taxonomy_terms()
    {
        $params = [
            'from' => 'clothes',
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('collections', 'clothes')
            ->dispatch('filter-updated',
                field: 'colors',
                condition: 'taxonomy',
                payload: ['red'],
                modifier: 'any',
            )
            ->assertSet('params', [
                'taxonomy:colors:any' => 'red',
            ])
            ->dispatch('filter-updated',
                field: 'colors',
                condition: 'taxonomy',
                payload: ['red', 'yellow'],
                modifier: 'any',
            )
            ->assertSet('params', [
                'taxonomy:colors:any' => 'red|yellow',
            ])
            ->dispatch('filter-updated',
                field: 'colors',
                condition: 'taxonomy',
                payload: ['yellow'],
                modifier: 'any',
            )
            ->assertSet('params', [
                'taxonomy:colors:any' => 'yellow',
            ]);
    }

    /** @test */
    public function it_gets_a_list_of_allowed_filters_by_the_parameter()
    {
        $params = [
            'from' => 'music',
            'allowed_filters' => 'sizes:is|taxonomy:colors:any|query_scope:multiselect|multiselect:brand',
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('allowedFilters', function ($collection) {
                $this->assertInstanceOf(Collection::class, $collection);
                $this->assertEquals(['sizes:is', 'taxonomy:colors:any', 'query_scope:multiselect', 'multiselect:brand'], $collection->all());

                return true;
            });
    }

    /** @test */
    public function allowed_filters_is_false_if_not_set()
    {
        $params = [
            'from' => 'music',
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('allowedFilters', false);
    }

    /** @test */
    public function check_that_filter_works_if_allowed_filters_not_set()
    {
        $params = [
            'from' => 'clothes',
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('collections', 'clothes')
            ->dispatch('filter-updated',
                field: 'colors',
                condition: 'taxonomy',
                payload: 'red',
                command: 'add',
                modifier: 'any',
            )
            ->assertDontSee('Yellow Shirt')
            ->assertDontSee('Black Shirt');
    }

    /** @test */
    public function check_that_filter_gets_ignored_if_not_in_allowed_filters()
    {
        $params = [
            'from' => 'clothes',
            'allowed_filters' => 'random_field:is',
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('collections', 'clothes')
            ->dispatch('filter-updated',
                field: 'colors',
                condition: 'taxonomy',
                payload: 'red',
                command: 'add',
                modifier: 'any',
            )
            ->assertSee('Yellow Shirt')
            ->assertSee('Black Shirt');
    }

    /** @test */
    public function it_sets_query_scope_parameters()
    {
        $params = [
            'from' => 'clothes',
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('collections', 'clothes')
            ->dispatch('filter-updated',
                field: 'sizes',
                condition: 'query_scope',
                payload: ['xl'],
                modifier: 'multiselect',
            )
            ->assertSet('params', [
                'query_scope' => 'multiselect',
                'multiselect:sizes' => 'xl',
            ])
            ->dispatch('filter-updated',
                field: 'sizes',
                condition: 'query_scope',
                payload: ['xl', 'l'],
                modifier: 'multiselect',
            )
            ->assertSet('params', [
                'query_scope' => 'multiselect',
                'multiselect:sizes' => 'xl|l',
            ])
            ->dispatch('filter-updated',
                field: 'sizes',
                condition: 'query_scope',
                payload: ['xl'],
                modifier: 'multiselect',
            )
            ->assertSet('params', [
                'query_scope' => 'multiselect',
                'multiselect:sizes' => 'xl',
            ])
            ->dispatch('filter-updated',
                field: 'sizes',
                condition: 'query_scope',
                payload: [],
                modifier: 'multiselect',
            )
            ->assertSet('params', [])
            ->dispatch('filter-updated',
                field: 'sizes',
                condition: 'query_scope',
                payload: ['xl'],
                modifier: 'multiselect',
            )
            ->assertSet('params', [
                'query_scope' => 'multiselect',
                'multiselect:sizes' => 'xl',
            ])
            ->dispatch('filter-updated',
                field: 'sizes',
                condition: 'query_scope',
                payload: ['l'],
                modifier: 'multiselect',
            )
            ->assertSet('params', [
                'query_scope' => 'multiselect',
                'multiselect:sizes' => 'l',
            ]);
    }

    /** @test */
    public function it_can_set_multiple_query_scope_parameters()
    {
        $params = [
            'from' => 'clothes',
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('collections', 'clothes')
            ->dispatch('filter-updated',
                field: 'sizes',
                condition: 'query_scope',
                payload: ['xl'],
                modifier: 'multiselect',
            )
            ->assertSet('params', [
                'query_scope' => 'multiselect',
                'multiselect:sizes' => 'xl',
            ])
            ->dispatch('filter-updated',
                field: 'colors',
                condition: 'query_scope',
                payload: ['red', 'black'],
                modifier: 'some_other_scope',
            )
            ->assertSet('params', [
                'query_scope' => 'multiselect|some_other_scope',
                'multiselect:sizes' => 'xl',
                'some_other_scope:colors' => 'red|black',
            ])
            ->dispatch('filter-updated',
                field: 'origin',
                condition: 'query_scope',
                payload: ['china'],
                modifier: 'multiselect',
            )
            ->assertSet('params', [
                'query_scope' => 'multiselect|some_other_scope',
                'multiselect:sizes' => 'xl',
                'multiselect:origin' => 'china',
                'some_other_scope:colors' => 'red|black',
            ])
            ->dispatch('filter-updated',
                field: 'colors',
                condition: 'query_scope',
                payload: [],
                modifier: 'some_other_scope',
            )
            ->assertSet('params', [
                'query_scope' => 'multiselect',
                'multiselect:sizes' => 'xl',
                'multiselect:origin' => 'china',
            ])
            ->dispatch('filter-updated',
                field: 'origin',
                condition: 'query_scope',
                payload: [],
                modifier: 'multiselect',
            )
            ->assertSet('params', [
                'query_scope' => 'multiselect',
                'multiselect:sizes' => 'xl',
            ]);
    }

    /** @test */
    public function it_sets_collection_sort()
    {
        $params = [
            'from' => 'clothes',
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('collections', 'clothes')
            ->dispatch('sort-updated',
                sort: 'title:asc'
            )
            ->assertSet('params', [
                'sort' => 'title:asc',
            ])
            ->dispatch('sort-updated',
                sort: ''
            )->assertSet('params', []);
    }

    /** @test */
    public function it_clears_all_filters_for_a_field()
    {
        $params = [
            'from' => 'music',
            'title:is' => 'I Love Guitars',
            'item_options:is' => 'option1|option2',
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('params', [
                'title:is' => 'I Love Guitars',
                'item_options:is' => 'option1|option2',
            ])
            ->dispatch('clear-filter',
                field: 'item_options',
                condition: 'is',
                modifier: 'any',
            )
            ->assertSet('params', [
                'title:is' => 'I Love Guitars',
            ]);
    }

    /** @test */
    public function it_does_not_dispatch_the_params_updated_event_by_default()
    {
        $params = [
            'from' => 'clothes',
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->dispatch('filter-updated',
                field: 'colors',
                condition: 'taxonomy',
                payload: 'yellow',
                command: 'add',
                modifier: 'any',
            )
            ->assertNotDispatched('params-updated');
    }

    /** @test */
    public function it_dispatches_the_params_updated_event_if_enabled()
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', true);

        $params = [
            'from' => 'clothes',
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->dispatch('filter-updated',
                field: 'colors',
                condition: 'taxonomy',
                payload: ['yellow'],
                modifier: 'any',
            )
            ->assertDispatched('params-updated')
            ->dispatch('filter-updated',
                field: 'colors',
                condition: 'taxonomy',
                payload: [],
                modifier: 'any',
            )
            ->assertDispatched('params-updated')
            ->dispatch('sort-updated',
                sort: 'title:asc'
            )
            ->assertNotDispatched('params-updated');
    }
}
