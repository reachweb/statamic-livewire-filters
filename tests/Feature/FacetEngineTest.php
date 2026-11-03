<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfCheckboxFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfRangeFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;
use Reach\StatamicLivewireFilters\Support\CountQueryPool;
use Reach\StatamicLivewireFilters\Support\RangeBoundsQuery;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class FacetEngineTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('statamic-livewire-filters.enable_facet_engine', true);
        Config::set('statamic-livewire-filters.enable_filter_values_count', true);
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        Facades\Collection::make('pages')->save();
        Facades\Collection::make('other')->save();
        Facades\Blueprint::make()->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        ['handle' => 'title', 'field' => ['type' => 'text']],
                        ['handle' => 'item_options', 'field' => ['type' => 'checkboxes', 'options' => ['option1' => 'Option 1', 'option2' => 'Option 2']]],
                        ['handle' => 'size', 'field' => ['type' => 'integer']],
                    ],
                ],
            ],
        ])->setHandle('pages')->setNamespace('collections.pages')->save();

        EntryFactory::id('a')->collection('pages')->slug('a')->make()->set('item_options', 'option1')->set('size', 2)->save();
        EntryFactory::id('b')->collection('pages')->slug('b')->make()->set('item_options', 'option2')->set('size', 7)->save();
    }

    #[Test]
    public function filters_rendered_before_the_collection_are_in_its_first_encrypted_query()
    {
        $checkbox = $this->checkbox();
        $range = $this->range();

        $collection = $this->collection();

        $checkbox->assertNotDispatched('register-facet');
        $range->assertNotDispatched('register-facet');
        $this->assertSame([['item_options', null], ['size', null]], $collection->get('facets'));
        $this->assertSame([['item_options', null], ['size', null]], $this->decodedQuery($collection)['facets']);
    }

    #[Test]
    public function a_filter_rendered_after_the_collection_registers_through_an_event()
    {
        $collection = $this->collection();
        $this->assertSame([], $collection->get('facets'));
        $this->assertArrayNotHasKey('facets', $this->decodedQuery($collection));

        $this->checkbox(['condition' => 'query_scope', 'modifier' => 'multiselect'])
            ->assertDispatched('register-facet', field: 'item_options', scope: 'multiselect', collection: 'pages');

        $collection->dispatch('register-facet', field: 'item_options', scope: 'multiselect', collection: 'pages')
            ->assertSet('facets', [['item_options', 'multiselect']])
            ->assertNotDispatched('params-updated');

        // The next filter state carries it.
        $collection->dispatch('filter-updated', field: 'title', condition: 'contains', payload: 'x', modifier: 'any');
        $this->assertSame([['item_options', 'multiselect']], $this->decodedQuery($collection)['facets']);
    }

    #[Test]
    public function the_collection_only_accepts_well_formed_specs_for_its_own_collections()
    {
        $collection = $this->collection();

        foreach ([
            ['field' => 'data->secret', 'scope' => null, 'collection' => 'pages'],
            ['field' => 'item_options', 'scope' => ['multiselect'], 'collection' => 'pages'],
            ['field' => 'item_options', 'scope' => null, 'collection' => 'other'],
            ['field' => 'item_options', 'scope' => null, 'collection' => null],
        ] as $event) {
            $collection->dispatch('register-facet', ...$event)->assertSet('facets', []);
        }

        $collection->dispatch('register-facet', field: 'item_options', scope: null, collection: 'pages')
            ->dispatch('register-facet', field: 'item_options', scope: null, collection: 'pages')
            ->assertSet('facets', [['item_options', null]]);

        foreach (range(1, CountQueryPool::MAX_FACETS + 5) as $i) {
            $collection->dispatch('register-facet', field: "field_{$i}", scope: null, collection: 'pages');
        }

        $this->assertCount(CountQueryPool::MAX_FACETS, $collection->get('facets'));
    }

    #[Test]
    public function nothing_registers_with_the_engine_off()
    {
        Config::set('statamic-livewire-filters.enable_facet_engine', false);

        $this->checkbox();
        $collection = $this->collection();

        $this->checkbox()->assertNotDispatched('register-facet');
        $this->assertSame([], $collection->get('facets'));
        $this->assertArrayNotHasKey('facets', $this->decodedQuery($collection));

        $collection->dispatch('register-facet', field: 'item_options', scope: null, collection: 'pages')
            ->assertSet('facets', []);
    }

    #[Test]
    public function a_filter_registers_only_when_its_own_feature_is_on()
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', false);

        $this->checkbox();
        $this->range();

        $this->assertSame([['size', null]], $this->collection()->get('facets'));
    }

    #[Test]
    public function counts_and_bounds_are_the_same_with_the_engine_on_and_off()
    {
        $results = [];

        foreach ([false, true] as $engine) {
            Config::set('statamic-livewire-filters.enable_facet_engine', $engine);
            app()->forgetScopedInstances();

            $checkbox = $this->checkbox();
            $range = $this->range();
            $collection = $this->collection();

            foreach ([[], ['size:gte' => 5], ['item_options:is' => 'option1']] as $params) {
                $collection->set('params', $params);
                $collection->call('$refresh');

                $event = RangeBoundsQuery::encode('pages', $params, null, 0, [], $collection->get('facets'));
                app()->forgetScopedInstances();

                $results[$engine][] = [
                    $checkbox->dispatch('params-updated', $params, $event)->get('statamic_field')['counts'],
                    $range->dispatch('params-updated', $params, $event)->get('dynamicMin'),
                    $range->get('dynamicMax'),
                ];
            }
        }

        $this->assertSame([[['option1' => 1, 'option2' => 1], 2, 7], [['option1' => 0, 'option2' => 1], 2, 7], [['option1' => 1, 'option2' => 1], 2, 2]], $results[false]);
        $this->assertSame($results[false], $results[true]);
    }

    protected function checkbox(array $arguments = [])
    {
        return Livewire::test(LfCheckboxFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is', ...$arguments]);
    }

    protected function range()
    {
        return Livewire::test(LfRangeFilter::class, ['field' => 'size', 'blueprint' => 'pages.pages', 'condition' => 'gte', 'min' => 0, 'max' => 10]);
    }

    protected function collection()
    {
        return Livewire::test(LivewireCollection::class, ['params' => ['from' => 'pages']]);
    }

    protected function decodedQuery($collection): array
    {
        $arguments = collect($collection->effects['dispatches'])->firstWhere('name', 'params-updated')['params'];

        return RangeBoundsQuery::decodeForCollection($arguments[1], 'pages');
    }
}
