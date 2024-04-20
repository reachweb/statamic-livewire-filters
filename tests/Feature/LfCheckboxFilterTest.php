<?php

namespace Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Reach\StatamicLivewireFilters\Http\Livewire\LfCheckboxFilter;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

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
                                    'option1' => 'Option 1',
                                    'option2' => 'Option 2',
                                    'option3' => 'Option 3',
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
                payload: 'option1',
                command: 'add',
            )
            ->set('selected', ['option1', 'option2'])
            ->assertSet('selected', ['option1', 'option2'])
            ->assertDispatched('filter-updated',
                field: 'item_options',
                condition: 'is',
                payload: 'option2',
                command: 'add',
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
                payload: 'not-an-option',
                command: 'add',
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
                payload: 'red',
                command: 'add',
            )
            ->set('selected', ['yellow'])
            ->assertSet('selected', ['yellow'])
            ->assertDispatched('filter-updated',
                field: 'colors',
                condition: 'taxonomy',
                payload: 'red',
                command: 'remove',
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
            ->assertSeeHtml('<span class="text-gray-500 ml-1">(2)</span>');
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

    protected function makeEntry($collection, $slug)
    {
        return EntryFactory::id($slug)->collection($collection)->slug($slug)->make();
    }
}
