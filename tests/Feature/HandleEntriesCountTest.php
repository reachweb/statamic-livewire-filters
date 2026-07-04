<?php

namespace Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfCheckboxFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\Traits\HandleEntriesCount;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class HandleEntriesCountTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    private $component;

    protected function setUp(): void
    {
        parent::setUp();

        $collection = Facades\Collection::make('pages')->save();
        $blueprint = Facades\Blueprint::make()->setContents([
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
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $blueprint->setHandle('pages')->setNamespace('collections.'.$collection->handle())->save();

        EntryFactory::id('a')->collection($collection)->slug('a')->make()->set('title', 'Entry A')->set('item_options', 'option1')->save();
        EntryFactory::id('b')->collection($collection)->slug('b')->make()->set('title', 'Entry B')->set('item_options', 'option2')->save();

        $this->component = new class
        {
            use HandleEntriesCount;

            public $condition = 'query_scope';

            public $modifier = 'multiselect';

            public $options = null;

            public $statamic_field = [];

            public function removeParams(array $params, string $fieldHandle): array
            {
                return $this->removeCurrentFieldFromParams($params, $fieldHandle);
            }
        };
    }

    #[Test]
    public function it_removes_the_current_query_scope_field_and_preserves_other_scopes_for_count_queries()
    {
        $params = [
            'query_scope' => 'multiselect|some_other_scope',
            'multiselect:car_type' => '4x4|SUV',
            'some_other_scope:origin' => 'japan',
        ];

        $this->assertSame([
            'query_scope' => 'some_other_scope',
            'some_other_scope:origin' => 'japan',
        ], $this->component->removeParams($params, 'car_type'));
    }

    #[Test]
    public function it_removes_the_query_scope_key_entirely_when_the_current_scope_is_the_only_one()
    {
        $params = [
            'query_scope' => 'multiselect',
            'multiselect:car_type' => '4x4|SUV',
        ];

        $this->assertSame([], $this->component->removeParams($params, 'car_type'));
    }

    #[Test]
    public function it_keeps_the_scope_when_other_fields_still_use_it()
    {
        $params = [
            'query_scope' => 'multiselect|some_other_scope',
            'multiselect:car_type' => '4x4|SUV',
            'multiselect:origin' => 'japan',
            'some_other_scope:color' => 'red',
        ];

        $this->assertSame([
            'query_scope' => 'multiselect|some_other_scope',
            'multiselect:origin' => 'japan',
            'some_other_scope:color' => 'red',
        ], $this->component->removeParams($params, 'car_type'));
    }

    #[Test]
    public function it_returns_statamic_field_counts_when_the_component_uses_blueprint_options()
    {
        $this->component->options = null;

        $this->component->statamic_field = [
            'counts' => [
                'option1' => 2,
                'option2' => 1,
            ],
        ];

        $this->assertSame([
            'option1' => 2,
            'option2' => 1,
        ], $this->component->counts());
    }

    #[Test]
    public function it_returns_updated_statamic_field_counts_when_custom_options_are_used()
    {
        $this->component->options = [
            'custom1' => 'Custom 1',
            'custom2' => 'Custom 2',
        ];

        $this->component->statamic_field = [
            'counts' => [
                'custom1' => 3,
                'custom2' => 1,
            ],
        ];

        $this->assertSame([
            'custom1' => 3,
            'custom2' => 1,
        ], $this->component->counts());
    }

    #[Test]
    public function it_ignores_a_params_updated_dispatch_when_the_counts_feature_is_disabled()
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', false);

        Livewire::test(LfCheckboxFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->dispatch('params-updated', [])
            ->assertNotDispatched('counts-updated')
            ->assertViewHas('statamic_field', function ($statamic_field) {
                return $statamic_field['counts'] === ['option1' => null, 'option2' => null];
            });
    }

    #[Test]
    public function it_computes_counts_for_the_blueprint_field_when_the_feature_is_enabled()
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', true);

        Livewire::test(LfCheckboxFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->dispatch('params-updated', [])
            ->assertDispatched('counts-updated')
            ->assertViewHas('statamic_field', function ($statamic_field) {
                return $statamic_field['counts'] === ['option1' => 1, 'option2' => 1];
            });
    }

    #[Test]
    public function it_applies_visibility_params_to_the_counts_query_so_counts_match_the_entries_query()
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', true);

        EntryFactory::id('draft')->collection('pages')->slug('draft')->make()
            ->set('title', 'Draft')->set('item_options', 'option1')->published(false)->save();

        Livewire::test(LfCheckboxFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->dispatch('params-updated', [])
            ->assertViewHas('statamic_field', function ($statamic_field) {
                return $statamic_field['counts'] === ['option1' => 1, 'option2' => 1];
            })
            ->dispatch('params-updated', ['status:is' => 'any'])
            ->assertViewHas('statamic_field', function ($statamic_field) {
                return $statamic_field['counts'] === ['option1' => 2, 'option2' => 1];
            });
    }

    #[Test]
    public function it_keeps_the_counts_query_pinned_to_the_locked_collection_when_collection_params_are_injected()
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', true);

        Facades\Collection::make('secrets')->save();
        EntryFactory::id('s1')->collection('secrets')->slug('s1')->make()->set('item_options', 'option1')->save();
        EntryFactory::id('s2')->collection('secrets')->slug('s2')->make()->set('item_options', 'option1')->save();

        Livewire::test(LfCheckboxFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->dispatch('params-updated', ['from' => 'secrets'])
            ->assertViewHas('statamic_field', function ($statamic_field) {
                return $statamic_field['counts'] === ['option1' => 1, 'option2' => 1];
            })
            ->dispatch('params-updated', ['collection' => 'secrets', 'in' => 'secrets'])
            ->assertViewHas('statamic_field', function ($statamic_field) {
                return $statamic_field['counts'] === ['option1' => 1, 'option2' => 1];
            });
    }
}
