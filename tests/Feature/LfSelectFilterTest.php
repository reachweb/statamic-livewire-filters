<?php

namespace Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfSelectFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class LfSelectFilterTest extends TestCase
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

                            'handle' => 'item_options',
                            'field' => [
                                'type' => 'select',
                                'display' => 'Select',
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
                        [
                            'handle' => 'country',
                            'field' => [
                                'type' => 'dictionary',
                                'display' => 'Country',
                                'dictionary' => 'countries',
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
    public function it_renders_the_component_and_gets_the_options_for_a_checkbox()
    {
        Livewire::test(LfSelectFilter::class, ['field' => 'item_options', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->assertSee('Option 1')
            ->assertSee('Option 2')
            ->assertSee('Option 3');
    }

    #[Test]
    public function it_renders_the_component_with_the_combobox_and_gets_the_options_for_a_checkbox()
    {
        Livewire::test(LfSelectFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is', 'view' => 'lf-select-advanced'])
            ->assertSee('Option 1')
            ->assertSee('Option 2')
            ->assertSee('Option 3')
            ->assertSee('combobox');
    }

    #[Test]
    public function it_throws_a_field_not_found_exception_if_the_field_doesnt_exist()
    {
        $this->expectExceptionMessage('Field [not-a-field] not found');

        Livewire::test(LfSelectFilter::class, ['field' => 'not-a-field', 'blueprint' => 'pages.pages', 'condition' => 'is']);
    }

    #[Test]
    public function it_throws_a_blueprint_not_found_exception_if_the_blueprint_doesnt_exist()
    {
        $this->expectExceptionMessage('Blueprint [not-a-blueprint] not found');

        Livewire::test(LfSelectFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.not-a-blueprint', 'condition' => 'is']);
    }

    #[Test]
    public function it_changes_the_value_of_selected_property_when_an_option_is_set_and_sends_an_event()
    {
        Livewire::test(LfSelectFilter::class, ['field' => 'item_options', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is'])
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

    #[Test]
    public function it_loads_a_param_that_is_preset()
    {
        Livewire::test(LfSelectFilter::class, ['field' => 'item_options', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->assertSet('selected', '')
            ->dispatch('preset-params', ['item_options:is' => 'option1', 'another_field:is' => 'value'])
            ->assertSet('selected', 'option1');
    }

    #[Test]
    public function it_clears_the_value_when_clear_is_called()
    {
        Livewire::test(LfSelectFilter::class, ['field' => 'item_options', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->set('selected', 'option1')
            ->call('clear')
            ->assertSet('selected', '');
    }

    #[Test]
    public function it_clears_the_value_when_clear_option_is_fired()
    {
        Livewire::test(LfSelectFilter::class, ['field' => 'item_options', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->set('selected', 'option1')
            ->dispatch('clear-option', [
                'field' => 'item_options',
                'value' => 'option1',
            ])
            ->assertSet('selected', '');
    }

    #[Test]
    public function it_clears_the_value_when_clear_all_filters_event_is_fired()
    {
        Livewire::test(LfSelectFilter::class, ['field' => 'item_options', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->set('selected', 'option1')
            ->dispatch('clear-all-filters')
            ->assertSet('selected', '')
            ->assertDispatched('clear-filter',
                field: 'item_options',
                condition: 'is'
            );
    }

    #[Test]
    public function it_renders_the_component_and_gets_options_from_dictionary_field()
    {
        Livewire::test(LfSelectFilter::class, ['field' => 'country', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->assertSee('Afghanistan')
            ->assertSee('Albania')
            ->assertSee('Algeria');
    }

    #[Test]
    public function it_filters_taxonomy_terms_with_numeric_slugs()
    {
        Facades\Taxonomy::make('years')->save();
        Facades\Term::make()->taxonomy('years')->inDefaultLocale()->slug('100')->data(['title' => 'One Hundred'])->save();
        Facades\Term::make()->taxonomy('years')->inDefaultLocale()->slug('200')->data(['title' => 'Two Hundred'])->save();
        Facades\Collection::make('vintages')->taxonomies(['years'])->save();

        Facades\Blueprint::make()->setContents([
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
                            'handle' => 'years',
                            'field' => [
                                'type' => 'terms',
                                'taxonomies' => ['years'],
                            ],
                        ],
                    ],
                ],
            ],
        ])->setHandle('vintages')->setNamespace('collections.vintages')->save();

        EntryFactory::collection('vintages')->slug('item-100')->data(['title' => 'Item One Hundred', 'years' => ['100']])->create();
        EntryFactory::collection('vintages')->slug('item-200')->data(['title' => 'Item Two Hundred', 'years' => ['200']])->create();

        Livewire::test(LfSelectFilter::class, ['field' => 'years', 'blueprint' => 'vintages.vintages', 'condition' => 'taxonomy'])
            ->assertViewHas('statamic_field', function ($statamic_field) {
                return array_key_exists('100', $statamic_field['options'])
                    && array_key_exists('200', $statamic_field['options'])
                    && $statamic_field['options']['100'] === 'One Hundred'
                    && $statamic_field['options']['200'] === 'Two Hundred';
            })
            ->set('selected', '100')
            ->assertSet('selected', '100')
            ->assertHasNoErrors('selected')
            ->assertDispatched('filter-updated',
                field: 'years',
                condition: 'taxonomy',
                payload: '100',
            );

        Livewire::test(LivewireCollection::class, ['params' => ['from' => 'vintages']])
            ->dispatch('filter-updated',
                field: 'years',
                condition: 'taxonomy',
                payload: '100',
                modifier: 'any',
            )
            ->assertSee('Item One Hundred')
            ->assertDontSee('Item Two Hundred');

        Livewire::test(LfSelectFilter::class, ['field' => 'years', 'blueprint' => 'vintages.vintages', 'condition' => 'taxonomy'])
            ->dispatch('params-updated', [])
            ->assertViewHas('statamic_field', function ($statamic_field) {
                return $statamic_field['counts']['100'] === 1
                    && $statamic_field['counts']['200'] === 1;
            });
    }

    protected function makeEntry($collection, $slug)
    {
        return EntryFactory::id($slug)->collection($collection)->slug($slug)->make();
    }
}
