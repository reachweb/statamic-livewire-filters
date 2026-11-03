<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Support\CountEntries;
use Reach\StatamicLivewireFilters\Support\CountQueryPool;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;
use Statamic\Query\EloquentQueryBuilder;
use Statamic\Query\ResolveValue;
use Statamic\Tags\Collection\Entries as StatamicEntries;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;

class CountQueryPoolTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use PreventSavingStacheItemsToDisk;

    protected int $stacheQueries = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Facades\Taxonomy::make('brands')->save();
        Facades\Term::make()->taxonomy('brands')->inDefaultLocale()->slug('toyota')->data(['title' => 'Toyota'])->save();
        Facades\Term::make()->taxonomy('brands')->inDefaultLocale()->slug('bmw')->data(['title' => 'BMW'])->save();

        foreach (['cars', 'trucks'] as $handle) {
            Facades\Collection::make($handle)->taxonomies(['brands'])->save();
            $this->vehicleBlueprint()->setHandle($handle)->setNamespace('collections.'.$handle)->save();
        }

        Facades\Collection::make('news')->dated(true)->futureDateBehavior('private')->pastDateBehavior('public')->save();
        $this->vehicleBlueprint()->setHandle('news')->setNamespace('collections.news')->save();

        $this->vehicle('cars', 'car-a', ['title' => 'Car A', 'color' => ['red', 'blue'], 'transmission' => 'manual', 'seats' => 4, 'year' => '2020-05-22', 'brands' => ['toyota']]);
        $this->vehicle('cars', 'car-b', ['title' => 'Car B', 'color' => ['red'], 'transmission' => 'automatic', 'seats' => 7, 'year' => '2022-02-02', 'brands' => ['bmw']]);
        $this->vehicle('cars', 'car-c', ['title' => 'Car C', 'color' => ['green'], 'transmission' => 'manual', 'seats' => null, 'year' => '2024-12-30']);
        $this->vehicle('cars', 'car-d', ['title' => 'Car D', 'color' => ['blue'], 'transmission' => 'semi', 'seats' => 2, 'year' => '2018-01-01', 'brands' => ['toyota']]);
        $this->vehicle('cars', 'car-draft', ['title' => 'Car Draft', 'color' => ['red'], 'transmission' => 'manual', 'seats' => 9, 'year' => '2026-01-01'], false);
        $this->vehicle('trucks', 'truck-a', ['title' => 'Truck A', 'color' => ['white'], 'transmission' => 'manual', 'seats' => 3, 'year' => '2019-06-06', 'brands' => ['toyota']]);
        $this->vehicle('trucks', 'truck-b', ['title' => 'Truck B', 'color' => ['red'], 'transmission' => 'automatic', 'seats' => 2, 'year' => '2021-07-07']);

        EntryFactory::id('news-past')->collection('news')->slug('news-past')->date('2020-01-01')->data(['title' => 'Past', 'color' => ['red']])->create();
        EntryFactory::id('news-future')->collection('news')->slug('news-future')->date(now()->addYear()->format('Y-m-d'))->data(['title' => 'Future', 'color' => ['blue']])->create();

        Event::listen(QueryExecuted::class, fn () => $this->stacheQueries++);
    }

    #[Test]
    public function fields_sharing_base_params_share_one_filter_pass()
    {
        $pool = app(CountQueryPool::class);
        $base = ['transmission:is' => 'manual'];

        $this->withFakeQueries(function () use ($pool, $base) {
            foreach (['color', 'seats', 'year', 'title'] as $field) {
                $pool->getFieldValues('cars', $base, $field);
            }
        });
        $this->assertSame(1, $this->stacheQueries);

        $this->withFakeQueries(fn () => $pool->getFieldValues('cars', ['transmission:is' => 'automatic'], 'color'));
        $this->assertSame(1, $this->stacheQueries, 'different base params need their own filter pass');

        $this->withFakeQueries(fn () => $pool->getFieldValues('cars', $base, 'color'));
        $this->assertSame(0, $this->stacheQueries, 'identical call must be served from the pool');

        foreach (['color', 'seats', 'year', 'title'] as $field) {
            $this->assertSame($this->reference(['from' => 'cars'] + $base, $field), $this->pooled('cars', $base, $field));
        }
    }

    #[Test]
    public function a_from_in_the_params_neither_splits_the_pool_nor_overrides_the_collection()
    {
        $pool = app(CountQueryPool::class);

        $this->withFakeQueries(function () use ($pool) {
            $pool->getFieldValues('cars', ['from' => 'cars', 'transmission:is' => 'manual'], 'seats');
            $pool->getFieldValues('cars', ['transmission:is' => 'manual'], 'color');
        });
        $this->assertSame(1, $this->stacheQueries, 'bounds (with from) and counts (without) must share the key set');

        $this->assertSame($this->reference(['from' => 'cars', 'transmission:is' => 'manual'], 'seats'), $this->pooled('cars', ['from' => 'cars', 'transmission:is' => 'manual'], 'seats'));
        $this->assertSame($this->reference(['from' => 'cars'], 'title'), $this->pooled('cars', ['from' => 'trucks'], 'title'));
        $this->assertSame($this->reference(['from' => 'cars'], 'title'), $this->pooled('cars', ['from' => 'cars|trucks'], 'title'));
    }

    public static function queryCases(): array
    {
        return [
            'single collection, array values' => [['from' => 'cars'], 'color'],
            'single collection, null values' => [['from' => 'cars'], 'seats'],
            'single collection, date values (Carbon)' => [['from' => 'cars'], 'year'],
            'multi collection a|b' => [['from' => 'cars|trucks'], 'title'],
            'multi collection a|b, array values' => [['from' => 'cars|trucks'], 'color'],
            'multi collection as an array' => [['from' => ['cars', 'trucks']], 'title'],
            'multi collection with not_from' => [['from' => 'cars|trucks|news', 'not_from' => 'news'], 'title'],
            'all collections' => [['from' => '*'], 'title'],
            'multiselect query scope' => [['from' => 'cars', 'query_scope' => 'multiselect', 'multiselect:color' => 'red|green'], 'transmission'],
            'taxonomy condition' => [['from' => 'cars|trucks', 'taxonomy:brands:any' => 'toyota'], 'title'],
            'draft status' => [['from' => 'cars', 'status:is' => 'draft'], 'title'],
            'any status' => [['from' => 'cars', 'status:is' => 'any'], 'title'],
            'dated collection hides future' => [['from' => 'news'], 'title'],
            'dated collection show_future' => [['from' => 'news', 'show_future' => true], 'title'],
            'dated collection any status' => [['from' => 'news', 'status:is' => 'any'], 'title'],
            'random sort' => [['from' => 'cars', 'sort' => 'random'], 'title'],
            'explicit sort' => [['from' => 'cars', 'sort' => 'title:desc'], 'title'],
            'limit keeps the sorted subset' => [['from' => 'cars', 'sort' => 'title:desc', 'limit' => 2], 'title'],
            'offset keeps the sorted subset' => [['from' => 'cars', 'sort' => 'title:asc', 'limit' => 2, 'offset' => 1], 'title'],
            'no matches' => [['from' => 'cars', 'title:is' => 'nope'], 'title'],
            'nothing can match (no past, no future)' => [['from' => 'news', 'show_future' => false, 'show_past' => false], 'title'],
        ];
    }

    #[Test]
    #[DataProvider('queryCases')]
    public function pooled_values_equal_the_hydrated_query_values(array $params, string $field)
    {
        $expected = $this->reference($params, $field);

        $this->assertSame($expected, $this->pooled($params['from'], collect($params)->except('from')->all(), $field));
        $this->assertSame($expected, $this->normalize((new CountEntries(Parameters::make($params, Context::make([]))))->pluck($field)));
    }

    #[Test]
    public function the_fixtures_exercise_the_cases()
    {
        $this->assertSame(['"Car C"', '"Car D"'], $this->pooled('cars', ['sort' => 'title:desc', 'limit' => 2], 'title'));
        $this->assertSame(['"Car B"', '"Car C"'], $this->pooled('cars', ['sort' => 'title:asc', 'limit' => 2, 'offset' => 1], 'title'));
        $this->assertSame(['"Car Draft"'], $this->pooled('cars', ['status:is' => 'draft'], 'title'));
        $this->assertSame(['"Past"'], $this->pooled('news', [], 'title'));
        $this->assertSame(['"Future"', '"Past"'], $this->pooled('news', ['status:is' => 'any'], 'title'));
        $this->assertSame(['"Car A"', '"Car D"', '"Truck A"'], $this->pooled('cars|trucks', ['taxonomy:brands:any' => 'toyota'], 'title'));
        $this->assertSame(['"automatic"', '"manual"', '"manual"'], $this->pooled('cars', ['query_scope' => 'multiselect', 'multiselect:color' => 'red|green'], 'transmission'));
        $this->assertSame([], $this->pooled('news', ['show_future' => false, 'show_past' => false], 'title'));
        $this->assertContains('null', $this->pooled('cars', [], 'seats'));
        $this->assertCount(6, $this->pooled('cars|trucks', [], 'title'));
    }

    #[Test]
    public function resolved_keys_are_collection_prefixed_for_every_queried_collection()
    {
        $keys = (new CountEntries(Parameters::make(['from' => 'cars|trucks'], Context::make([]))))->resolvedKeys();

        $this->assertSame(
            ['cars::car-a', 'cars::car-b', 'cars::car-c', 'cars::car-d', 'trucks::truck-a', 'trucks::truck-b'],
            $keys->sort()->values()->all()
        );

        $single = (new CountEntries(Parameters::make(['from' => 'trucks'], Context::make([]))))->resolvedKeys();
        $this->assertSame(['trucks::truck-a', 'trucks::truck-b'], $single->sort()->values()->all());

        $none = (new CountEntries(Parameters::make(['from' => 'news', 'show_future' => false, 'show_past' => false], Context::make([]))))->resolvedKeys();
        $this->assertTrue($none->isEmpty());
        $this->assertTrue(CountEntries::valuesForKeys($none, 'title')->isEmpty());
    }

    #[Test]
    public function numeric_entry_ids_resolve_through_the_key_set()
    {
        Facades\Collection::make('nums')->save();
        $this->vehicleBlueprint()->setHandle('nums')->setNamespace('collections.nums')->save();
        $this->vehicle('nums', '100', ['title' => 'Hundred', 'seats' => 1]);
        $this->vehicle('nums', '200', ['title' => 'Two Hundred', 'seats' => 2]);

        $this->assertSame(['nums::100', 'nums::200'], (new CountEntries(Parameters::make(['from' => 'nums'], Context::make([]))))->resolvedKeys()->sort()->values()->all());
        $this->assertSame($this->reference(['from' => 'nums'], 'seats'), $this->pooled('nums', [], 'seats'));
        $this->assertSame(['1', '2'], $this->pooled('nums', [], 'seats'));
        $this->assertSame($this->reference(['from' => 'nums|cars'], 'title'), $this->pooled('nums|cars', [], 'title'));
    }

    #[Test]
    public function localized_entries_keep_inherited_values_across_sites()
    {
        Config::set('statamic.system.multisite', true);
        Facades\Site::setSites([
            'en' => ['name' => 'English', 'url' => 'http://localhost/', 'locale' => 'en_US'],
            'fr' => ['name' => 'French', 'url' => 'http://localhost/fr/', 'locale' => 'fr_FR'],
        ]);
        Facades\Collection::make('bikes')->sites(['en', 'fr'])->save();
        $this->vehicleBlueprint()->setHandle('bikes')->setNamespace('collections.bikes')->save();

        EntryFactory::id('bike-a')->collection('bikes')->slug('bike-a')->data(['title' => 'Bike A', 'color' => ['red']])->create();
        EntryFactory::id('bike-a-fr')->collection('bikes')->slug('bike-a-fr')->locale('fr')->origin('bike-a')->data(['title' => 'Velo A'])->create();
        EntryFactory::id('bike-b')->collection('bikes')->slug('bike-b')->data(['title' => 'Bike B', 'color' => ['blue']])->create();
        EntryFactory::id('bike-b-fr')->collection('bikes')->slug('bike-b-fr')->locale('fr')->origin('bike-b')->data(['title' => 'Velo B', 'color' => ['green']])->create();
        EntryFactory::id('bike-c')->collection('bikes')->slug('bike-c')->data(['title' => 'Bike C', 'color' => ['white']])->create();

        $this->assertSame(['["green"]', '["red"]'], $this->pooled('bikes', ['site' => 'fr'], 'color'), 'fr inherits red from its origin');

        foreach ([['site' => 'fr'], ['site' => 'en'], ['site' => '*'], []] as $params) {
            $this->assertSame($this->reference(['from' => 'bikes'] + $params, 'color'), $this->pooled('bikes', $params, 'color'));
        }

        Facades\Site::setCurrent('fr');
        $this->app->forgetInstance(CountQueryPool::class);
        $this->assertSame(['["green"]', '["red"]'], $this->pooled('bikes', [], 'color'));
    }

    #[Test]
    public function resolved_keys_are_not_attempted_on_an_eloquent_query()
    {
        $params = Parameters::make(['from' => 'cars'], Context::make([]));
        $builder = Mockery::mock(EloquentQueryBuilder::class);
        $builder->shouldReceive('reorder')->once()->withNoArgs()->andReturnSelf();
        $builder->shouldNotReceive('pluck');
        $builder->shouldNotReceive('get');

        $countEntries = Mockery::mock(CountEntries::class, [$params])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $countEntries->shouldReceive('query')->once()->andReturn($builder);

        $this->assertNull($countEntries->resolvedKeys());
    }

    protected function withFakeQueries(callable $callback): void
    {
        $this->stacheQueries = 0;
        Config::set('statamic.system.fake_sql_queries', true);

        try {
            $callback();
        } finally {
            Config::set('statamic.system.fake_sql_queries', false);
        }
    }

    /**
     * Independent oracle: hydrate the entries through Statamic's own tag class and
     * resolve each value the way the Stache indexes do.
     */
    protected function reference(array $params, string $field): array
    {
        $entries = (new StatamicEntries(Parameters::make($params, Context::make([]))))->get();
        $resolver = new ResolveValue;

        return $this->normalize($entries->map(fn ($entry) => $resolver($entry, $field)));
    }

    protected function pooled(string|array $collection, array $baseParams, string $field): array
    {
        return $this->normalize(app(CountQueryPool::class)->getFieldValues($collection, $baseParams, $field));
    }

    protected function normalize($values): array
    {
        return collect($values)->map(fn ($value) => json_encode($value))->sort()->values()->all();
    }

    protected function vehicleBlueprint()
    {
        return Facades\Blueprint::make()->setContents(['sections' => ['main' => ['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'color', 'field' => ['type' => 'checkboxes', 'options' => ['red' => 'Red', 'blue' => 'Blue', 'green' => 'Green', 'white' => 'White']]],
            ['handle' => 'transmission', 'field' => ['type' => 'select', 'options' => ['manual' => 'Manual', 'automatic' => 'Automatic', 'semi' => 'Semi']]],
            ['handle' => 'seats', 'field' => ['type' => 'integer']],
            ['handle' => 'year', 'field' => ['type' => 'date']],
            ['handle' => 'brands', 'field' => ['type' => 'terms', 'taxonomies' => ['brands']]],
        ]]]]);
    }

    protected function vehicle(string $collection, string $id, array $data, bool $published = true): void
    {
        EntryFactory::id($id)->collection($collection)->slug($id)->data($data)->published($published)->create();
    }
}
