<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Livewire\Livewire;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection as LivewireCollectionComponent;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class LivewireCollectionComponentTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    private $music;

    public function setUp(): void
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
                command: 'add',
                modifier: 'any',
            )
            ->assertSet('params', [
                'title:is' => 'I Love Guitars',
                'item_options:is' => 'option1',
            ])
            ->dispatch('filter-updated',
                field: 'title',
                condition: 'is',
                payload: 'Test',
                command: 'replace',
                modifier: 'any',
            )
            ->assertSet('params', [
                'title:is' => 'Test',
                'item_options:is' => 'option1',
            ])
            ->dispatch('filter-updated',
                field: 'item_options',
                condition: 'is',
                payload: 'option2',
                command: 'add',
                modifier: 'any',
            )
            ->assertSet('params', [
                'title:is' => 'Test',
                'item_options:is' => 'option1|option2',
            ])
            ->dispatch('filter-updated',
                field: 'item_options',
                condition: 'is',
                payload: 'option1',
                command: 'remove',
                modifier: 'any',
            )
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
                payload: 'red',
                command: 'add',
                modifier: 'any',
            )
            ->assertSet('params', [
                'taxonomy:colors:any' => 'red',
            ])
            ->dispatch('filter-updated',
                field: 'colors',
                condition: 'taxonomy',
                payload: 'yellow',
                command: 'add',
                modifier: 'any',
            )
            ->assertSet('params', [
                'taxonomy:colors:any' => 'red|yellow',
            ])
            ->dispatch('filter-updated',
                field: 'colors',
                condition: 'taxonomy',
                payload: 'red',
                command: 'remove',
                modifier: 'any',
            )
            ->assertSet('params', [
                'taxonomy:colors:any' => 'yellow',
            ]);
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
                payload: 'xl',
                command: 'add',
                modifier: 'multiselect',
            )
            ->assertSet('params', [
                'query_scope' => 'multiselect',
                'multiselect:sizes' => 'xl',
            ])
            ->dispatch('filter-updated',
                field: 'sizes',
                condition: 'query_scope',
                payload: 'l',
                command: 'add',
                modifier: 'multiselect',
            )
            ->assertSet('params', [
                'query_scope' => 'multiselect',
                'multiselect:sizes' => 'xl|l',
            ])
            ->dispatch('filter-updated',
                field: 'sizes',
                condition: 'query_scope',
                payload: 'l',
                command: 'remove',
                modifier: 'multiselect',
            )
            ->assertSet('params', [
                'query_scope' => 'multiselect',
                'multiselect:sizes' => 'xl',
            ])
            ->dispatch('filter-updated',
                field: 'sizes',
                condition: 'query_scope',
                payload: 'xl',
                command: 'remove',
                modifier: 'multiselect',
            )
            ->assertSet('params', [])
            ->dispatch('filter-updated',
                field: 'sizes',
                condition: 'query_scope',
                payload: 'xl',
                command: 'replace',
                modifier: 'multiselect',
            )
            ->assertSet('params', [
                'query_scope' => 'multiselect',
                'multiselect:sizes' => 'xl',
            ])
            ->dispatch('filter-updated',
                field: 'sizes',
                condition: 'query_scope',
                payload: 'l',
                command: 'replace',
                modifier: 'multiselect',
            )
            ->assertSet('params', [
                'query_scope' => 'multiselect',
                'multiselect:sizes' => 'l',
            ])
            ->dispatch('filter-updated',
                field: 'sizes',
                condition: 'query_scope',
                payload: '',
                command: 'clear',
                modifier: 'multiselect',
            )
            ->assertSet('params', []);
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
            ->dispatch('filter-updated',
                field: 'item_options',
                condition: 'is',
                payload: false,
                command: 'clear',
                modifier: 'any',
            )
            ->assertSet('params', [
                'title:is' => 'I Love Guitars',
            ]);
    }
}
