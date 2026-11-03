<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\FileStore;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection as LivewireCollectionComponent;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;
use Statamic\Facades\Blink;
use Statamic\Tags\Collection\Entries;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;
use Statamic\View\Antlers\Language\Runtime\RuntimeParser;

/**
 * Cache store decorator so we can count how many Stache index loads and entry
 * hydrations each simulated request performs.
 */
class BenchCountingCacheStore implements LockProvider, Store
{
    public array $counts = ['index_get' => 0, 'index_miss' => 0, 'item_get' => 0, 'other_get' => 0];

    public array $missed = [];

    public function __construct(public Store $inner) {}

    public function reset(): void
    {
        foreach ($this->counts as $k => $v) {
            $this->counts[$k] = 0;
        }
        $this->missed = [];
    }

    public function get($key)
    {
        $value = $this->inner->get($key);

        if (str_starts_with($key, 'stache::indexes::')) {
            $this->counts['index_get']++;
            if ($value === null) {
                $this->counts['index_miss']++;
                $this->missed[] = substr($key, strlen('stache::indexes::'));
            }
        } elseif (str_starts_with($key, 'stache::items::')) {
            $this->counts['item_get']++;
        } else {
            $this->counts['other_get']++;
        }

        return $value;
    }

    public function many(array $keys)
    {
        return $this->inner->many($keys);
    }

    public function put($key, $value, $seconds)
    {
        return $this->inner->put($key, $value, $seconds);
    }

    public function putMany(array $values, $seconds)
    {
        return $this->inner->putMany($values, $seconds);
    }

    public function increment($key, $value = 1)
    {
        return $this->inner->increment($key, $value);
    }

    public function decrement($key, $value = 1)
    {
        return $this->inner->decrement($key, $value);
    }

    public function forever($key, $value)
    {
        return $this->inner->forever($key, $value);
    }

    public function forget($key)
    {
        return $this->inner->forget($key);
    }

    public function flush()
    {
        return $this->inner->flush();
    }

    public function getPrefix()
    {
        return $this->inner->getPrefix();
    }

    public function lock($name, $seconds = 0, $owner = null)
    {
        return $this->inner->lock($name, $seconds, $owner);
    }

    public function restoreLock($name, $owner)
    {
        return $this->inner->restoreLock($name, $owner);
    }
}

/**
 * Same component, with the phases of entries() timed so a per-request
 * breakdown is available next to the wall-clock total of the request.
 */
class BenchCollection extends LivewireCollectionComponent
{
    public static array $phases = [];

    public static function mark(string $phase, int $start): void
    {
        static::$phases[$phase] = (static::$phases[$phase] ?? 0) + (hrtime(true) - $start) / 1e6;
    }

    public function entries()
    {
        if (getenv('BENCH_PHASES') !== '1') {
            $s = hrtime(true);
            $result = parent::entries();
            static::mark('entries() total', $s);

            return $result;
        }

        $s = hrtime(true);
        $params = $this->generateParams();
        static::mark('generateParams', $s);

        $s = hrtime(true);
        $tag = new Entries($params);
        static::mark('Entries::__construct', $s);

        $s = hrtime(true);
        $entries = $tag->get();
        static::mark('Entries::get (query+paginate)', $s);

        $s = hrtime(true);
        $entries = $this->runHooks('livewire-fetched-entries', $entries);
        static::mark('runHooks', $s);

        $s = hrtime(true);
        $this->updateCustomQueryStringUrl();
        static::mark('updateCustomQueryStringUrl', $s);

        if ($this->paginate && $entries instanceof LengthAwarePaginator) {
            $s = hrtime(true);
            $result = $this->withPagination('entries', $entries);
            static::mark('withPagination (links render)', $s);

            return $result;
        }

        return ['entries' => $entries];
    }
}

class CollectionBenchTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    protected int $n;

    public static ?BenchCountingCacheStore $spy = null;

    protected array $rows = [];

    protected string $resultsFile;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('cache.stores.bench', ['driver' => 'bench']);
        $app['config']->set('statamic.stache.cache_store', 'bench');
        $app['config']->set('statamic.stache.watcher', false);

        $register = function ($manager) {
            $manager->extend('bench', function ($app, $config) use ($manager) {
                $mode = getenv('BENCH_CACHE') ?: 'array';
                $inner = $mode === 'file'
                    ? new FileStore($app['files'], __DIR__.'/../../.bench-cache')
                    : new ArrayStore(false);

                CollectionBenchTest::$spy = new BenchCountingCacheStore($inner);

                return $manager->repository(CollectionBenchTest::$spy, $config);
            });
        };

        if ($app->resolved('cache')) {
            $register($app['cache']);
        } else {
            $app->resolving('cache', $register);
        }
    }

    protected function setUp(): void
    {
        $mode = getenv('BENCH_CACHE') ?: 'array';
        $this->fakeStacheDirectory = __DIR__.'/../__fixtures__/dev-null-bench-'.$mode;
        if (! is_dir($this->fakeStacheDirectory)) {
            mkdir($this->fakeStacheDirectory, 0777, true);
        }

        parent::setUp();

        $this->n = (int) (getenv('BENCH_N') ?: 3000);
        $this->resultsFile = __DIR__.'/../../.bench-results-'.$mode.'-'.($this->name()).'.txt';
        @unlink($this->resultsFile);

        Livewire::component('bench-collection', BenchCollection::class);

        app('cache')->store('bench');

        if (static::$spy->inner instanceof FileStore) {
            static::$spy->flush();
        }

        $this->buildContent();
    }

    protected function buildContent(): void
    {
        $start = hrtime(true);

        Facades\Taxonomy::make('tags')->save();
        for ($i = 1; $i <= 10; $i++) {
            Facades\Term::make()->taxonomy('tags')->inDefaultLocale()->slug("tag-$i")->data(['title' => "Tag $i"])->save();
        }

        Facades\Collection::make('products')->taxonomies(['tags'])->save();

        $blueprint = Facades\Blueprint::make()->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        ['handle' => 'title', 'field' => ['type' => 'text']],
                        ['handle' => 'brand', 'field' => ['type' => 'select', 'options' => array_combine(
                            array_map(fn ($i) => "brand-$i", range(1, 20)),
                            array_map(fn ($i) => "Brand $i", range(1, 20)),
                        )]],
                        ['handle' => 'category', 'field' => ['type' => 'select', 'options' => array_combine(
                            array_map(fn ($i) => "cat-$i", range(1, 8)),
                            array_map(fn ($i) => "Category $i", range(1, 8)),
                        )]],
                        ['handle' => 'colors', 'field' => ['type' => 'checkboxes', 'options' => [
                            'red' => 'Red', 'blue' => 'Blue', 'green' => 'Green', 'black' => 'Black', 'white' => 'White', 'yellow' => 'Yellow',
                        ]]],
                        ['handle' => 'price', 'field' => ['type' => 'integer']],
                        ['handle' => 'tags', 'field' => ['type' => 'terms', 'taxonomies' => ['tags']]],
                    ],
                ],
            ],
        ]);
        $blueprint->setHandle('product')->setNamespace('collections.products')->save();

        $colors = ['red', 'blue', 'green', 'black', 'white', 'yellow'];
        mt_srand(42);

        for ($i = 1; $i <= $this->n; $i++) {
            $entryColors = [];
            foreach ($colors as $c) {
                if (mt_rand(0, 2) === 0) {
                    $entryColors[] = $c;
                }
            }
            if ($entryColors === []) {
                $entryColors[] = $colors[$i % 6];
            }

            EntryFactory::collection('products')->slug("product-$i")->data([
                'title' => "Product $i",
                'brand' => 'brand-'.(($i % 20) + 1),
                'category' => 'cat-'.(($i % 8) + 1),
                'colors' => $entryColors,
                'price' => mt_rand(100, 5000),
                'tags' => ['tag-'.(($i % 10) + 1), 'tag-'.((($i * 7) % 10) + 1)],
            ])->create();
        }

        $this->log(sprintf('[setup] built %d entries in %.0f ms (cache=%s)', $this->n, (hrtime(true) - $start) / 1e6, getenv('BENCH_CACHE') ?: 'array'));
    }

    protected function log(string $line): void
    {
        fwrite(STDERR, $line."\n");
        file_put_contents($this->resultsFile, $line."\n", FILE_APPEND);
    }

    /**
     * Emulate a fresh PHP-FPM request: Blink caches gone, Stache index objects
     * re-resolved (so each index is re-read from the cache store), Antlers
     * node cache empty.
     */
    protected function requestBoundary(): void
    {
        Blink::flush();
        Blink::store('entry-uris')->flush();
        Blink::store('structure-entries')->flush();
        app()->instance('stache.indexes', collect());
        RuntimeParser::clearRenderNodeCache();
        static::$spy->reset();
        BenchCollection::$phases = [];
    }

    protected function step(string $name, callable $fn, ?int $expectedEntries = null): mixed
    {
        $this->requestBoundary();

        $start = hrtime(true);
        $component = $fn();
        $ms = (hrtime(true) - $start) / 1e6;

        $html = $component->html();
        $snapshot = json_encode($component->snapshot);
        $phases = BenchCollection::$phases;

        $this->rows[] = [
            'step' => $name,
            'ms' => $ms,
            'index_get' => static::$spy->counts['index_get'],
            'item_get' => static::$spy->counts['item_get'],
            'html_bytes' => strlen($html),
            'snapshot_bytes' => strlen($snapshot),
            'entries_in_html' => substr_count($html, 'wire:key="'),
            'phases' => $phases,
        ];

        $rendered = preg_match_all('/<div wire:key="(?!lf-page-)[^"]+"/', $html);

        $this->log(sprintf(
            '%-44s %8.1f ms | idx loads %3d (built %2d) | item gets %4d | html %7d B | snapshot %5d B | entries rendered %3d | phases: %s%s',
            $name,
            $ms,
            static::$spy->counts['index_get'],
            static::$spy->counts['index_miss'],
            static::$spy->counts['item_get'],
            strlen($html),
            strlen($snapshot),
            $rendered,
            collect($phases)->map(fn ($v, $k) => sprintf('%s=%.1f', $k, $v))->implode(', '),
            static::$spy->missed ? ' | built: '.implode(',', static::$spy->missed) : '',
        ));

        if (! file_exists(__DIR__.'/../../.bench-sample.html')) {
            file_put_contents(__DIR__.'/../../.bench-sample.html', $html);
        }

        if ($expectedEntries !== null && $expectedEntries !== $rendered) {
            $this->log("  !! expected $expectedEntries rendered entries, counted $rendered");
        }

        return $component;
    }

    #[Test]
    public function it_benchmarks_infinite_scroll_load_more_growth()
    {
        foreach (['cold (indexes built on demand)', 'warm (steady state)'] as $pass) {
            $this->log("--- Infinite scroll, paginate=12, N={$this->n} — $pass ---");

            // BENCH_INFINITE_SCROLL=append benchmarks the append mode (Plan 014); default is the page-growing mode.
            $mode = getenv('BENCH_INFINITE_SCROLL') ?: true;
            $append = $mode === 'append';

            $component = $this->step('mount+render (infinite'.($append ? ', append' : '').')', fn () => Livewire::test('bench-collection', [
                'params' => ['from' => 'products', 'paginate' => 12, 'infinite_scroll' => $mode],
            ]), 12);

            for ($i = 1; $i <= 5; $i++) {
                $this->step("loadMore #$i", fn () => $component->call('loadMore'), $append ? 12 : 12 * ($i + 1));
            }

            $this->step('filter-updated brand:is after 5 loads', fn () => $component->dispatch('filter-updated',
                field: 'brand', condition: 'is', payload: 'brand-3', modifier: 'any'), 12);
        }

        $this->assertTrue(true);
    }

    #[Test]
    public function it_benchmarks_the_multiselect_scope()
    {
        $this->log('--- Multiselect scope via the real Entries tag, N='.$this->n.' ---');

        $make = fn (array $params) => new class(Parameters::make($params, Context::make([]))) extends Entries
        {
            public function exposedQuery()
            {
                return $this->query();
            }
        };

        $time = function (string $label, callable $fn, int $reps = 5) {
            $this->requestBoundary();
            $fn();
            $start = hrtime(true);
            for ($i = 0; $i < $reps; $i++) {
                $fn();
            }
            $ms = (hrtime(true) - $start) / 1e6 / $reps;
            $this->log(sprintf('%-60s %9.3f ms/op', $label, $ms));

            return $ms;
        };

        foreach ([1, 3, 6] as $k) {
            $values = implode('|', array_slice(['red', 'blue', 'green', 'black', 'white', 'yellow'], 0, $k));
            $ms = $make(['from' => 'products', 'query_scope' => 'multiselect', 'multiselect:colors' => $values]);
            $time("multiselect x$k: paginate(12)", fn () => $ms->exposedQuery()->paginate(12));
            $time("multiselect x$k: count()", fn () => $ms->exposedQuery()->count());
            $this->log(sprintf('  (count = %d)', $ms->exposedQuery()->count()));
        }

        $this->assertTrue(true);
    }
}
