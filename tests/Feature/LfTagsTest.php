<?php

namespace Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Livewire\Livewire;
use Reach\StatamicLivewireFilters\Http\Livewire\LfTags;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class LfTagsTest extends TestCase
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
    public function it_renders_the_component_and_gets_the_option_labels_for_a_checkboxes_field()
    {
        $component = Livewire::test(LfTags::class, ['fields' => 'item_options', 'blueprint' => 'pages.pages']);

        $this->assertEquals([
            'option1' => 'Option 1',
            'option2' => 'Option 2',
            'option3' => 'Option 3',
        ], $component->statamicFields->get('item_options')['options']);
    }

    /** @test */
    public function it_renders_the_component_and_gets_the_term_titles_for_a_taxonomy_field()
    {
        $component = Livewire::test(LfTags::class, ['fields' => 'colors', 'blueprint' => 'clothes.clothes']);

        $this->assertEquals([
            'red' => 'Red',
            'black' => 'Black',
            'yellow' => 'Yellow',
        ], $component->statamicFields->get('colors')['options']);
    }

    /** @test */
    public function it_throws_a_field_not_found_exception_if_the_field_doesnt_exist()
    {
        $this->expectExceptionMessage('Field [not-a-field] not found');

        $component = Livewire::test(LfTags::class, ['fields' => 'item_options|not-a-field', 'blueprint' => 'pages.pages']);

        $this->assertNotEmpty($component->statamicFields);
    }

    /** @test */
    public function it_throws_a_blueprint_not_found_exception_if_the_blueprint_doesnt_exist()
    {
        $this->expectExceptionMessage('Blueprint [not-a-blueprint] not found');

        $component = Livewire::test(LfTags::class, ['fields' => 'item_options', 'blueprint' => 'pages.not-a-blueprint']);

        $this->assertNotEmpty($component->statamicFields);
    }

    /** @test */
    public function it_renders_the_tag_when_a_filter_is_updated()
    {
        Livewire::test(LfTags::class, ['fields' => 'item_options', 'blueprint' => 'pages.pages'])
            ->dispatch('tags-updated', ['item_options:is' => 'option1'])
            ->assertSee('Checkbox: Option 1');
    }

    /** @test */
    public function it_does_not_render_the_tag_when_a_filter_is_not_in_the_fields_array()
    {
        Livewire::test(LfTags::class, ['fields' => 'some_other_option', 'blueprint' => 'pages.pages'])
            ->dispatch('tags-updated', ['item_options:is' => 'option1'])
            ->assertDontSee('Checkbox: Option 1');
    }

    /** @test */
    public function it_dispatches_the_clear_option_event()
    {
        Livewire::test(LfTags::class, ['fields' => 'item_options', 'blueprint' => 'pages.pages'])
            ->dispatch('tags-updated', ['item_options:is' => 'option1'])
            ->call('removeOption', 'item_options', 'option1')
            ->assertDispatched('clear-option', [
                'field' => 'item_options',
                'value' => 'option1',
                'fieldLabel' => 'Checkbox',
                'optionLabel' => 'Option 1',
                'condition' => 'is',
            ]);
    }

    protected function makeEntry($collection, $slug)
    {
        return EntryFactory::id($slug)->collection($collection)->slug($slug)->make();
    }
}
