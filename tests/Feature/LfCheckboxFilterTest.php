<?php

namespace Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
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
                                'type' => 'checkbox',
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

        $this->makeEntry($this->collection, 'a')->set('title', 'I Love Guitars')->save();
        $this->makeEntry($this->collection, 'b')->set('title', 'I Love Drums')->save();
        $this->makeEntry($this->collection, 'c')->set('title', 'I Hate Flutes')->save();

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
        Livewire::test(LfCheckboxFilter::class, ['field' => 'item_options', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is'])
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
        Livewire::test(LfCheckboxFilter::class, ['field' => 'item_options', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is'])
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
    public function it_shows_taxonomy_terms_and_submits_the_right_events()
    {
        Livewire::test(LfCheckboxFilter::class, ['field' => 'colors', 'collection' => 'clothes', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy'])
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

    protected function makeEntry($collection, $slug)
    {
        return EntryFactory::id($slug)->collection($collection)->slug($slug)->make();
    }
}