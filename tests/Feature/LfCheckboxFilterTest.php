<?php

namespace Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Reach\StatamicLivewireFilters\Http\Livewire\LfCheckboxFilter;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Entries\Entry;
use Statamic\Facades;
use Statamic\Facades\Site;

class LfCheckboxFilterTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    protected $collection;

    protected $blueprint;

    public function setUp(): void
    {
        parent::setUp();

        $this->collection = Facades\Collection::make('pages')->save();
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

                            'handle' => 'item_options',
                            'field' => [
                                'type' => 'checkboxes',
                                'display' => 'Checkbox',
                                'listable' => 'hidden',
                                'options' => [
                                    [
                                        'key' => 'option1',
                                        'value' => 'Option 1',
                                    ],
                                    [
                                        'key' => 'option2',
                                        'value' => 'Option 2',
                                    ],
                                    [
                                        'key' => 'option3',
                                        'value' => 'Option 3',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->blueprint->setHandle('pages')->setNamespace('collections.'.$this->collection->handle())->save();

        $this->makeEntry($this->collection, 'a')->set('title', 'I Love Guitars')->set('item_options', 'option1')->save();
        $this->makeEntry($this->collection, 'b')->set('title', 'I Love Drums')->set('item_options', 'option1')->save();
        $this->makeEntry($this->collection, 'c')->set('title', 'I Hate Flutes')->set('item_options', 'option2')->save();

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

        // Setup for Entries field test
        Facades\Collection::make('instruments')->save();

        $instrumentsBlueprint = Facades\Blueprint::make()->setContents([
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
                    ],
                ],
            ],
        ]);
        $instrumentsBlueprint->setHandle('instruments')->setNamespace('collections.instruments')->save();

        $this->makeEntry(Facades\Collection::findByHandle('instruments'), 'guitar')->set('title', 'Guitar')->save();
        $this->makeEntry(Facades\Collection::findByHandle('instruments'), 'drums')->set('title', 'Drums')->save();
        $this->makeEntry(Facades\Collection::findByHandle('instruments'), 'piano')->set('title', 'Piano')->save();

        // Add posts blueprint with entries field
        Facades\Collection::make('posts')->save();
        $postsBlueprint = Facades\Blueprint::make()->setContents([
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
                            'handle' => 'related_instruments',
                            'field' => [
                                'type' => 'entries',
                                'display' => 'Related Instruments',
                                'collections' => [
                                    'instruments',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $postsBlueprint->setHandle('posts')->setNamespace('collections.posts')->save();
    }

    /** @test */
    public function it_renders_the_component_and_gets_the_options_for_a_checkbox()
    {
        Livewire::test(LfCheckboxFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->assertSee('Option 1')
            ->assertSee('Option 2')
            ->assertSee('Option 3');
    }

    /** @test */
    public function it_renders_the_component_with_the_combobox_and_gets_the_options_for_a_checkbox()
    {
        Livewire::test(LfCheckboxFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is', 'view' => 'lf-checkbox-advanced'])
            ->assertSee('Option 1')
            ->assertSee('Option 2')
            ->assertSee('Option 3')
            ->assertSee('combobox');
    }

    /** @test */
    public function it_throws_a_field_not_found_exception_if_the_field_doesnt_exist()
    {
        $this->expectExceptionMessage('Field [not-a-field] not found');

        Livewire::test(LfCheckboxFilter::class, ['field' => 'not-a-field', 'blueprint' => 'pages.pages', 'condition' => 'is']);
    }

    /** @test */
    public function it_throws_a_blueprint_not_found_exception_if_the_blueprint_doesnt_exist()
    {
        $this->expectExceptionMessage('Blueprint [not-a-blueprint] not found');

        Livewire::test(LfCheckboxFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.not-a-blueprint', 'condition' => 'is']);
    }

    /** @test */
    public function it_changes_the_value_of_selected_property_when_an_option_is_set_and_sends_an_event()
    {
        Livewire::test(LfCheckboxFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->assertSet('selected', [])
            ->set('selected', ['option1'])
            ->assertSet('selected', ['option1'])
            ->assertDispatched('filter-updated',
                field: 'item_options',
                condition: 'is',
                payload: ['option1'],
            )
            ->set('selected', ['option1', 'option2'])
            ->assertSet('selected', ['option1', 'option2'])
            ->assertDispatched('filter-updated',
                field: 'item_options',
                condition: 'is',
                payload: ['option1', 'option2'],
            );
    }

    /** @test */
    public function it_does_not_accept_a_value_not_in_the_options_array()
    {
        Livewire::test(LfCheckboxFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->assertSet('selected', [])
            ->set('selected', ['not-an-option'])
            ->assertHasErrors('selected')
            ->assertNotDispatched('filter-updated');
    }

    /** @test */
    public function it_can_turn_off_validation_of_values_in_the_config()
    {
        Config::set('statamic-livewire-filters.validate_filter_values', false);

        Livewire::test(LfCheckboxFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->assertSet('selected', [])
            ->set('selected', ['not-an-option'])
            ->assertSet('selected', ['not-an-option'])
            ->assertDispatched('filter-updated',
                field: 'item_options',
                condition: 'is',
                payload: ['not-an-option'],
            );
    }

    /** @test */
    public function it_shows_taxonomy_terms_and_submits_the_right_events()
    {
        Livewire::test(LfCheckboxFilter::class, ['field' => 'colors', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy'])
            ->assertSee('Red')
            ->assertSee('Black')
            ->assertSee('Yellow')
            ->set('selected', ['red'])
            ->assertSet('selected', ['red'])
            ->assertDispatched('filter-updated',
                field: 'colors',
                condition: 'taxonomy',
                payload: ['red'],
            )
            ->set('selected', ['yellow'])
            ->assertSet('selected', ['yellow'])
            ->assertDispatched('filter-updated',
                field: 'colors',
                condition: 'taxonomy',
                payload: ['yellow'],
            );
    }

    /** @test */
    public function it_does_not_accept_an_invalid_taxonomy_value()
    {
        Livewire::test(LfCheckboxFilter::class, ['field' => 'colors', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy'])
            ->set('selected', ['purple'])
            ->assertHasErrors('selected');
    }

    /** @test */
    public function it_loads_a_param_that_is_preset()
    {
        Livewire::test(LfCheckboxFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->assertSet('selected', [])
            ->dispatch('preset-params', ['item_options:is' => 'option1', 'another_field:is' => 'value'])
            ->assertSet('selected', ['option1']);
    }

    /** @test */
    public function it_loads_a_param_that_is_preset_for_a_taxonomy_with_modifier()
    {
        Livewire::test(LfCheckboxFilter::class, ['field' => 'colors',  'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy', 'modifier' => 'any'])
            ->assertSet('selected', [])
            ->dispatch('preset-params', ['taxonomy:colors:any' => 'red', 'another_field:is' => 'value'])
            ->assertSet('selected', ['red']);
    }

    /** @test */
    public function it_loads_a_param_that_is_preset_for_a_query_scope()
    {
        Livewire::test(LfCheckboxFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'query_scope', 'modifier' => 'multiselect'])
            ->assertSet('selected', [])
            ->dispatch('preset-params', ['multiselect:item_options' => 'option1', 'query_scope' => 'multiselect'])
            ->assertSet('selected', ['option1']);
    }

    /** @test */
    public function it_calculates_the_count_for_each_entry()
    {
        Livewire::test(LfCheckboxFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->assertSet('selected', [])
            ->dispatch('params-updated', ['item_options:is' => 'option1'])
            ->assertViewHas('statamic_field', function ($statamic_field) {
                return $statamic_field['counts'] === ['option1' => 2, 'option2' => 1, 'option3' => 0];
            })
            ->assertSeeHtml('<span class="text-lf-muted ml-1">(2)</span>');
    }

    /** @test */
    public function it_clears_the_value_when_clear_is_called()
    {
        Livewire::test(LfCheckboxFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->set('selected', ['option1', 'option2'])
            ->call('clear')
            ->assertSet('selected', []);
    }

    /** @test */
    public function it_clears_the_value_when_clear_option_is_fired()
    {
        Livewire::test(LfCheckboxFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->set('selected', ['option1', 'option2'])
            ->dispatch('clear-option', [
                'field' => 'item_options',
                'value' => 'option2',
            ])
            ->assertSet('selected', ['option1'])
            ->dispatch('clear-option', [
                'field' => 'item_options',
                'value' => 'option1',
            ])
            ->assertSet('selected', []);
    }

    /** @test */
    public function it_clears_the_value_when_clear_all_filters_event_is_fired()
    {
        Livewire::test(LfCheckboxFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->set('selected', ['option1', 'option2'])
            ->dispatch('clear-all-filters')
            ->assertSet('selected', [])
            ->assertDispatched('clear-filter',
                field: 'item_options',
                condition: 'is'
            );
    }

    /** @test */
    public function it_can_reorder_term_filter_values_by_slug()
    {
        Livewire::test(LfCheckboxFilter::class, ['field' => 'colors', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy', 'sort' => 'slug:asc'])
            ->assertViewHas('statamic_field', function ($statamic_field) {
                return array_keys($statamic_field['options']) === ['black', 'red', 'yellow'];
            });
    }

    /** @test */
    public function it_can_reorder_term_filter_values_by_title()
    {
        Livewire::test(LfCheckboxFilter::class, ['field' => 'colors', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy', 'sort' => 'title:desc'])
            ->assertViewHas('statamic_field', function ($statamic_field) {
                return array_keys($statamic_field['options']) === ['yellow', 'red', 'black'];
            });
    }

    /** @test */
    public function it_can_reorder_checkboxes_filter_values_by_key()
    {
        Livewire::test(LfCheckboxFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is', 'sort' => 'key:desc'])
            ->assertViewHas('statamic_field', function ($statamic_field) {
                return array_keys($statamic_field['options']) === ['option3', 'option2', 'option1'];
            });
    }

    /** @test */
    public function it_can_reorder_checkboxes_filter_values_by_value()
    {
        Livewire::test(LfCheckboxFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is', 'sort' => 'label:desc'])
            ->assertViewHas('statamic_field', function ($statamic_field) {
                return array_keys($statamic_field['options']) === ['option3', 'option2', 'option1'];
            });
    }

    /** @test */
    public function it_throws_an_exception_for_wrong_sort_parameter()
    {
        $this->expectExceptionMessage('Cannot sort field [item_options] by [slug]');

        Livewire::test(LfCheckboxFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is', 'sort' => 'slug:desc']);
    }

    /** @test */
    public function it_throws_an_exception_for_wrong_sort_parameter_terms()
    {
        $this->expectExceptionMessage('Cannot sort field [colors] by [key]');
        Livewire::test(LfCheckboxFilter::class, ['field' => 'colors', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy', 'sort' => 'key:desc']);
    }

    /** @test */
    public function it_can_reorder_term_filter_values_by_custom_field()
    {
        Facades\Blueprint::make()->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        [
                            'handle' => 'order',
                            'field' => [
                                'type' => 'text',
                                'display' => 'Order',
                            ],
                        ],
                    ],
                ],
            ],
        ])->setHandle('brand')->setNamespace('taxonomies.brand')->save();

        Facades\Taxonomy::make('brand')->save();
        Facades\Term::make()->taxonomy('brand')->inDefaultLocale()->slug('nike')->data(['title' => 'Nike', 'order' => '3'])->save();
        Facades\Term::make()->taxonomy('brand')->inDefaultLocale()->slug('adidas')->data(['title' => 'Adidas', 'order' => '1'])->save();
        Facades\Term::make()->taxonomy('brand')->inDefaultLocale()->slug('reebok')->data(['title' => 'Reebok', 'order' => '2'])->save();

        // add to clothers blueprint
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
                        [

                            'handle' => 'brand',
                            'field' => [
                                'type' => 'terms',
                                'taxonomies' => [
                                    'brand',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $clothesBlueprint->setHandle('clothes')->setNamespace('collections.clothes')->save();

        // By default they are sorted by creation date
        Livewire::test(LfCheckboxFilter::class, ['field' => 'brand', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy'])
            ->assertViewHas('statamic_field', function ($statamic_field) {
                return array_keys($statamic_field['options']) === ['nike', 'adidas', 'reebok'];
            });

        Livewire::test(LfCheckboxFilter::class, ['field' => 'brand', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy', 'sort' => 'order:asc'])
            ->assertViewHas('statamic_field', function ($statamic_field) {
                return array_keys($statamic_field['options']) === ['adidas', 'reebok', 'nike'];
            });

        $this->expectExceptionMessage('Cannot find field [something] in the taxonomy [brand]');
        Livewire::test(LfCheckboxFilter::class, ['field' => 'brand', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy', 'sort' => 'something:asc']);
    }

    /** @test */
    public function it_displays_taxonomy_terms_in_the_current_language()
    {
        // Setup Statamic for multi-language support
        Site::setSites([
            'en' => [
                'name' => 'English',
                'url' => '/',
                'locale' => 'en_US',
            ],
            'es' => [
                'name' => 'Spanish',
                'url' => '/es',
                'locale' => 'es_ES',
            ],
        ]);

        // Update the existing colors taxonomy to support multiple sites
        Facades\Taxonomy::find('colors')->sites(['default', 'es'])->save();

        // Add translations to existing terms
        $red = Facades\Term::find('colors::red');
        $red->in('es')->slug('rojo')->data(['title' => 'Rojo'])->save();

        $black = Facades\Term::find('colors::black');
        $black->in('es')->slug('negro')->data(['title' => 'Negro'])->save();

        $yellow = Facades\Term::find('colors::yellow');
        $yellow->in('es')->slug('amarillo')->data(['title' => 'Amarillo'])->save();

        // Update collection to support multiple sites
        Facades\Collection::find('clothes')->sites(['default', 'es'])->save();

        // Test with default site
        Site::setCurrent('en');
        Livewire::test(LfCheckboxFilter::class, ['field' => 'colors', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy'])
            ->assertSee('Red')
            ->assertSee('Black')
            ->assertSee('Yellow')
            ->assertDontSee('Rojo')
            ->assertDontSee('Negro')
            ->assertDontSee('Amarillo');

        // Test with Spanish site
        Site::setCurrent('es');
        Livewire::test(LfCheckboxFilter::class, ['field' => 'colors', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy'])
            ->assertSee('Rojo')
            ->assertSee('Negro')
            ->assertSee('Amarillo')
            ->assertDontSee('value="amarillo"')
            ->assertDontSee('Red')
            ->assertDontSee('Black')
            ->assertDontSee('Yellow');
    }

    /** @test */
    public function it_renders_the_component_and_gets_the_options_for_entries_field()
    {
        Livewire::test(LfCheckboxFilter::class, ['field' => 'related_instruments', 'blueprint' => 'posts.posts', 'condition' => 'is'])
            ->assertSee('Guitar')
            ->assertSee('Drums')
            ->assertSee('Piano');
    }

    /** @test */
    public function it_changes_the_value_of_selected_property_when_an_entry_is_selected_and_sends_an_event()
    {
        $guitarId = Entry::query()
            ->where('collection', 'instruments')
            ->where('slug', 'guitar')
            ->first()
            ->id();

        $drumsId = Entry::query()
            ->where('collection', 'instruments')
            ->where('slug', 'drums')
            ->first()
            ->id();

        Livewire::test(LfCheckboxFilter::class, ['field' => 'related_instruments', 'blueprint' => 'posts.posts', 'condition' => 'is'])
            ->assertSet('selected', [])
            ->set('selected', [$guitarId])
            ->assertSet('selected', [$guitarId])
            ->assertDispatched('filter-updated',
                field: 'related_instruments',
                condition: 'is',
                payload: [$guitarId],
            )
            ->set('selected', [$guitarId, $drumsId])
            ->assertSet('selected', [$guitarId, $drumsId])
            ->assertDispatched('filter-updated',
                field: 'related_instruments',
                condition: 'is',
                payload: [$guitarId, $drumsId],
            );
    }

    /** @test */
    public function it_does_not_accept_an_invalid_entry_value()
    {
        Config::set('statamic-livewire-filters.validate_filter_values', true);

        Livewire::test(LfCheckboxFilter::class, ['field' => 'related_instruments', 'blueprint' => 'posts.posts', 'condition' => 'is'])
            ->assertSet('selected', [])
            ->set('selected', ['not-a-valid-entry-id'])
            ->assertHasErrors('selected')
            ->assertNotDispatched('filter-updated');
    }

    /** @test */
    public function it_loads_preset_params_for_entries_field()
    {
        $guitarId = Entry::query()
            ->where('collection', 'instruments')
            ->where('slug', 'guitar')
            ->first()
            ->id();

        Livewire::test(LfCheckboxFilter::class, ['field' => 'related_instruments', 'blueprint' => 'posts.posts', 'condition' => 'is'])
            ->assertSet('selected', [])
            ->dispatch('preset-params', ['related_instruments:is' => $guitarId])
            ->assertSet('selected', [$guitarId]);
    }

    /** @test */
    public function it_uses_custom_options_when_provided()
    {
        $customOptions = [
            'custom1' => 'Custom 1',
            'custom2' => 'Custom 2',
        ];

        Livewire::test(LfCheckboxFilter::class, [
            'field' => 'item_options',
            'blueprint' => 'pages.pages',
            'condition' => 'is',
            'options' => $customOptions,
        ])
            ->assertSee('Custom 1')
            ->assertSee('Custom 2')
            ->assertDontSee('Option 1')
            ->assertDontSee('Option 2')
            ->assertDontSee('Option 3');
    }

    /** @test */
    public function it_ignores_non_array_options()
    {
        Livewire::test(LfCheckboxFilter::class, [
            'field' => 'item_options',
            'blueprint' => 'pages.pages',
            'condition' => 'is',
            'options' => 'not-an-array',
        ])
            ->assertSee('Option 1')
            ->assertSee('Option 2')
            ->assertSee('Option 3');
    }

    protected function makeEntry($collection, $slug)
    {
        return EntryFactory::id($slug)->collection($collection)->slug($slug)->make();
    }
}
