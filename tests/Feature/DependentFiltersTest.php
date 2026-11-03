<?php

namespace Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Js;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfCheckboxFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfRadioFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfSelectFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;
use Reach\StatamicLivewireFilters\Support\RangeBoundsQuery;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class DependentFiltersTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('statamic-livewire-filters.enable_dependent_filters', true);

        $collection = Facades\Collection::make('cars')->save();
        Facades\Blueprint::make()->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        ['handle' => 'title', 'field' => ['type' => 'text']],
                        ['handle' => 'car_brand', 'field' => ['type' => 'select', 'options' => [
                            'audi' => 'Audi', 'bmw' => 'BMW', 'citroen' => 'Citroen',
                        ]]],
                        ['handle' => 'car_model', 'field' => ['type' => 'select', 'options' => [
                            'a3' => 'A3', 'q3' => 'Q3', 'x1' => 'X1', 'x5' => 'X5', 'm3' => 'M3', 'c4' => 'C4',
                        ]]],
                        ['handle' => 'fuel', 'field' => ['type' => 'select', 'options' => [
                            'petrol' => 'Petrol', 'diesel' => 'Diesel',
                        ]]],
                    ],
                ],
            ],
        ])->setHandle('cars')->setNamespace('collections.cars')->save();

        foreach ([
            ['audi-a3', 'audi', 'a3', 'petrol'],
            ['audi-a3-diesel', 'audi', 'a3', 'diesel'],
            ['audi-q3', 'audi', 'q3', 'diesel'],
            ['bmw-x1', 'bmw', 'x1', 'petrol'],
            ['bmw-x5', 'bmw', 'x5', 'diesel'],
            ['citroen-c4', 'citroen', 'c4', 'diesel'],
        ] as [$slug, $brand, $model, $fuel]) {
            EntryFactory::id($slug)->collection($collection)->slug($slug)->make()
                ->set('title', $slug)->set('car_brand', $brand)->set('car_model', $model)->set('fuel', $fuel)->save();
        }

        // A draft must not make its model available.
        EntryFactory::id('bmw-m3')->collection($collection)->slug('bmw-m3')->make()
            ->set('title', 'bmw-m3')->set('car_brand', 'bmw')->set('car_model', 'm3')->published(false)->save();
    }

    public static function modelFilters(): array
    {
        return [
            'checkbox' => [LfCheckboxFilter::class],
            'radio' => [LfRadioFilter::class],
            'select' => [LfSelectFilter::class],
        ];
    }

    #[Test]
    #[DataProvider('modelFilters')]
    public function it_narrows_its_options_to_the_values_that_co_occur_with_the_parent(string $filter)
    {
        $component = Livewire::test($filter, $this->modelArguments())
            ->assertSee('Q3')
            ->assertSee('X5')
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['car_brand:is' => 'bmw']))
            ->assertSet('availableOptions', ['x1', 'x5'])
            ->assertDispatched('available-options-updated', ['x1', 'x5'])
            ->assertSee('X1')
            ->assertSee('X5')
            ->assertDontSee('A3')
            ->assertDontSee('Q3')
            ->assertDontSee('M3');

        // Several parent values widen the list; no parent value shows every option.
        $component->dispatch('params-updated', ...$this->paramsUpdatedArguments(['car_brand:in' => 'audi|citroen']))
            ->assertSet('availableOptions', ['a3', 'q3', 'c4'])
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['fuel:is' => 'diesel']))
            ->assertSet('availableOptions', null)
            ->assertSee('Q3')
            ->assertSee('X1');
    }

    #[Test]
    public function it_drops_only_the_selected_values_the_new_parent_value_lacks()
    {
        Livewire::test(LfCheckboxFilter::class, $this->modelArguments())
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['car_brand:in' => 'audi|bmw']))
            ->set('selected', ['a3', 'x1'])
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['car_brand:is' => 'bmw', 'car_model:in' => 'a3|x1']))
            ->assertSet('selected', ['x1'])
            ->assertDispatched('filter-updated', field: 'car_model', condition: 'in', payload: ['x1'], modifier: 'any')
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['car_brand:is' => 'citroen', 'car_model:in' => 'x1']))
            ->assertSet('selected', [])
            ->assertDispatched('clear-filter', field: 'car_model', condition: 'in');
    }

    #[Test]
    #[DataProvider('singleValueFilters')]
    public function it_clears_a_single_selection_the_new_parent_value_lacks(string $filter)
    {
        Livewire::test($filter, $this->modelArguments('is'))
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['car_brand:is' => 'audi']))
            ->set('selected', 'q3')
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['car_brand:is' => 'audi', 'car_model:is' => 'q3']))
            ->assertSet('selected', 'q3')
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['car_brand:is' => 'bmw', 'car_model:is' => 'q3']))
            ->assertSet('selected', '')
            ->assertDispatched('clear-filter', field: 'car_model', condition: 'is');
    }

    public static function singleValueFilters(): array
    {
        return [
            'radio' => [LfRadioFilter::class],
            'select' => [LfSelectFilter::class],
        ];
    }

    #[Test]
    public function it_keeps_the_selection_when_the_parent_value_still_offers_it_or_is_cleared()
    {
        Livewire::test(LfRadioFilter::class, $this->modelArguments('is'))
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['car_brand:is' => 'audi']))
            ->set('selected', 'a3')
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['car_brand:in' => 'audi|bmw', 'car_model:is' => 'a3']))
            ->assertSet('selected', 'a3')
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['car_model:is' => 'a3']))
            ->assertSet('selected', 'a3')
            ->assertNotDispatched('clear-filter');
    }

    #[Test]
    public function another_filter_narrowing_the_options_does_not_clear_or_hide_the_selection()
    {
        // Other active filters narrow the options too, but only a parent change clears.
        Livewire::test(LfRadioFilter::class, $this->modelArguments('is'))
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['car_brand:is' => 'audi']))
            ->set('selected', 'q3')
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['car_brand:is' => 'audi', 'fuel:is' => 'petrol', 'car_model:is' => 'q3']))
            ->assertSet('availableOptions', ['a3'])
            ->assertSet('selected', 'q3')
            ->assertNotDispatched('clear-filter')
            ->assertSee('Q3');
    }

    #[Test]
    public function it_keeps_counts_for_every_option_while_narrowing()
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', true);

        Livewire::test(LfCheckboxFilter::class, $this->modelArguments())
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['car_brand:is' => 'audi']))
            ->assertSet('availableOptions', ['a3', 'q3'])
            ->assertDispatched('counts-updated', ['a3' => 2, 'q3' => 1, 'x1' => 0, 'x5' => 0, 'm3' => 0, 'c4' => 0])
            ->assertSee('A3')
            ->assertSee('(2)')
            ->assertDontSee('X1');
    }

    #[Test]
    public function without_counts_it_only_updates_the_available_options()
    {
        Livewire::test(LfCheckboxFilter::class, $this->modelArguments())
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['car_brand:is' => 'audi']))
            ->assertSet('availableOptions', ['a3', 'q3'])
            ->assertNotDispatched('counts-updated');
    }

    #[Test]
    #[DataProvider('modelFilters')]
    public function it_disables_unavailable_options_instead_of_hiding_them(string $filter)
    {
        $html = Livewire::test($filter, [...$this->modelArguments('is'), 'unavailable' => 'disable'])
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['car_brand:is' => 'citroen']))
            ->html();

        $this->assertMatchesRegularExpression('/value="x5"\s+disabled/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="c4"\s+disabled/', $html);
    }

    #[Test]
    public function it_rejects_an_unknown_unavailable_mode()
    {
        $this->expectExceptionMessage('The unavailable parameter must be `hide` or `disable`, `hidden` given.');

        Livewire::test(LfSelectFilter::class, [...$this->modelArguments('is'), 'unavailable' => 'hidden']);
    }

    #[Test]
    public function it_ignores_params_without_the_collections_encrypted_query()
    {
        Livewire::test(LfCheckboxFilter::class, $this->modelArguments())
            ->dispatch('params-updated', ['car_brand:is' => 'bmw'])
            ->dispatch('params-updated', ['car_brand:is' => 'bmw'], 'forged-token')
            ->assertSet('availableOptions', null)
            ->assertNotDispatched('available-options-updated');
    }

    #[Test]
    public function a_filter_without_depends_on_ignores_the_parent_and_skips_its_render()
    {
        $component = Livewire::test(LfCheckboxFilter::class, ['field' => 'car_model', 'blueprint' => 'cars.cars', 'condition' => 'in'])
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['car_brand:is' => 'bmw']))
            ->assertSet('availableOptions', null);

        $this->assertArrayNotHasKey('html', $component->effects);
    }

    #[Test]
    public function it_skips_its_render_when_the_available_options_did_not_change()
    {
        $component = Livewire::test(LfCheckboxFilter::class, $this->modelArguments())
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['car_brand:is' => 'bmw']));

        $this->assertArrayHasKey('html', $component->effects);

        $component->dispatch('params-updated', ...$this->paramsUpdatedArguments(['car_brand:is' => 'bmw', 'title:contains' => 'bmw']))
            ->assertNotDispatched('available-options-updated');

        $this->assertArrayNotHasKey('html', $component->effects);
    }

    #[Test]
    public function the_collection_sends_the_query_dependent_filters_read_when_they_are_enabled()
    {
        $collection = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'cars', 'car_brand:is' => 'citroen']]);
        $arguments = collect($collection->effects['dispatches'])->firstWhere('name', 'params-updated')['params'];

        Livewire::test(LfSelectFilter::class, $this->modelArguments('is'))
            ->dispatch('params-updated', ...$arguments)
            ->assertSet('availableOptions', ['c4']);

        Config::set('statamic-livewire-filters.enable_dependent_filters', false);

        Livewire::test(LivewireCollection::class, ['params' => ['from' => 'cars']])
            ->assertNotDispatched('params-updated');
    }

    #[Test]
    #[DataProvider('singleValueFilters')]
    public function an_empty_selection_does_not_clear_when_the_parent_changes(string $filter)
    {
        Livewire::test($filter, $this->modelArguments('is'))
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments([]))
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['car_brand:is' => 'bmw']))
            ->assertSet('availableOptions', ['x1', 'x5'])
            ->assertNotDispatched('clear-filter');
    }

    #[Test]
    public function the_first_update_keeps_a_preset_selection()
    {
        // On page load the parent hasn't changed: a preset another filter narrows away, or one the
        // parent doesn't offer, stays selected and visible, keeping the URL the visitor opened.
        foreach ([
            ['car_brand:is' => 'audi', 'fuel:is' => 'petrol', 'car_model:is' => 'q3'],
            ['car_brand:is' => 'citroen', 'car_model:is' => 'q3'],
        ] as $params) {
            Livewire::test(LfRadioFilter::class, $this->modelArguments('is'))
                ->dispatch('preset-params', $params)
                ->dispatch('params-updated', ...$this->paramsUpdatedArguments($params))
                ->assertSet('selected', 'q3')
                ->assertNotDispatched('clear-filter')
                ->assertSee('Q3');
        }
    }

    #[Test]
    public function a_no_op_update_pooled_with_a_real_change_does_not_skip_its_render()
    {
        // The browser pools a component's event calls from one response into one request.
        $filter = Livewire::test(LfCheckboxFilter::class, ['field' => 'car_model', 'blueprint' => 'cars.cars', 'condition' => 'in']);
        $this->dispatchPooled($filter, [
            ['preset-params', [['car_model:in' => 'a3']]],
            ['params-updated', $this->paramsUpdatedArguments(['car_model:in' => 'a3'])],
        ]);
        $this->assertSame(['a3'], $filter->get('selected'));
        $this->assertStringContainsString('wire:click="clear"', $filter->effects['html'] ?? '');

        $this->dispatchPooled($filter, [
            ['params-updated', $this->paramsUpdatedArguments([])],
            ['clear-all-filters', []],
        ]);
        $this->assertSame([], $filter->get('selected'));
        $this->assertArrayHasKey('html', $filter->effects);
        $this->assertStringNotContainsString('wire:click="clear"', $filter->effects['html']);
    }

    #[Test]
    public function it_reads_a_taxonomy_parent()
    {
        Facades\Taxonomy::make('make')->save();
        foreach (['audi', 'bmw'] as $slug) {
            Facades\Term::make()->taxonomy('make')->inDefaultLocale()->slug($slug)->data(['title' => ucfirst($slug)])->save();
        }
        $vehicles = Facades\Collection::make('vehicles')->taxonomies(['make'])->save();
        Facades\Blueprint::make()->setContents(['sections' => ['main' => ['fields' => [
            ['handle' => 'make', 'field' => ['type' => 'terms', 'taxonomies' => ['make']]],
            ['handle' => 'car_model', 'field' => ['type' => 'select', 'options' => ['a3' => 'A3', 'x1' => 'X1']]],
        ]]]])->setHandle('vehicles')->setNamespace('collections.vehicles')->save();
        EntryFactory::id('v-a3')->collection($vehicles)->slug('v-a3')->make()->set('make', ['audi'])->set('car_model', 'a3')->save();
        EntryFactory::id('v-x1')->collection($vehicles)->slug('v-x1')->make()->set('make', ['bmw'])->set('car_model', 'x1')->save();

        Livewire::test(LfCheckboxFilter::class, ['field' => 'car_model', 'blueprint' => 'vehicles.vehicles', 'condition' => 'in', 'depends_on' => 'make'])
            ->dispatch('params-updated', ...$this->paramsUpdatedArguments(['taxonomy:make:any' => 'bmw'], 'vehicles'))
            ->assertSet('availableOptions', ['x1']);
    }

    #[Test]
    #[DataProvider('advancedViews')]
    public function the_advanced_views_start_from_the_available_options(string $filter, string $view)
    {
        $component = Livewire::test($filter, [...$this->modelArguments('is'), 'view' => $view]);

        $this->assertStringContainsString('available: null', $component->html());

        $component->dispatch('params-updated', ...$this->paramsUpdatedArguments(['car_brand:is' => 'bmw']))
            ->assertDispatched('available-options-updated', ['x1', 'x5']);

        $this->assertStringContainsString(
            'available: '.Js::from(['x1', 'x5'])->toHtml(),
            $component->html(),
        );
    }

    public static function advancedViews(): array
    {
        return [
            'checkbox' => [LfCheckboxFilter::class, 'lf-checkbox-advanced'],
            'select' => [LfSelectFilter::class, 'lf-select-advanced'],
        ];
    }

    #[Test]
    #[DataProvider('lockedProperties')]
    public function it_forbids_client_tampering_with_the_dependency_state(string $property, $value)
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(LfCheckboxFilter::class, $this->modelArguments())->set($property, $value);
    }

    public static function lockedProperties(): array
    {
        return [
            'depends_on' => ['depends_on', 'fuel'],
            'unavailable' => ['unavailable', 'disable'],
            'availableOptions' => ['availableOptions', ['m3']],
            'parentValue' => ['parentValue', 'bmw'],
            'parentValueRecorded' => ['parentValueRecorded', true],
        ];
    }

    protected function modelArguments(string $condition = 'in'): array
    {
        return [
            'field' => 'car_model',
            'blueprint' => 'cars.cars',
            'condition' => $condition,
            'depends_on' => 'car_brand',
        ];
    }

    protected function dispatchPooled($component, array $events): void
    {
        $component->update(calls: array_map(fn ($event) => [
            'method' => '__dispatch',
            'params' => $event,
            'path' => '',
        ], $events));
    }

    protected function paramsUpdatedArguments(array $params, string $collection = 'cars'): array
    {
        return [$params, RangeBoundsQuery::encode($collection, $params)];
    }
}
