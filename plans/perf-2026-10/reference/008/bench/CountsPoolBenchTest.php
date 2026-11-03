<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Carbon\Carbon;
use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfCheckboxFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfDualRangeFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfRadioFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfRangeFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;
use Reach\StatamicLivewireFilters\Support\CountEntries;
use Reach\StatamicLivewireFilters\Support\CountQueryPool;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;

/**
 * Pool spy that only wraps the public entry point, so it measures the same thing
 * on the pristine and on the patched copy regardless of the pool's internals.
 */
class TimingPool extends CountQueryPool
{
    public static int $calls = 0;

    public static float $ms = 0;

    public static array $log = [];

    public static function reset(): void
    {
        static::$calls = 0;
        static::$ms = 0;
        static::$log = [];
    }

    public function getFieldValues(string|array $collection, array $baseParams, string $fieldHandle): Collection
    {
        $t = hrtime(true);
        $result = parent::getFieldValues($collection, $baseParams, $fieldHandle);
        $ms = (hrtime(true) - $t) / 1e6;
        static::$calls++;
        static::$ms += $ms;
        static::$log[] = sprintf('%-14s %7.2fms  collection=%s hasFrom=%s params=%s', $fieldHandle, $ms, json_encode($collection), isset($baseParams['from']) ? 'y' : 'n', json_encode(collect($baseParams)->except('from')->all()));

        return $result;
    }
}

/**
 * Bench for Plan 008: one params-updated interaction fanned out to 8 listeners
 * (6 count filters + 2 range filters), N entries in the Stache.
 * Run: BENCH_N=5000 vendor/bin/phpunit tests/Bench/CountsPoolBenchTest.php 2> bench-out.txt
 */
class CountsPoolBenchTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    protected int $n;

    protected $collection;

    protected array $out = [];

    protected int $queries = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->n = (int) (getenv('BENCH_N') ?: 5000);

        $t = hrtime(true);
        $this->collection = Facades\Collection::make('cars')->save();
        $opts = fn (array $keys) => array_map(fn ($k) => ['key' => $k, 'value' => ucfirst($k)], $keys);
        $blueprint = Facades\Blueprint::make()->setContents(['sections' => ['main' => ['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'transmission', 'field' => ['type' => 'select', 'options' => $opts(['manual', 'automatic', 'semi'])]],
            ['handle' => 'fuel_type', 'field' => ['type' => 'select', 'options' => $opts(['petrol', 'diesel', 'hybrid', 'electric'])]],
            ['handle' => 'air_condition', 'field' => ['type' => 'radio', 'options' => $opts(['none', 'manual', 'auto'])]],
            ['handle' => 'body_type', 'field' => ['type' => 'select', 'options' => $opts(['sedan', 'suv', 'hatch', 'coupe', 'wagon', 'van'])]],
            ['handle' => 'drive', 'field' => ['type' => 'radio', 'options' => $opts(['fwd', 'rwd', 'awd'])]],
            ['handle' => 'color', 'field' => ['type' => 'checkboxes', 'options' => $opts(['red', 'blue', 'black', 'white', 'silver', 'green', 'grey', 'yellow'])]],
            ['handle' => 'seats', 'field' => ['type' => 'integer']],
            ['handle' => 'year', 'field' => ['type' => 'date']],
        ]]]]);
        $blueprint->setHandle('car')->setNamespace('collections.cars')->save();

        mt_srand(42);
        $pick = fn (array $a) => $a[mt_rand(0, count($a) - 1)];
        $colors = ['red', 'blue', 'black', 'white', 'silver', 'green', 'grey', 'yellow'];
        for ($i = 0; $i < $this->n; $i++) {
            $c = [$pick($colors)];
            if (mt_rand(0, 2) === 0) {
                $c[] = $pick($colors);
            }
            EntryFactory::id("car-{$i}")->collection($this->collection)->slug("car-{$i}")->data([
                'title' => "Car {$i}",
                'transmission' => $pick(['manual', 'automatic', 'semi']),
                'fuel_type' => $pick(['petrol', 'diesel', 'hybrid', 'electric']),
                'air_condition' => $pick(['none', 'manual', 'auto']),
                'body_type' => $pick(['sedan', 'suv', 'hatch', 'coupe', 'wagon', 'van']),
                'drive' => $pick(['fwd', 'rwd', 'awd']),
                'color' => array_values(array_unique($c)),
                'seats' => mt_rand(2, 9),
                'year' => sprintf('%d-%02d-%02d', mt_rand(2010, 2025), mt_rand(1, 12), mt_rand(1, 28)),
            ])->create();
        }
        $this->out[] = sprintf('setUp: N=%d entries in %.0f ms  (src=%s)', $this->n, (hrtime(true) - $t) / 1e6, realpath(__DIR__.'/../../src'));

        Event::listen(QueryExecuted::class, function () {
            $this->queries++;
        });
    }

    protected function tearDown(): void
    {
        fwrite(STDERR, "\n".implode("\n", $this->out)."\n");
        parent::tearDown();
    }

    protected function filterArgs(): array
    {
        return [
            'color' => [LfCheckboxFilter::class, ['field' => 'color', 'blueprint' => 'cars.car', 'condition' => 'query_scope', 'modifier' => 'multiselect']],
            'transmission' => [LfRadioFilter::class, ['field' => 'transmission', 'blueprint' => 'cars.car', 'condition' => 'is']],
            'fuel_type' => [LfRadioFilter::class, ['field' => 'fuel_type', 'blueprint' => 'cars.car', 'condition' => 'is']],
            'air_condition' => [LfRadioFilter::class, ['field' => 'air_condition', 'blueprint' => 'cars.car', 'condition' => 'is']],
            'body_type' => [LfCheckboxFilter::class, ['field' => 'body_type', 'blueprint' => 'cars.car', 'condition' => 'in']],
            'drive' => [LfRadioFilter::class, ['field' => 'drive', 'blueprint' => 'cars.car', 'condition' => 'is']],
            'seats' => [LfRangeFilter::class, ['field' => 'seats', 'blueprint' => 'cars.car', 'condition' => 'gte', 'min' => 2, 'max' => 9]],
            'year' => [LfDualRangeFilter::class, ['field' => 'year', 'blueprint' => 'cars.car', 'condition' => 'dual_range', 'min' => 2010, 'max' => 2025, 'minRange' => 1]],
        ];
    }

    protected function payloadAfter(array $interactions): array
    {
        $collection = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'cars']]);
        foreach ($interactions as [$field, $condition, $payload, $modifier]) {
            $collection->dispatch('filter-updated', field: $field, condition: $condition, payload: $payload, modifier: $modifier);
        }

        return collect($collection->effects['dispatches'])->last(fn ($e) => $e['name'] === 'params-updated')['params'];
    }

    protected function freshPool(): void
    {
        $this->app->forgetInstance(CountQueryPool::class);
        $this->app->scoped(CountQueryPool::class, TimingPool::class);
        TimingPool::reset();
    }

    /** One request: every listener handles the same params-updated. Pass 1 counts Stache queries (untimed), pass 2 is timed. */
    protected function interaction(string $label, array $payload, array $filters): array
    {
        $this->freshPool();
        Config::set('statamic.system.fake_sql_queries', true);
        $this->queries = 0;
        foreach ($filters as $f) {
            $f->dispatch('params-updated', ...$payload);
        }
        $queries = $this->queries;
        Config::set('statamic.system.fake_sql_queries', false);

        $this->freshPool();
        $t = hrtime(true);
        foreach ($filters as $f) {
            $f->dispatch('params-updated', ...$payload);
        }
        $total = (hrtime(true) - $t) / 1e6;
        $this->out[] = sprintf('%-44s total=%7.1fms  poolCalls=%d  poolMs=%6.1f  stacheQueries=%d', $label, $total, TimingPool::$calls, TimingPool::$ms, $queries);

        return ['total' => $total, 'poolMs' => TimingPool::$ms, 'queries' => $queries, 'log' => TimingPool::$log];
    }

    protected function snapshot(array $filters): string
    {
        $s = [];
        foreach ($filters as $name => $f) {
            $field = $f->get('statamic_field');
            $s[$name] = isset($field['counts']) ? $field['counts'] : [
                'dynamicMin' => $f->get('dynamicMin'), 'dynamicMax' => $f->get('dynamicMax'),
                'fieldMin' => $f->get('fieldMin'), 'fieldMax' => $f->get('fieldMax'), 'disabled' => $f->get('boundsDisabled'),
            ];
        }

        return json_encode($s);
    }

    #[Test]
    public function one_interaction_with_counts_and_bounds()
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', true);
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        $filters = [];
        foreach ($this->filterArgs() as $name => [$class, $args]) {
            $filters[$name] = Livewire::test($class, $args);
        }

        // Warm every Stache index a request would find cached in production.
        foreach (['color', 'transmission', 'fuel_type', 'air_condition', 'body_type', 'drive', 'seats', 'year', 'title', 'published', 'collectionHandle', 'id', 'site', 'slug'] as $f) {
            Facades\Entry::query()->where('collection', 'cars')->pluck($f);
        }

        $p0 = $this->payloadAfter([]);
        $p1 = $this->payloadAfter([['color', 'query_scope', ['red'], 'multiselect']]);
        $p2 = $this->payloadAfter([['color', 'query_scope', ['red'], 'multiselect'], ['transmission', 'is', 'manual', 'any']]);
        $this->out[] = 'p1 params='.json_encode($p1[0]).' token_bytes='.strlen($p1[1] ?? '');

        Config::set('statamic-livewire-filters.enable_filter_values_count', false);
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', false);
        $this->interaction('OFF warmup', $p1, $filters);
        $this->interaction('OFF (Livewire-only, 8 listeners) rep1', $p1, $filters);
        $this->interaction('OFF (Livewire-only, 8 listeners) rep2', $p1, $filters);
        Config::set('statamic-livewire-filters.enable_filter_values_count', true);
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        $this->interaction('ON warmup', $p1, $filters);
        $snap = [];
        foreach ([1, 2] as $rep) {
            $this->interaction("ON p0 (mount, 0 active) rep{$rep}", $p0, $filters);
            $snap['p0'] = $this->snapshot($filters);
            $r1 = $this->interaction("ON p1 (color=red) rep{$rep}", $p1, $filters);
            $snap['p1'] = $this->snapshot($filters);
            $this->interaction("ON p2 (color=red + transmission=manual) rep{$rep}", $p2, $filters);
            $snap['p2'] = $this->snapshot($filters);
        }
        $this->out[] = '  pool call log (p1):';
        foreach ($r1['log'] as $l) {
            $this->out[] = '    '.$l;
        }
        foreach ($snap as $k => $json) {
            $this->out[] = "RESULT {$k} ".$json;
        }

        // ---- C2: sort cost in isolation (copy-independent: raw Stache builder) ----
        $sorted = $this->avg(fn () => Facades\Entry::query()->where('collection', 'cars')->whereStatus('published')->orderBy('title')->pluck('transmission'));
        $unsorted = $this->avg(fn () => Facades\Entry::query()->where('collection', 'cars')->whereStatus('published')->reorder()->pluck('transmission'));
        $keysOnly = $this->avg(fn () => Facades\Entry::query()->where('collection', 'cars')->whereStatus('published')->reorder()->pluck('id'));
        $this->out[] = sprintf('C2 raw Stache pluck(transmission) N=%d: orderBy(title)=%.1fms  reorder()=%.1fms  reorder()->pluck(id)=%.1fms', $this->n, $sorted, $unsorted, $keysOnly);
        $cur0 = $this->avg(fn () => (new CountEntries(Parameters::make(['from' => 'cars'], Context::make([]))))->pluck('transmission'));
        $cur1 = $this->avg(fn () => (new CountEntries(Parameters::make(['from' => 'cars', 'query_scope' => 'multiselect', 'multiselect:color' => 'red'], Context::make([]))))->pluck('transmission'));
        $this->out[] = sprintf('C2 this copy CountEntries::pluck(transmission): no filter=%.1fms  color=red=%.1fms', $cur0, $cur1);

        // ---- C4: date values straight from the index ----
        $years = Facades\Entry::query()->where('collection', 'cars')->reorder()->pluck('year');
        $first = $years->first();
        $t = hrtime(true);
        $a = $years->map(fn ($v) => Carbon::parse($v)->timestamp);
        $parseMs = (hrtime(true) - $t) / 1e6;
        $t = hrtime(true);
        $b = $years->map(fn ($v) => $v instanceof \DateTimeInterface ? $v->getTimestamp() : Carbon::parse($v)->timestamp);
        $tsMs = (hrtime(true) - $t) / 1e6;
        $this->assertSame([$a->min(), $a->max()], [$b->min(), $b->max()]);
        $this->out[] = sprintf('C4 index value type=%s; N=%d Carbon::parse loop=%.1fms  getTimestamp loop=%.1fms  (min/max identical: %s)', is_object($first) ? get_class($first) : gettype($first), $years->count(), $parseMs, $tsMs, $a->min() === $b->min() && $a->max() === $b->max() ? 'yes' : 'NO');
        $this->out[] = sprintf('peak memory=%.0f MB', memory_get_peak_usage(true) / 1048576);
    }

    protected function avg(\Closure $fn, int $reps = 5): float
    {
        $fn();
        $t = hrtime(true);
        for ($i = 0; $i < $reps; $i++) {
            $fn();
        }

        return (hrtime(true) - $t) / $reps / 1e6;
    }
}
