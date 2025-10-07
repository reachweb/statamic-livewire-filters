<?php

namespace Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfDateFilter;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class LfDateFilterTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    protected $collection;

    protected $blueprint;

    protected function setUp(): void
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

                            'handle' => 'item_from',
                            'field' => [
                                'type' => 'date',
                                'display' => 'Radio',
                                'listable' => 'hidden',
                                'mode' => 'single',
                                'earliest_date' => '2024-01-01',
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

    #[Test]
    public function it_renders_the_component_and_gets_an_input_box_with_data_flatpickr()
    {
        Livewire::test(LfDateFilter::class, ['field' => 'item_from', 'blueprint' => 'pages.pages', 'condition' => 'is_after'])
            ->assertSee('data-flatpickr');
    }

    #[Test]
    public function it_throws_a_field_not_found_exception_if_the_field_doesnt_exist()
    {
        $this->expectExceptionMessage('Field [not-a-field] not found');

        Livewire::test(LfDateFilter::class, ['field' => 'not-a-field', 'blueprint' => 'pages.pages', 'condition' => 'is']);
    }

    #[Test]
    public function it_throws_a_blueprint_not_found_exception_if_the_blueprint_doesnt_exist()
    {
        $this->expectExceptionMessage('Blueprint [not-a-blueprint] not found');

        Livewire::test(LfDateFilter::class, ['field' => 'item_from', 'blueprint' => 'pages.not-a-blueprint', 'condition' => 'is']);
    }

    #[Test]
    public function it_changes_the_value_of_selected_property_when_the_user_types()
    {
        Livewire::test(LfDateFilter::class, ['field' => 'item_from', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is_after'])
            ->assertSet('selected', '')
            ->set('selected', '2024-03-01')
            ->assertSet('selected', '2024-03-01')
            ->assertDispatched('filter-updated',
                field: 'item_from',
                condition: 'is_after',
                payload: '2024-03-01',
            )
            ->set('selected', '2024-05-01')
            ->assertSet('selected', '2024-05-01')
            ->assertDispatched('filter-updated',
                field: 'item_from',
                condition: 'is_after',
                payload: '2024-05-01',
            );
    }

    #[Test]
    public function it_clears_the_value_when_clear_is_called()
    {
        Livewire::test(LfDateFilter::class, ['field' => 'item_from', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is_after'])
            ->set('selected', '2024-03-01')
            ->call('clear')
            ->assertSet('selected', '');
    }

    #[Test]
    public function it_clears_the_value_when_clear_option_is_fired()
    {
        Livewire::test(LfDateFilter::class, ['field' => 'item_from', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is_after'])
            ->set('selected', '2024-03-01')
            ->dispatch('clear-option', [
                'field' => 'item_from',
                'value' => '2024-03-01',
            ])
            ->assertSet('selected', '');
    }

    #[Test]
    public function it_clears_the_value_when_clear_all_filters_event_is_fired()
    {
        Livewire::test(LfDateFilter::class, ['field' => 'item_from', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is_after'])
            ->set('selected', '2024-03-01')
            ->dispatch('clear-all-filters')
            ->assertSet('selected', '')
            ->assertDispatched('clear-filter',
                field: 'item_from',
                condition: 'is_after'
            );
    }

    #[Test]
    public function it_loads_a_param_that_is_preset()
    {
        Livewire::test(LfDateFilter::class, ['field' => 'item_from', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->assertSet('selected', '')
            ->dispatch('preset-params', ['item_from:is' => '2024-05-01', 'another_field:is' => 'value'])
            ->assertSet('selected', '2024-05-01');
    }

    protected function makeEntry($collection, $slug)
    {
        return EntryFactory::id($slug)->collection($collection)->slug($slug)->make();
    }
}
