<?php

namespace Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Livewire\Livewire;
use Reach\StatamicLivewireFilters\Http\Livewire\LfRangeFilter;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class LfRangeFilterTest extends TestCase
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

                            'handle' => 'max_items',
                            'field' => [
                                'type' => 'text',
                                'display' => 'Text',
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
    public function it_renders_the_component()
    {
        Livewire::test(LfRangeFilter::class, [
            'field' => 'max_items',
            'blueprint' => 'pages.pages',
            'condition' => 'gte',
            'min' => 1,
            'max' => 4,
        ])
            ->assertSee('1')
            ->assertSee('4');
    }

    /** @test */
    public function it_throws_a_field_not_found_exception_if_the_field_doesnt_exist()
    {
        $this->expectExceptionMessage('Field [not-a-field] not found');

        Livewire::test(LfRangeFilter::class, [
            'field' => 'not-a-field',
            'blueprint' => 'pages.pages',
            'condition' => 'gte',
            'min' => 1,
            'max' => 4,
        ]);
    }

    /** @test */
    public function it_throws_a_blueprint_not_found_exception_if_the_blueprint_doesnt_exist()
    {
        $this->expectExceptionMessage('Blueprint [not-a-blueprint] not found');

        Livewire::test(LfRangeFilter::class, [
            'field' => 'max_items',
            'blueprint' => 'pages.not-a-blueprint',
            'condition' => 'gte',
            'min' => 1,
            'max' => 4,
        ]);
    }

    /** @test */
    public function it_changes_the_value_of_selected_property_when_slider_changes()
    {
        Livewire::test(LfRangeFilter::class, [
            'field' => 'max_items',
            'blueprint' => 'pages.pages',
            'condition' => 'gte',
            'min' => 1,
            'max' => 4,
            'default' => 2,
        ])
            ->assertSet('selected', 2)
            ->set('selected', 3)
            ->assertSet('selected', 3)
            ->assertDispatched('filter-updated',
                field: 'max_items',
                condition: 'gte',
                payload: 3,
            );
    }

    /** @test */
    public function it_can_be_reset_using_the_clear_method()
    {
        Livewire::test(LfRangeFilter::class, [
            'field' => 'max_items',
            'blueprint' => 'pages.pages',
            'condition' => 'gte',
            'min' => 1,
            'max' => 4,
            'default' => 2,
        ])
            ->set('selected', 3)
            ->assertDispatched('filter-updated',
                field: 'max_items',
                condition: 'gte',
                payload: 3,
            )
            ->call('clear')
            ->assertSet('selected', 2)
            ->assertDispatched('clear-filter',
                field: 'max_items',
                condition: 'gte',
            );
    }

    /** @test */
    public function it_can_be_reset_using_the_clear_option_event()
    {
        Livewire::test(LfRangeFilter::class, [
            'field' => 'max_items',
            'blueprint' => 'pages.pages',
            'condition' => 'gte',
            'min' => 1,
            'max' => 4,
            'default' => 2,
        ])
            ->set('selected', 3)
            ->assertDispatched('filter-updated',
                field: 'max_items',
                condition: 'gte',
                payload: 3,
            )
            ->dispatch('clear-option', ['field' => 'max_items', 'value' => 3])
            ->assertSet('selected', 2)
            ->assertDispatched('clear-filter',
                field: 'max_items',
                condition: 'gte',
            );
    }

    /** @test */
    public function it_loads_a_param_that_is_preset()
    {
        Livewire::test(LfRangeFilter::class, [
            'field' => 'max_items',
            'blueprint' => 'pages.pages',
            'condition' => 'gte',
            'min' => 1,
            'max' => 4,
            'default' => 2,

        ])
            ->assertSet('selected', 2)
            ->dispatch('preset-params', ['max_items:gte' => 3, 'another_field:is' => 'value'])
            ->assertSet('selected', 3);
    }

    protected function makeEntry($collection, $slug)
    {
        return EntryFactory::id($slug)->collection($collection)->slug($slug)->make();
    }
}
