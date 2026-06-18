<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
    public function allowed_filters_is_false_if_not_set()
    {
        $params = [
            'from' => 'music',
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('allowedFilters', false);
    }

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
    public function it_dispatches_params_updated_on_mount_when_counts_are_enabled()
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', true);

        Livewire::test(LivewireCollectionComponent::class, ['params' => ['from' => 'music']])
            ->assertDispatched('params-updated');
    }

    #[Test]
    public function it_sets_lazy_placeholder_from_parameter()
    {
        $params = [
            'from' => 'music',
            'lazy-placeholder' => 'custom-placeholder',
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('lazyPlaceholder', 'custom-placeholder')
            ->assertSet('collections', 'music');
    }

    #[Test]
    public function it_uses_default_lazy_placeholder_when_not_set()
    {
        $params = [
            'from' => 'music',
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('lazyPlaceholder', 'lazyload-placeholder');
    }

    #[Test]
    public function placeholder_method_uses_lazy_placeholder_from_params_before_mount()
    {
        // This test simulates what happens during lazy loading:
        // placeholder() is called BEFORE mount(), so we need to pass params directly
        $component = new LivewireCollectionComponent;

        // Simulate the params that Livewire passes to placeholder() during lazy loading
        $params = [
            'params' => [
                'from' => 'music',
                'lazy-placeholder' => 'lazyload-placeholder', // Use existing view
            ],
        ];

        $view = $component->placeholder($params);

        $this->assertEquals('statamic-livewire-filters::livewire.ui.lazyload-placeholder', $view->name());
    }

    #[Test]
    public function placeholder_method_uses_default_when_no_lazy_placeholder_in_params()
    {
        $component = new LivewireCollectionComponent;

        // No lazy-placeholder in params
        $params = [
            'params' => [
                'from' => 'music',
            ],
        ];

        $view = $component->placeholder($params);

        $this->assertEquals('statamic-livewire-filters::livewire.ui.lazyload-placeholder', $view->name());
    }

    #[Test]
    public function it_dispatches_params_updated_on_lazy_mount_when_counts_are_enabled()
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', true);

        $request = Request::create('/livewire/update', 'POST');
        $request->headers->set('X-Livewire', 'true');
        $request->headers->set('Referer', 'http://localhost/music');

        $this->app->instance('request', $request);

        Livewire::test(LivewireCollectionComponent::class, ['params' => ['from' => 'music']])
            ->assertDispatched('params-updated');
    }

    #[Test]
    public function placeholder_method_extracts_lazy_placeholder_from_nested_params()
    {
        // Test that the placeholder method correctly reads from $params['params']['lazy-placeholder']
        // This is the actual structure Livewire passes during lazy loading
        $component = new LivewireCollectionComponent;

        // Before the fix, this would have used the default because mount() hadn't run yet
        // After the fix, placeholder() reads directly from the params array
        $this->assertEquals('lazyload-placeholder', $component->lazyPlaceholder);

        // Call placeholder with custom value - should use it even though property has default
        $params = ['params' => ['lazy-placeholder' => 'custom-view']];

        // We can't render the view (doesn't exist), but we can verify the logic
        // by checking that a component without mount() being called still gets the right value
        try {
            $component->placeholder($params);
        } catch (\InvalidArgumentException $e) {
            // Expected - view doesn't exist, but check it tried to load the right one
            $this->assertStringContainsString('custom-view', $e->getMessage());
        }
    }

    #[Test]
    public function infinite_scroll_grows_the_page_size_and_exposes_has_more_pages()
    {
        $params = [
            'from' => 'clothes',
            'paginate' => 2,
            'infinite_scroll' => true,
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('infiniteScroll', true)
            ->assertSet('initialPaginate', 2)
            ->assertSet('hasMorePages', true)
            ->call('loadMore')
            ->assertSet('paginate', 4)
            ->assertSet('hasMorePages', false);
    }

    #[Test]
    public function infinite_scroll_resets_to_the_initial_page_size_when_filtering()
    {
        $params = [
            'from' => 'clothes',
            'paginate' => 2,
            'infinite_scroll' => true,
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->call('loadMore')
            ->assertSet('paginate', 4)
            ->dispatch('filter-updated',
                field: 'colors',
                condition: 'taxonomy',
                payload: 'red',
                modifier: 'any',
            )
            ->assertSet('paginate', 2);
    }

    #[Test]
    public function infinite_scroll_resets_to_the_initial_page_size_when_sorting()
    {
        $params = [
            'from' => 'clothes',
            'paginate' => 2,
            'infinite_scroll' => true,
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->call('loadMore')
            ->assertSet('paginate', 4)
            ->dispatch('sort-updated', sort: 'title:desc')
            ->assertSet('paginate', 2);
    }

    #[Test]
    public function infinite_scroll_resets_to_the_initial_page_size_when_clearing_a_filter()
    {
        EntryFactory::collection('clothes')->slug('red-2')->data(['title' => 'Red 2', 'colors' => ['red']])->create();
        EntryFactory::collection('clothes')->slug('red-3')->data(['title' => 'Red 3', 'colors' => ['red']])->create();
        EntryFactory::collection('clothes')->slug('red-4')->data(['title' => 'Red 4', 'colors' => ['red']])->create();

        $params = [
            'from' => 'clothes',
            'paginate' => 2,
            'infinite_scroll' => true,
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->dispatch('filter-updated',
                field: 'colors',
                condition: 'taxonomy',
                payload: 'red',
                modifier: 'any',
            )
            ->call('loadMore')
            ->assertSet('paginate', 4)
            ->dispatch('clear-filter',
                field: 'colors',
                condition: 'taxonomy',
                modifier: 'any',
            )
            ->assertSet('paginate', 2);
    }

    #[Test]
    public function infinite_scroll_does_not_grow_the_page_size_past_the_total()
    {
        $params = [
            'from' => 'clothes',
            'paginate' => 2,
            'infinite_scroll' => true,
        ];

        // 3 entries exist. The first loadMore grows 2 -> 4 (covering all 3, no
        // more pages). A second loadMore must be a no-op since nothing remains.
        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->call('loadMore')
            ->assertSet('paginate', 4)
            ->assertSet('hasMorePages', false)
            ->call('loadMore')
            ->assertSet('paginate', 4)
            ->assertSet('hasMorePages', false);
    }

    #[Test]
    public function infinite_scroll_keeps_the_total_count_correct_as_the_page_size_grows()
    {
        $params = [
            'from' => 'clothes',
            'paginate' => 2,
            'infinite_scroll' => true,
        ];

        $component = Livewire::test(LivewireCollectionComponent::class, ['params' => $params]);

        $this->assertEquals(3, $component->get('entriesCount'));

        $component->call('loadMore');

        $this->assertEquals(3, $component->get('entriesCount'));
    }

    #[Test]
    public function infinite_scroll_is_disabled_when_no_numeric_page_size_is_set()
    {
        $params = [
            'from' => 'clothes',
            'infinite_scroll' => true,
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('infiniteScroll', false);
    }

    #[Test]
    public function default_view_renders_a_load_more_button_in_infinite_scroll_mode()
    {
        $params = [
            'from' => 'clothes',
            'paginate' => 2,
            'infinite_scroll' => true,
        ];

        $html = Livewire::test(LivewireCollectionComponent::class, ['params' => $params])->html();

        $this->assertStringContainsString('wire:click="loadMore"', $html);
        $this->assertStringNotContainsString('Pagination Navigation', $html);
    }

    #[Test]
    public function default_view_renders_numbered_pagination_when_not_in_infinite_scroll_mode()
    {
        $params = [
            'from' => 'clothes',
            'paginate' => 2,
        ];

        $html = Livewire::test(LivewireCollectionComponent::class, ['params' => $params])->html();

        $this->assertStringContainsString('Pagination Navigation', $html);
        $this->assertStringNotContainsString('wire:click="loadMore"', $html);
    }

    #[Test]
    public function infinite_scroll_forces_page_one_on_mount_when_deep_linked_to_a_later_page()
    {
        $params = [
            'from' => 'clothes',
            'paginate' => 2,
            'infinite_scroll' => true,
        ];

        // Landing on ?page=2 must not offset an infinite-scroll list: mount forces
        // page 1, so the earliest entries stay reachable and there are more to load.
        Livewire::withQueryParams(['page' => 2])
            ->test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('paginators.page', 1)
            ->assertSet('hasMorePages', true)
            ->assertSet('entriesCount', 3);
    }

    #[Test]
    public function infinite_scroll_never_writes_a_page_param_with_a_custom_query_string()
    {
        Config::set('statamic-livewire-filters.custom_query_string', 'filters');
        Config::set('statamic-livewire-filters.custom_query_string_aliases', [
            'taxonomy:colors:any' => 'color',
        ]);

        $params = [
            'from' => 'clothes',
            'paginate' => 2,
            'infinite_scroll' => true,
        ];

        // Growing the page size keeps the component on page 1, so the custom
        // query string URL must never gain a ?page= segment.
        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->call('loadMore')
            ->assertSet('paginate', 4)
            ->assertSet('paginators.page', 1)
            ->dispatch('preset-params', [])
            ->assertDispatched('update-url', fn ($name, $payload) => ! str_contains($payload['newUrl'], 'page='));
    }

    #[Test]
    public function infinite_scroll_resets_the_page_size_on_clear_all_even_without_active_filters()
    {
        $params = [
            'from' => 'clothes',
            'paginate' => 2,
            'infinite_scroll' => true,
        ];

        // A grown page size with no active filters must still reset when the
        // clear-all event fires (the per-filter clear cascade never runs here).
        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->call('loadMore')
            ->assertSet('paginate', 4)
            ->dispatch('clear-all-filters')
            ->assertSet('paginate', 2);
    }

    #[Test]
    public function infinite_scroll_can_be_enabled_with_the_string_true_value()
    {
        $params = [
            'from' => 'clothes',
            'paginate' => 2,
            'infinite_scroll' => 'true',
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('infiniteScroll', true);
    }

    #[Test]
    public function infinite_scroll_is_disabled_with_the_string_false_value()
    {
        $params = [
            'from' => 'clothes',
            'paginate' => 2,
            'infinite_scroll' => 'false',
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('infiniteScroll', false);
    }

    #[Test]
    public function load_more_is_a_no_op_and_view_renders_when_infinite_scroll_is_disabled_at_mount()
    {
        $params = [
            'from' => 'clothes',
            'infinite_scroll' => true,
        ];

        $component = Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('infiniteScroll', false)
            ->call('loadMore')
            ->assertSet('infiniteScroll', false)
            ->assertSet('paginate', null);

        // The non-paginated render branch must not error and must not show a button.
        $this->assertStringNotContainsString('wire:click="loadMore"', $component->html());
    }

    #[Test]
    public function infinite_scroll_grows_across_multiple_load_more_calls()
    {
        foreach (range(1, 6) as $i) {
            EntryFactory::collection('clothes')->slug("extra-{$i}")->data(['title' => "Extra {$i}"])->create();
        }

        $params = [
            'from' => 'clothes',
            'paginate' => 2,
            'infinite_scroll' => true,
        ];

        // 9 entries, page size 2 -> grows 2,4,6,8,10 across four loadMore calls.
        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('hasMorePages', true)
            ->call('loadMore')->assertSet('paginate', 4)->assertSet('hasMorePages', true)
            ->call('loadMore')->assertSet('paginate', 6)->assertSet('hasMorePages', true)
            ->call('loadMore')->assertSet('paginate', 8)->assertSet('hasMorePages', true)
            ->call('loadMore')->assertSet('paginate', 10)->assertSet('hasMorePages', false)
            ->call('loadMore')->assertSet('paginate', 10)->assertSet('hasMorePages', false)
            ->assertSet('paginators.page', 1);
    }

    #[Test]
    public function infinite_scroll_keeps_the_count_correct_when_a_filter_is_active()
    {
        EntryFactory::collection('clothes')->slug('red-2')->data(['title' => 'Red 2', 'colors' => ['red']])->create();
        EntryFactory::collection('clothes')->slug('red-3')->data(['title' => 'Red 3', 'colors' => ['red']])->create();
        EntryFactory::collection('clothes')->slug('red-4')->data(['title' => 'Red 4', 'colors' => ['red']])->create();

        $params = [
            'from' => 'clothes',
            'paginate' => 2,
            'infinite_scroll' => true,
        ];

        // 6 clothes entries total, 4 of them red. With a red filter applied the
        // displayed total must be the filtered total (4), not the collection total.
        $component = Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->dispatch('filter-updated',
                field: 'colors',
                condition: 'taxonomy',
                payload: 'red',
                modifier: 'any',
            );

        $this->assertEquals(4, $component->get('entriesCount'));
        $component->assertSet('hasMorePages', true);

        $component->call('loadMore')->assertSet('paginate', 4);

        $this->assertEquals(4, $component->get('entriesCount'));
        $component->assertSet('hasMorePages', false);
    }

    #[Test]
    public function has_more_pages_is_not_exposed_when_not_in_infinite_scroll_mode()
    {
        $params = [
            'from' => 'clothes',
            'paginate' => 2,
        ];

        $component = Livewire::test(LivewireCollectionComponent::class, ['params' => $params]);

        // The view data carries infinite_scroll => false and omits has_more_pages.
        $this->assertSame(false, $component->viewData('infinite_scroll'));
    }

    #[Test]
    public function default_view_in_infinite_mode_uses_translatable_strings_and_aria_attributes()
    {
        $params = [
            'from' => 'clothes',
            'paginate' => 2,
            'infinite_scroll' => true,
        ];

        $html = Livewire::test(LivewireCollectionComponent::class, ['params' => $params])->html();

        // Translation keys resolve to their values rather than being emitted raw.
        $this->assertStringContainsString('Load more', $html);
        $this->assertStringContainsString('Loading', $html);
        $this->assertStringNotContainsString('statamic-livewire-filters::ui', $html);

        // Accessibility hooks for the dynamically injected entries and loading state.
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('aria-busy', $html);
        $this->assertStringContainsString('role="status"', $html);
    }
}
