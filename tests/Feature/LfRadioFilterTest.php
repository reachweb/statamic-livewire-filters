<?php

namespace Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfRadioFilter;
use Reach\StatamicLivewireFilters\Tests\DispatchesParamsUpdated;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class LfRadioFilterTest extends TestCase
{
    use DispatchesParamsUpdated, PreventSavingStacheItemsToDisk;

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
                            'handle' => 'price',
                            'field' => [
                                'type' => 'integer',
                                'display' => 'Price',
                            ],
                        ],
                        [

                            'handle' => 'item_options',
                            'field' => [
                                'type' => 'radio',
                                'display' => 'Radio',
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

        $this->makeEntry($this->collection, 'a')->set('title', 'I Love Guitars')->set('item_options', 'option1')->set('price', 50)->save();
        $this->makeEntry($this->collection, 'b')->set('title', 'I Love Drums')->set('item_options', 'option1')->set('price', 100)->save();
        $this->makeEntry($this->collection, 'c')->set('title', 'I Hate Flutes')->set('item_options', 'option2')->set('price', 250)->save();
    }

    #[Test]
    public function it_renders_the_component_and_gets_the_options_for_a_checkbox()
    {
        Livewire::test(LfRadioFilter::class, ['field' => 'item_options', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->assertSee('Option 1')
            ->assertSee('Option 2')
            ->assertSee('Option 3');
    }

    #[Test]
    public function it_throws_a_field_not_found_exception_if_the_field_doesnt_exist()
    {
        $this->expectExceptionMessage('Field [not-a-field] not found');

        Livewire::test(LfRadioFilter::class, ['field' => 'not-a-field', 'blueprint' => 'pages.pages', 'condition' => 'is']);
    }

    #[Test]
    public function it_throws_a_blueprint_not_found_exception_if_the_blueprint_doesnt_exist()
    {
        $this->expectExceptionMessage('Blueprint [not-a-blueprint] not found');

        Livewire::test(LfRadioFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.not-a-blueprint', 'condition' => 'is']);
    }

    #[Test]
    public function it_changes_the_value_of_selected_property_when_an_option_is_set_and_sends_an_event()
    {
        Livewire::test(LfRadioFilter::class, ['field' => 'item_options', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is'])
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
    public function it_does_not_accept_a_value_not_in_the_options_array()
    {
        Livewire::test(LfRadioFilter::class, ['field' => 'item_options', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->assertSet('selected', '')
            ->set('selected', 'not-an-option')
            ->assertHasErrors('selected')
            ->assertNotDispatched('filter-updated');
    }

    #[Test]
    public function it_can_turn_off_validation_in_the_config()
    {
        Config::set('statamic-livewire-filters.validate_filter_values', false);

        Livewire::test(LfRadioFilter::class, ['field' => 'item_options', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->assertSet('selected', '')
            ->set('selected', 'not-an-option')
            ->assertSet('selected', 'not-an-option')
            ->assertDispatched('filter-updated',
                field: 'item_options',
                condition: 'is',
                payload: 'not-an-option',
            );
    }

    #[Test]
    public function it_loads_a_param_that_is_preset()
    {
        Livewire::test(LfRadioFilter::class, ['field' => 'item_options', 'collection' => 'pages', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->assertSet('selected', '')
            ->dispatch('preset-params', ['item_options:is' => 'option1', 'another_field:is' => 'value'])
            ->assertSet('selected', 'option1');
    }

    #[Test]
    public function it_calculates_the_count_for_each_entry()
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', true);

        Livewire::test(LfRadioFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->assertSet('selected', '')
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['item_options:is' => 'option1']))
            ->assertViewHas('statamic_field', function ($statamic_field) {
                return $statamic_field['counts'] === ['option1' => 2, 'option2' => 1, 'option3' => 0];
            })
            ->assertSeeHtml('<span class="text-lf-muted ml-1">(2)</span>');

    }

    #[Test]
    public function it_leaves_counts_unset_on_initial_mount_and_populates_them_after_params_updated()
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', true);

        Livewire::test(LfRadioFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->assertViewHas('statamic_field', function ($statamic_field) {
                return $statamic_field['counts'] === ['option1' => null, 'option2' => null, 'option3' => null];
            })
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments([]))
            ->assertViewHas('statamic_field', function ($statamic_field) {
                return $statamic_field['counts'] === ['option1' => 2, 'option2' => 1, 'option3' => 0];
            });
    }

    #[Test]
    public function it_clears_the_value_when_clear_is_called()
    {
        Livewire::test(LfRadioFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->set('selected', 'option1')
            ->call('clear')
            ->assertSet('selected', '');
    }

    #[Test]
    public function it_clears_the_value_when_clear_option_is_fired()
    {
        Livewire::test(LfRadioFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is'])
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
        Livewire::test(LfRadioFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->set('selected', 'option1')
            ->dispatch('clear-all-filters')
            ->assertSet('selected', '')
            ->assertDispatched('clear-filter',
                field: 'item_options',
                condition: 'is'
            );
    }

    #[Test]
    public function it_uses_custom_options_when_provided()
    {
        $customOptions = [
            'custom1' => 'Custom 1',
            'custom2' => 'Custom 2',
        ];

        Livewire::test(LfRadioFilter::class, [
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

    #[Test]
    public function it_ignores_non_array_options()
    {
        Livewire::test(LfRadioFilter::class, [
            'field' => 'item_options',
            'blueprint' => 'pages.pages',
            'condition' => 'is',
            'options' => 'not-an-array',
        ])
            ->assertSee('Option 1')
            ->assertSee('Option 2')
            ->assertSee('Option 3');
    }

    #[Test]
    public function it_turns_buckets_into_labelled_range_list_options()
    {
        Livewire::test(LfRadioFilter::class, ['field' => 'price', 'blueprint' => 'pages.pages', 'buckets' => '0-100|100-200|200-'])
            ->assertSet('condition', 'dual_range')
            ->assertSet('view', 'lf-range-list')
            ->assertSet('options', ['0-100' => 'Under 100', '100-200' => '100 to 200', '200-' => 'Over 200'])
            ->assertSeeInOrder(['All', 'Under 100', '100 to 200', 'Over 200']);
    }

    #[Test]
    public function it_rejects_a_malformed_bucket()
    {
        $this->expectExceptionMessage('Invalid range list bucket [100]');

        Livewire::test(LfRadioFilter::class, ['field' => 'price', 'blueprint' => 'pages.pages', 'buckets' => '0-100|100']);
    }

    #[Test]
    public function selecting_a_bucket_sends_its_bounds_and_an_open_end_as_null()
    {
        Livewire::test(LfRadioFilter::class, ['field' => 'price', 'blueprint' => 'pages.pages', 'buckets' => '0-100|100-200|200-'])
            ->set('selected', '100-200')
            ->assertDispatched('filter-updated', field: 'price', condition: 'dual_range', payload: ['min' => '100', 'max' => '200'])
            ->set('selected', '200-')
            ->assertDispatched('filter-updated', field: 'price', condition: 'dual_range', payload: ['min' => '200', 'max' => null])
            ->set('selected', '')
            ->assertHasNoErrors()
            ->assertDispatched('filter-updated', field: 'price', condition: 'dual_range', payload: '')
            ->set('selected', '300-400')
            ->assertHasErrors('selected');
    }

    #[Test]
    public function it_selects_the_bucket_matching_preset_bounds()
    {
        Livewire::test(LfRadioFilter::class, ['field' => 'price', 'blueprint' => 'pages.pages', 'buckets' => '0-100|100-200|200-'])
            ->dispatch('preset-params', ['price:gte' => '100', 'price:lte' => '200'])
            ->assertSet('selected', '100-200')
            ->dispatch('preset-params', ['price:gte' => '200'])
            ->assertSet('selected', '200-')
            ->dispatch('preset-params', ['price:gte' => '150', 'price:lte' => '200'])
            ->assertSet('selected', '');

        Livewire::test(LfRadioFilter::class, ['field' => 'price', 'blueprint' => 'pages.pages', 'buckets' => '0-100|100-', 'modifier' => 'gte|lt'])
            ->dispatch('preset-params', ['price:gte' => '0', 'price:lt' => '100'])
            ->assertSet('selected', '0-100');
    }

    #[Test]
    public function it_counts_entries_into_buckets_with_the_modifier_comparisons()
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', true);

        // 100 sits on the shared edge: gte/lte counts it in both buckets, gte|lt only in the upper one.
        Livewire::test(LfRadioFilter::class, ['field' => 'price', 'blueprint' => 'pages.pages', 'buckets' => '0-100|100-200|200-'])
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['price:gte' => '200', 'item_options:is' => 'option1']))
            ->assertViewHas('statamic_field', fn ($field) => $field['counts'] === ['0-100' => 2, '100-200' => 1, '200-' => 0]);

        Livewire::test(LfRadioFilter::class, ['field' => 'price', 'blueprint' => 'pages.pages', 'buckets' => '0-100|100-200|200-', 'modifier' => 'gte|lt'])
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments([]))
            ->assertViewHas('statamic_field', fn ($field) => $field['counts'] === ['0-100' => 1, '100-200' => 1, '200-' => 1])
            ->assertSeeHtml('<span class="text-lf-muted ml-1">(1)</span>');
    }

    protected function makeEntry($collection, $slug)
    {
        return EntryFactory::id($slug)->collection($collection)->slug($slug)->make();
    }
}
