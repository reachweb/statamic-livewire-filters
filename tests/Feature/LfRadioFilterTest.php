<?php

namespace Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfRadioFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfSelectFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;
use Reach\StatamicLivewireFilters\Support\CustomQueryString;
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
    public function it_hides_options_with_a_zero_count_but_keeps_the_selected_one()
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', true);

        // "Flutes" only matches entry c (option2), so option1 and option3 both count 0, but option1 is selected.
        Livewire::test(LfRadioFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is', 'zero_count' => 'hide', 'selected' => 'option1'])
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['title:contains' => 'Flutes', 'item_options:is' => 'option1']))
            ->assertSee('Option 1')
            ->assertSee('Option 2')
            ->assertDontSee('Option 3');
    }

    #[Test]
    public function it_disables_options_with_a_zero_count_but_not_the_selected_one()
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', true);

        $html = Livewire::test(LfRadioFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is', 'zero_count' => 'disable', 'selected' => 'option1'])
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['title:contains' => 'Flutes']))
            ->assertSee('Option 3')
            ->html();

        $this->assertSame(['option3'], $this->disabledValues($html));
    }

    #[Test]
    public function it_ignores_a_tampered_nested_selection_when_applying_zero_count()
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', true);
        Config::set('statamic-livewire-filters.validate_filter_values', false);

        Livewire::test(LfRadioFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is', 'zero_count' => 'hide'])
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments([]))
            ->set('selected', [['option3']])
            ->assertOk()
            ->assertSee('Option 1')
            ->assertDontSee('Option 3');
    }

    #[Test]
    public function it_keeps_zero_count_options_until_the_counts_are_calculated()
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', true);

        Livewire::test(LfRadioFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is', 'zero_count' => 'hide'])
            ->assertSee('Option 3')
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments([]))
            ->assertDontSee('Option 3');
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
    public function it_labels_open_negative_decimal_and_single_value_buckets()
    {
        // 9-10 only parses when the bounds are compared as numbers.
        Livewire::test(LfRadioFilter::class, ['field' => 'price', 'blueprint' => 'pages.pages', 'buckets' => '-50|-50-0|0-0|0.5-1.5|9-10|10-'])
            ->assertSet('options', [
                '-50' => 'Under 50',
                '-50-0' => '-50 to 0',
                '0-0' => '0 to 0',
                '0.5-1.5' => '0.5 to 1.5',
                '9-10' => '9 to 10',
                '10-' => 'Over 10',
            ])
            ->set('selected', '-50')
            ->assertHasNoErrors()
            ->assertDispatched('filter-updated', field: 'price', condition: 'dual_range', payload: ['min' => null, 'max' => '50'])
            ->dispatch('preset-params', ['price:gte' => '0.5', 'price:lte' => '1.5'])
            ->assertSet('selected', '0.5-1.5')
            ->dispatch('preset-params', ['price:lte' => '50'])
            ->assertSet('selected', '-50');
    }

    #[Test]
    public function the_select_filter_takes_buckets_and_keeps_its_own_view()
    {
        Livewire::test(LfSelectFilter::class, ['field' => 'price', 'blueprint' => 'pages.pages', 'buckets' => '0-100|100-200|200-'])
            ->assertSet('view', 'lf-select')
            ->assertSet('condition', 'dual_range')
            ->assertSeeInOrder(['Under 100', '100 to 200', 'Over 200'])
            ->set('selected', '200-')
            ->assertDispatched('filter-updated', field: 'price', condition: 'dual_range', payload: ['min' => '200', 'max' => null])
            // The select's placeholder option clears the filter through its relay.
            ->set('selected', '')
            ->assertHasNoErrors()
            ->assertDispatched('clear-filter', field: 'price', condition: 'dual_range');
    }

    #[Test]
    public function it_labels_a_bucket_from_options_keyed_by_bucket()
    {
        Livewire::test(LfRadioFilter::class, [
            'field' => 'price',
            'blueprint' => 'pages.pages',
            'buckets' => '0-100|100-200|200-',
            'options' => ['0-100' => 'Up to €100', '200-' => '€200 or more', '300-' => 'Not a bucket'],
        ])
            ->assertSet('options', ['0-100' => 'Up to €100', '100-200' => '100 to 200', '200-' => '€200 or more'])
            ->set('selected', '200-')
            ->assertDispatched('filter-updated', field: 'price', condition: 'dual_range', payload: ['min' => '200', 'max' => null]);
    }

    public static function invalidRangeLists(): array
    {
        return [
            'bucket without a dash' => ['0-100|100', 'any', 'Invalid range list bucket [100]'],
            'bucket with min above max' => ['0-100|200-100', 'any', 'Invalid range list bucket [200-100]'],
            'modifier the counts cannot compare' => ['0-100|100-', 'is|lte', 'Invalid range list modifier [is|lte]'],
            'modifier with one comparison' => ['0-100|100-', 'gte', 'Invalid range list modifier [gte]'],
            'modifier with the ends swapped' => ['0-100|100-', 'lt|gte', 'Invalid range list modifier [lt|gte]'],
            'bucket without a bound' => ['0-100|-', 'any', 'Invalid range list bucket [-]'],
            'bucket that is not a number' => ['a-b', 'any', 'Invalid range list bucket [a-b]'],
        ];
    }

    #[Test]
    #[DataProvider('invalidRangeLists')]
    public function it_rejects_an_invalid_bucket_or_modifier(string $buckets, string $modifier, string $message)
    {
        $this->expectExceptionMessage($message);

        Livewire::test(LfRadioFilter::class, ['field' => 'price', 'blueprint' => 'pages.pages', 'buckets' => $buckets, 'modifier' => $modifier]);
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
    public function it_clears_the_filter_for_an_unknown_or_tampered_bucket_when_validation_is_off()
    {
        Config::set('statamic-livewire-filters.validate_filter_values', false);

        Livewire::test(LfRadioFilter::class, ['field' => 'price', 'blueprint' => 'pages.pages', 'buckets' => '0-100|100-200|200-'])
            ->set('selected', '300-400')
            ->assertDispatched('filter-updated', field: 'price', condition: 'dual_range', payload: '')
            ->set('selected', ['0-100'])
            ->assertOk()
            ->assertDispatched('filter-updated', field: 'price', condition: 'dual_range', payload: '');
    }

    #[Test]
    public function it_restores_a_bucket_with_a_zero_bound_from_the_custom_query_string()
    {
        Config::set('statamic-livewire-filters.custom_query_string', 'filters');

        $this->assertSame(
            ['price:gte' => '0', 'title:is' => 'x'],
            CustomQueryString::sanitize(['price:gte' => '0', 'item_options:is' => '', 'title:is' => 'x']),
        );

        $url = ['params' => ['price:gte' => '0', 'price:lte' => '100']];

        $html = Livewire::withQueryParams($url)
            ->test(LfRadioFilter::class, ['field' => 'price', 'blueprint' => 'pages.pages', 'buckets' => '0-100|100-200|200-'])
            ->assertSet('selected', '0-100')
            ->html();

        $this->assertMatchesRegularExpression('/value="0-100"\s+checked/', $html);

        Livewire::withQueryParams($url)
            ->test(LivewireCollection::class, ['params' => ['from' => 'pages']])
            ->assertSet('params', ['price:gte' => '0', 'price:lte' => '100']);
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
            ->assertSet('selected', '')
            // A tampered event can carry anything; a non-scalar bound counts as missing.
            ->dispatch('preset-params', ['price:gte' => ['200'], 'price:lte' => '100'])
            ->assertOk()
            ->assertSet('selected', '')
            ->dispatch('preset-params', ['price:gte' => '200', 'price:lte' => ['x']])
            ->assertSet('selected', '200-')
            ->dispatch('preset-params', ['price:gte' => ['200']])
            ->assertSet('selected', '200-');

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

        // gt|lte keeps 100 in the lower bucket only, and 250 is the one value above 200.
        Livewire::test(LfRadioFilter::class, ['field' => 'price', 'blueprint' => 'pages.pages', 'buckets' => '0-100|100-200|200-', 'modifier' => 'gt|lte'])
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments([]))
            ->assertViewHas('statamic_field', fn ($field) => $field['counts'] === ['0-100' => 2, '100-200' => 0, '200-' => 1]);
    }

    #[Test]
    public function it_counts_an_entry_without_a_value_in_the_buckets_that_list_it()
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', true);

        $this->makeEntry($this->collection, 'd')->set('title', 'I Have No Price')->set('item_options', 'option3')->save();

        // The Stache lists an entry without a price for `price:lte`, but not once a min is set.
        Livewire::test(LivewireCollection::class, ['params' => ['from' => 'pages', 'price:lte' => '100']])
            ->assertSet('entriesCount', 3);
        Livewire::test(LivewireCollection::class, ['params' => ['from' => 'pages', 'price:gte' => '0', 'price:lte' => '100']])
            ->assertSet('entriesCount', 2);

        Livewire::test(LfRadioFilter::class, ['field' => 'price', 'blueprint' => 'pages.pages', 'buckets' => '-100|0-100|100-'])
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments([]))
            ->assertViewHas('statamic_field', fn ($field) => $field['counts'] === ['-100' => 3, '0-100' => 2, '100-' => 2]);
    }

    #[Test]
    public function it_hides_buckets_with_a_zero_count_but_keeps_the_selected_one()
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', true);

        // option2 only holds the 250 entry, so both lower buckets count 0, but 0-100 is selected.
        Livewire::test(LfRadioFilter::class, ['field' => 'price', 'blueprint' => 'pages.pages', 'buckets' => '0-100|100-200|200-', 'zero_count' => 'hide', 'selected' => '0-100'])
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['item_options:is' => 'option2']))
            ->assertSee('All')
            ->assertSee('Under 100')
            ->assertDontSee('100 to 200')
            ->assertSee('Over 200');
    }

    #[Test]
    public function it_disables_buckets_with_a_zero_count_but_not_the_all_option()
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', true);

        $html = Livewire::test(LfRadioFilter::class, ['field' => 'price', 'blueprint' => 'pages.pages', 'buckets' => '0-100|100-200|200-', 'zero_count' => 'disable'])
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['item_options:is' => 'option2']))
            ->assertSee('Under 100')
            ->html();

        $this->assertSame(['0-100', '100-200'], $this->disabledValues($html));
    }

    #[Test]
    public function it_renders_the_url_bucket_checked_on_first_render_and_all_without_one()
    {
        Config::set('statamic-livewire-filters.enable_query_string', true);

        $html = Livewire::test(LfRadioFilter::class, ['field' => 'price', 'blueprint' => 'pages.pages', 'buckets' => '0-100|100-200|200-'])
            ->html();

        $this->assertMatchesRegularExpression('/value=""\s+checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="[^"]+"\s+checked/', $html);

        $html = Livewire::withQueryParams(['params' => ['price:gte' => '100', 'price:lte' => '200']])
            ->test(LfRadioFilter::class, ['field' => 'price', 'blueprint' => 'pages.pages', 'buckets' => '0-100|100-200|200-'])
            ->assertSet('selected', '100-200')
            ->html();

        $this->assertMatchesRegularExpression('/value="100-200"\s+checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="(|0-100|200-)"\s+checked/', $html);
    }

    protected function makeEntry($collection, $slug)
    {
        return EntryFactory::id($slug)->collection($collection)->slug($slug)->make();
    }
}
