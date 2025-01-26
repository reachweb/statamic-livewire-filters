<?php

namespace Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Livewire\Livewire;
use Reach\StatamicLivewireFilters\Http\Livewire\LfTextFilter;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class LfTextFilterTest extends TestCase
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
                                'type' => 'text',
                                'display' => 'Radio',
                                'listable' => 'hidden',
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
    }

    /** @test */
    public function it_renders_the_component_and_gets_an_input_box()
    {
        Livewire::test(LfTextFilter::class, ['field' => 'item_options', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->assertSee('input');
    }

    /** @test */
    public function it_throws_a_field_not_found_exception_if_the_field_doesnt_exist()
    {
        $this->expectExceptionMessage('Field [not-a-field] not found');

        Livewire::test(LfTextFilter::class, ['field' => 'not-a-field', 'blueprint' => 'pages.pages', 'condition' => 'is']);
    }

    /** @test */
    public function it_throws_a_blueprint_not_found_exception_if_the_blueprint_doesnt_exist()
    {
        $this->expectExceptionMessage('Blueprint [not-a-blueprint] not found');

        Livewire::test(LfTextFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.not-a-blueprint', 'condition' => 'is']);
    }

    /** @test */
    public function it_changes_the_value_of_selected_property_when_the_user_types()
    {
        Livewire::test(LfTextFilter::class, ['field' => 'item_options', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->assertSet('selected', '')
            ->set('selected', 'option1')
            ->assertSet('selected', 'option1')
            ->assertDispatched('filter-updated',
                field: 'item_options',
                condition: 'is',
                payload: 'option1',
            )
            ->set('selected', 'option2')
            ->assertSet('selected', 'option2')
            ->assertDispatched('filter-updated',
                field: 'item_options',
                condition: 'is',
                payload: 'option2',
            );
    }

    /** @test */
    public function it_loads_a_param_that_is_preset()
    {
        Livewire::test(LfTextFilter::class, ['field' => 'item_options', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->assertSet('selected', '')
            ->dispatch('preset-params', ['item_options:is' => 'option1', 'another_field:is' => 'value'])
            ->assertSet('selected', 'option1');
    }

    /** @test */
    public function it_clears_the_value_when_clear_is_called()
    {
        Livewire::test(LfTextFilter::class, ['field' => 'item_options', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->set('selected', 'option1')
            ->call('clear')
            ->assertSet('selected', '');
    }

    /** @test */
    public function it_clears_the_value_when_clear_option_is_fired()
    {
        Livewire::test(LfTextFilter::class, ['field' => 'item_options', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->set('selected', 'option1')
            ->dispatch('clear-option', [
                'field' => 'item_options',
                'value' => 'option1',
            ])
            ->assertSet('selected', '');
    }

    /** @test */
    public function it_clears_the_value_when_clear_all_filters_event_is_fired()
    {
        Livewire::test(LfTextFilter::class, ['field' => 'item_options', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->set('selected', 'option1')
            ->dispatch('clear-all-filters')
            ->assertSet('selected', '')
            ->assertDispatched('clear-filter',
                field: 'item_options',
                condition: 'is'
            );
    }

    protected function makeEntry($collection, $slug)
    {
        return EntryFactory::id($slug)->collection($collection)->slug($slug)->make();
    }
}
