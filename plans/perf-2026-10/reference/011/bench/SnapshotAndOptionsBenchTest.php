<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Composer\InstalledVersions;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfCheckboxFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfDualRangeFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfRadioFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfRangeFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfSelectFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfTags;
use Reach\StatamicLivewireFilters\Http\Livewire\LfToggleFilter;
use Reach\StatamicLivewireFilters\Support\FieldOptionsCache;
use Reach\StatamicLivewireFilters\Support\RangeBoundsQuery;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;

/**
 * Independent verification bench for findings F2 (null counts in snapshot), F3 (#[Computed(persist)])
 * and F4 (persistent field-options cache). 500 terms, 1,000 entries, file cache driver (so the Stache
 * and every cache hit pay real file + unserialize cost, like the demo site with CACHE_STORE=file).
 *
 * Run:  BENCH_TAG=BEFORE vendor/bin/phpunit tests/Bench/SnapshotAndOptionsBenchTest.php
 * Output is appended to $BENCH_RESULTS (default: bench-results.txt next to this file).
 */
class SnapshotAndOptionsBenchTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    public const TERMS = 500;

    public const ENTRIES = 1000;

    protected string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheDir = sys_get_temp_dir().'/v1-bench-cache-'.getmypid();
        $driver = $this->name() === 'f3_persist_cache_traffic_array_driver' ? 'array' : 'file';
        Config::set('cache.stores.benchstore', $driver === 'file'
            ? ['driver' => 'file', 'path' => $this->cacheDir]
            : ['driver' => 'array', 'serialize' => true]);
        Config::set('cache.default', 'benchstore');
        Cache::store('benchstore')->flush();

        $t = hrtime(true);

        Taxonomy::make('brands')->save();
        for ($i = 1; $i <= self::TERMS; $i++) {
            Facades\Term::make()->taxonomy('brands')->inDefaultLocale()->slug("brand-$i")->data(['title' => "Brand $i"])->saveQuietly();
        }

        Facades\Collection::make('products')->taxonomies(['brands'])->save();

        $opts = fn (string $p) => collect(range(0, 4))->mapWithKeys(fn ($i) => ["{$p}{$i}" => ucfirst($p)." $i"])->all();

        Facades\Blueprint::make()->setContents([
            'sections' => ['main' => ['fields' => [
                ['handle' => 'title', 'field' => ['type' => 'text', 'display' => 'Title']],
                ['handle' => 'brands', 'field' => ['type' => 'terms', 'taxonomies' => ['brands'], 'display' => 'Brand']],
                ['handle' => 'related', 'field' => ['type' => 'entries', 'collections' => ['products'], 'display' => 'Related']],
                ['handle' => 'size', 'field' => ['type' => 'select', 'options' => $opts('size'), 'display' => 'Size', 'max_items' => 1]],
                ['handle' => 'color', 'field' => ['type' => 'radio', 'options' => $opts('color'), 'display' => 'Color']],
                ['handle' => 'tags', 'field' => ['type' => 'checkboxes', 'options' => $opts('tag'), 'display' => 'Tags']],
                ['handle' => 'price', 'field' => ['type' => 'integer', 'display' => 'Price']],
                ['handle' => 'year', 'field' => ['type' => 'date', 'display' => 'Year']],
            ]]],
        ])->setHandle('product')->setNamespace('collections.products')->save();

        for ($i = 1; $i <= self::ENTRIES; $i++) {
            Entry::make()->collection('products')->slug("product-$i")->data([
                'title' => "Product $i",
                'brands' => ['brand-'.(($i % self::TERMS) + 1)],
                'related' => [],
                'size' => 'size'.($i % 5),
                'color' => 'color'.($i % 5),
                'tags' => ['tag'.($i % 5)],
                'price' => $i,
                'year' => '20'.(10 + $i % 15).'-01-01',
            ])->saveQuietly();
        }

        // warm view compilation / autoload so the first measured mount is not paying for it
        Livewire::test(LfSelectFilter::class, ['blueprint' => 'products.product', 'field' => 'size', 'condition' => 'is']);
        Blink::flush();

        $this->log(sprintf('[setup] %s driver=%s terms=%d entries=%d livewire=%s statamic=%s setup=%.0fms',
            $this->name(), $driver, self::TERMS, self::ENTRIES, InstalledVersions::getPrettyVersion('livewire/livewire'),
            InstalledVersions::getPrettyVersion('statamic/cms'), (hrtime(true) - $t) / 1e6));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->cacheDir)) {
            exec('rm -rf '.escapeshellarg($this->cacheDir));
        }
        parent::tearDown();
    }

    protected function log(string $line): void
    {
        $tag = getenv('BENCH_TAG') ?: 'untagged';
        $file = getenv('BENCH_RESULTS') ?: __DIR__.'/bench-results.txt';
        $line = "[$tag] $line";
        fwrite(STDERR, $line."\n");
        file_put_contents($file, $line."\n", FILE_APPEND);
    }

    protected function normalizeHtml(string $html, ?string $id = null): string
    {
        $html = preg_replace('/\s(wire:snapshot|wire:effects|wire:id)="[^"]*"/', '', $html);

        return $id ? str_replace($id, 'LWID', $html) : $html;
    }

    protected function ms(callable $fn, &$result = null): float
    {
        $t = hrtime(true);
        $result = $fn();

        return round((hrtime(true) - $t) / 1e6, 2);
    }

    protected function requestBytes(array $snapshot, array $updates = [], array $calls = []): int
    {
        return strlen(json_encode(['snapshot' => json_encode($snapshot), 'updates' => (object) $updates, 'calls' => $calls]));
    }

    protected function responseBytes($c): int
    {
        return strlen(json_encode(['snapshot' => json_encode($c->snapshot), 'effects' => $c->effects]));
    }

    protected function filtersForPage(): array
    {
        return [
            ['toggle year', LfToggleFilter::class, ['blueprint' => 'products.product', 'field' => 'year', 'condition' => 'is_after', 'preset_value' => '2020-01-01', 'label' => 'New']],
            ['checkbox terms(500) advanced', LfCheckboxFilter::class, ['blueprint' => 'products.product', 'field' => 'brands', 'condition' => 'taxonomy', 'view' => 'lf-checkbox-advanced', 'searchable' => true]],
            ['checkbox entries(1000)', LfCheckboxFilter::class, ['blueprint' => 'products.product', 'field' => 'related', 'condition' => 'is']],
            ['checkbox tags(5)', LfCheckboxFilter::class, ['blueprint' => 'products.product', 'field' => 'tags', 'condition' => 'is']],
            ['select size(5)', LfSelectFilter::class, ['blueprint' => 'products.product', 'field' => 'size', 'condition' => 'is']],
            ['radio color(5)', LfRadioFilter::class, ['blueprint' => 'products.product', 'field' => 'color', 'condition' => 'is']],
            ['dual-range year', LfDualRangeFilter::class, ['blueprint' => 'products.product', 'field' => 'year', 'min' => 2010, 'max' => 2025]],
            ['range price', LfRangeFilter::class, ['blueprint' => 'products.product', 'field' => 'price', 'condition' => 'gte', 'min' => 1, 'max' => 1000, 'default' => 1]],
        ];
    }

    protected function freshGeneration(): void
    {
        if (class_exists(FieldOptionsCache::class)) {
            Cache::forever(FieldOptionsCache::GENERATION_KEY, mt_rand(10000, 99999));
        }
    }

    protected function cacheFileCount(): int
    {
        if (! is_dir($this->cacheDir)) {
            return 0;
        }

        return (int) trim(shell_exec('find '.escapeshellarg($this->cacheDir).' -type f | wc -l'));
    }

    /* ------------------------------------------------------------------ F2 */

    #[Test]
    public function f2_snapshot_bytes_and_counts_shape(): void
    {
        $this->log('--- F2: snapshot bytes per filter, counts key present?, counts() value, html md5 (flag off/on) ---');
        $cases = [
            ['checkbox entries(1000)', LfCheckboxFilter::class, ['blueprint' => 'products.product', 'field' => 'related', 'condition' => 'is']],
            ['checkbox terms(500)', LfCheckboxFilter::class, ['blueprint' => 'products.product', 'field' => 'brands', 'condition' => 'taxonomy']],
            ['checkbox terms(500) adv', LfCheckboxFilter::class, ['blueprint' => 'products.product', 'field' => 'brands', 'condition' => 'taxonomy', 'view' => 'lf-checkbox-advanced', 'searchable' => true]],
            ['select size(5)', LfSelectFilter::class, ['blueprint' => 'products.product', 'field' => 'size', 'condition' => 'is']],
            ['select size(5) adv', LfSelectFilter::class, ['blueprint' => 'products.product', 'field' => 'size', 'condition' => 'is', 'view' => 'lf-select-advanced']],
            ['radio color(5)', LfRadioFilter::class, ['blueprint' => 'products.product', 'field' => 'color', 'condition' => 'is']],
            ['checkbox custom opts', LfCheckboxFilter::class, ['blueprint' => 'products.product', 'field' => 'tags', 'condition' => 'is', 'options' => ['a' => 'A', 'b' => 'B']]],
        ];

        foreach ([false, true] as $flag) {
            Config::set('statamic-livewire-filters.enable_filter_values_count', $flag);
            foreach ($cases as [$label, $class, $params]) {
                Blink::flush();
                $c = Livewire::test($class, $params);
                $snap = $c->snapshot;
                $sf = $snap['data']['statamic_field'][0] ?? $snap['data']['statamic_field'];
                $countsPresent = is_array($sf) && array_key_exists('counts', $sf);
                $countsBytes = $countsPresent ? strlen(json_encode(is_array($sf['counts'][0] ?? null) ? $sf['counts'][0] : $sf['counts'])) : 0;
                $inst = $c->instance();
                $expected = array_fill_keys(array_keys($inst->filterOptions()), null);
                $countsOk = $inst->counts() === $expected ? 'ok' : 'MISMATCH';
                $html = $this->normalizeHtml($c->html(), $c->id());
                $countsJs = preg_match('/counts: (JSON\.parse\(.*?\)|\{.*?\}|\[.*?\]),/s', $html, $m) ? md5($m[1]) : '-';
                $this->log(sprintf('flag=%-5s %-26s snapshot %6d B | statamic_field %6d B | counts key %s (%5d B) | counts()==nullfill: %s | html md5 %s | alpine counts md5 %s',
                    $flag ? 'on' : 'off', $label, strlen(json_encode($snap)), strlen(json_encode($sf)), $countsPresent ? 'yes' : 'no ', $countsBytes, $countsOk, substr(md5($html), 0, 10), substr($countsJs, 0, 10)));

                if ($flag) {
                    $c->dispatch('params-updated', ['from' => 'products']);
                    $payload = collect($c->effects['dispatches'] ?? [])->firstWhere('name', 'counts-updated')['params'] ?? null;
                    $sf2 = $c->snapshot['data']['statamic_field'][0] ?? $c->snapshot['data']['statamic_field'];
                    $this->log(sprintf('            after params-updated: counts-updated payload md5 %s | counts key %s | html md5 %s | sum(counts)=%d',
                        substr(md5(json_encode($payload)), 0, 10), array_key_exists('counts', $sf2) ? 'yes' : 'no', substr(md5($this->normalizeHtml($c->html(), $c->id())), 0, 10), array_sum(is_array($sf2['counts'][0] ?? null) ? $sf2['counts'][0] : ($sf2['counts'] ?? []))));
                }
            }
        }
        $this->assertTrue(true);
    }

    #[Test]
    public function f2_pooled_params_updated_request_bytes(): void
    {
        $this->log('--- F2: pooled params-updated round trip, 7 filters (request = snapshot+calls, response = snapshot+effects) ---');
        foreach ([false, true] as $flag) {
            Config::set('statamic-livewire-filters.enable_filter_values_count', $flag);
            Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', $flag);
            $req = $res = $ms = $n = 0;
            $per = [];
            foreach ($this->filtersForPage() as [$label, $class, $params]) {
                if ($class === LfToggleFilter::class) {
                    continue;
                }
                Blink::flush();
                $c = Livewire::test($class, $params);
                $snap = $c->snapshot;
                $p = ['from' => 'products', 'size:is' => 'size1'];
                $args = [$p];
                if (in_array($class, [LfRangeFilter::class, LfDualRangeFilter::class])) {
                    $args[] = RangeBoundsQuery::encode('products', $p, 'col', 1, []);
                }
                $ms += $this->ms(fn () => $c->dispatch('params-updated', ...$args));
                $r = $this->requestBytes($snap, [], [['method' => '__dispatch', 'params' => ['params-updated', $args]]]);
                $req += $r;
                $res += $this->responseBytes($c);
                $per[$label] = $r;
                $n++;
            }
            $this->log(sprintf('counts/bounds %-3s: %d filters | request %7d B | response %7d B | server %.1f ms | per-filter request: %s',
                $flag ? 'ON' : 'off', $n, $req, $res, $ms, json_encode($per)));
        }
        $this->assertTrue(true);
    }

    /* ------------------------------------------------------------------ F3 */

    #[Test]
    public function f3_persist_cache_traffic_array_driver(): void
    {
        $this->log('--- F3: #[Computed(persist)] cache traffic (array driver, event spy) ---');
        $writes = $hits = $misses = [];
        Event::listen(KeyWritten::class, function (KeyWritten $e) use (&$writes) {
            $writes[] = [$e->key, strlen(serialize($e->value))];
        });
        Event::listen(CacheHit::class, function (CacheHit $e) use (&$hits) {
            $hits[] = $e->key;
        });
        Event::listen(CacheMissed::class, function (CacheMissed $e) use (&$misses) {
            $misses[] = $e->key;
        });
        $lw = fn (array $arr) => array_values(array_filter($arr, fn ($x) => str_starts_with(is_array($x) ? $x[0] : $x, 'lw_computed')));

        // page view 1
        $components = [];
        foreach ($this->filtersForPage() as [$label, $class, $params]) {
            $components[$label] = Livewire::test($class, $params);
        }
        $w = $lw($writes);
        $this->log(sprintf('page view #1 (8 filters): lw_computed writes=%d (%d B) hits=%d misses=%d | keys: %s',
            count($w), array_sum(array_column($w, 1)), count($lw($hits)), count($lw($misses)), implode(', ', array_map(fn ($x) => $x[0].'='.$x[1].'B', $w))));

        // update request on the SAME component id (= later request from the same page)
        $writes = $hits = $misses = [];
        $id = Entry::query()->where('collection', 'products')->first()->id();
        $components['checkbox entries(1000)']->set('selected', [$id]);
        $this->log(sprintf('update (set selected) on entries(1000) checkbox, same component id: lw_computed writes=%d hits=%d misses=%d | hit keys: %s',
            count($lw($writes)), count($lw($hits)), count($lw($misses)), implode(',', array_unique($lw($hits)))));

        // $refresh: how many cache reads does ONE render do?
        $writes = $hits = $misses = [];
        $components['checkbox entries(1000)']->call('$refresh');
        $this->log(sprintf('one $refresh render of entries(1000) checkbox: lw_computed hits=%d misses=%d writes=%d', count($lw($hits)), count($lw($misses)), count($lw($writes))));

        // rules() path: does validation go through the cache?
        $writes = $hits = $misses = [];
        $components['checkbox entries(1000)']->instance()->rules();
        $this->log(sprintf('rules() direct call: lw_computed hits=%d misses=%d writes=%d (0/0/0 => method call bypasses the attribute cache)', count($lw($hits)), count($lw($misses)), count($lw($writes))));

        // page view 2 (new component ids): any reuse of page view 1 entries?
        $writes = $hits = $misses = [];
        foreach ($this->filtersForPage() as [$label, $class, $params]) {
            Livewire::test($class, $params);
        }
        $this->log(sprintf('page view #2 (new ids): lw_computed writes=%d hits=%d misses=%d (hits=0 => zero cross-page reuse)', count($lw($writes)), count($lw($hits)), count($lw($misses))));
        $this->assertTrue(true);
    }

    #[Test]
    public function f3_persist_file_driver_files_and_update_cost(): void
    {
        $this->log('--- F3: file driver: cache files written per page view, update cost with/without persisted read ---');
        $before = $this->cacheFileCount();
        $writes = [];
        Event::listen(KeyWritten::class, function (KeyWritten $e) use (&$writes) {
            if (str_starts_with($e->key, 'lw_computed')) {
                $writes[] = strlen(serialize($e->value));
            }
        });
        $components = [];
        $ms = $this->ms(function () use (&$components) {
            foreach ($this->filtersForPage() as [$label, $class, $params]) {
                $components[$label] = Livewire::test($class, $params);
            }
        });
        $this->log(sprintf('page view (8 filters) mount+render %.1f ms: cache files %d -> %d (+%d), lw_computed writes %d totalling %d B',
            $ms, $before, $this->cacheFileCount(), $this->cacheFileCount() - $before, count($writes), array_sum($writes)));

        $id = Entry::query()->where('collection', 'products')->first()->id();
        foreach (['checkbox entries(1000)', 'checkbox terms(500) advanced', 'select size(5)'] as $label) {
            $c = $components[$label];
            $val = $label === 'checkbox entries(1000)' ? [$id] : ($label === 'select size(5)' ? 'size1' : ['brand-1']);
            $c->set('selected', $val);
            $t = hrtime(true);
            for ($i = 0; $i < 10; $i++) {
                $c->set('selected', $val);
            }
            $this->log(sprintf('%-30s set(selected) avg over 10: %.2f ms', $label, (hrtime(true) - $t) / 1e6 / 10));
        }

        // 20 "page views" -> how many files pile up (file driver never prunes expired entries on its own)
        $before = $this->cacheFileCount();
        for ($v = 0; $v < 20; $v++) {
            foreach ($this->filtersForPage() as [$label, $class, $params]) {
                Livewire::test($class, $params);
            }
        }
        $this->log(sprintf('20 more page views: cache files +%d', $this->cacheFileCount() - $before));
        $this->assertTrue(true);
    }

    #[Test]
    public function f3_lftags_cross_request_cost(): void
    {
        $this->log('--- F3: LfTags tags-updated across separate requests (Blink flushed each time), file driver ---');
        $id = Entry::query()->where('collection', 'products')->first()->id();
        $params = ['from' => 'products', 'taxonomy:brands:any' => 'brand-1|brand-2', 'related:is' => $id, 'title:is' => 'x', 'sort' => 'title:asc'];
        $flags = class_exists(FieldOptionsCache::class) ? [false, true] : [null];
        foreach ($flags as $f4) {
            if ($f4 !== null) {
                Config::set('statamic-livewire-filters.cache_field_options', $f4);
                $this->freshGeneration();
            }
            $writes = [];
            Event::listen(KeyWritten::class, function (KeyWritten $e) use (&$writes) {
                $writes[] = [$e->key, strlen(serialize($e->value))];
            });
            $c = Livewire::test(LfTags::class, ['blueprint' => 'products.product', 'fields' => 'brands|related|title']);
            $times = [];
            $tagsMd5 = [];
            for ($i = 0; $i < 4; $i++) {
                Blink::flush();
                $times[] = $this->ms(fn () => $c->dispatch('tags-updated', $params));
                $tagsMd5[] = substr(md5(json_encode($c->instance()->tags)), 0, 8);
            }
            $lw = array_filter($writes, fn ($w) => str_starts_with($w[0], 'lw_computed'));
            $fo = array_filter($writes, fn ($w) => str_contains($w[0], 'field-options'));
            $this->log(sprintf('F4 flag=%s: tags-updated #1..#4: %s ms | tags md5 %s | lw_computed writes %d (%d B) | field-options writes %d (%d B)',
                $f4 === null ? 'n/a' : ($f4 ? 'on' : 'off'), implode(' / ', $times), implode(',', array_unique($tagsMd5)),
                count($lw), array_sum(array_column($lw, 1)), count($fo), array_sum(array_column($fo, 1))));
        }
        $this->assertTrue(true);
    }

    /* ------------------------------------------------------------------ F4 */

    #[Test]
    public function f4_per_page_view_option_resolution_cost(): void
    {
        $this->log('--- F4: per page view mount cost (Blink flushed before each), file driver ---');
        $flags = class_exists(FieldOptionsCache::class) ? [false, true] : [null];
        foreach ($flags as $f4) {
            if ($f4 !== null) {
                Config::set('statamic-livewire-filters.cache_field_options', $f4);
            }
            foreach ([
                ['terms(500)', LfCheckboxFilter::class, ['blueprint' => 'products.product', 'field' => 'brands', 'condition' => 'taxonomy']],
                ['entries(1000)', LfCheckboxFilter::class, ['blueprint' => 'products.product', 'field' => 'related', 'condition' => 'is']],
                ['select size(5)', LfSelectFilter::class, ['blueprint' => 'products.product', 'field' => 'size', 'condition' => 'is']],
            ] as [$label, $class, $params]) {
                $this->freshGeneration();
                $times = [];
                for ($i = 0; $i < 4; $i++) {
                    Blink::flush();
                    $times[] = $this->ms(fn () => Livewire::test($class, $params));
                }
                $this->log(sprintf('F4 flag=%-3s %-14s mount #1..#4: %s ms', $f4 === null ? 'n/a' : ($f4 ? 'on' : 'off'), $label, implode(' / ', $times)));
            }
            // whole 8-filter page, Blink shared within the page view
            $this->freshGeneration();
            $times = [];
            for ($i = 0; $i < 3; $i++) {
                Blink::flush();
                $times[] = $this->ms(function () {
                    foreach ($this->filtersForPage() as [$label, $class, $params]) {
                        Livewire::test($class, $params);
                    }
                });
            }
            $this->log(sprintf('F4 flag=%-3s 8-filter page view #1..#3: %s ms', $f4 === null ? 'n/a' : ($f4 ? 'on' : 'off'), implode(' / ', $times)));
        }
        $this->assertTrue(true);
    }

    #[Test]
    public function f4_alternative_stache_index_pluck(): void
    {
        $this->log('--- F4 alternative: Stache index pluck() instead of get() (uncached current path vs pluck), file driver, Blink flushed ---');
        Config::set('statamic-livewire-filters.cache_field_options', false);
        $inst = Livewire::test(LfCheckboxFilter::class, ['blueprint' => 'products.product', 'field' => 'size', 'condition' => 'is'])->instance();
        $cur = \Closure::bind(fn ($c) => $this->getCollectionEntries($c)->all(), $inst, get_class($inst));
        $curT = \Closure::bind(fn ($t) => $this->getTaxonomyTerms($t)->all(), $inst, get_class($inst));

        foreach ([true, false] as $origin) {
            Config::set('statamic-livewire-filters.use_origin_id_for_entries_field', $origin);
            $a = $b = [];
            $tCur = $tAlt = [];
            for ($i = 0; $i < 3; $i++) {
                Blink::flush();
                $tCur[] = $this->ms(fn () => $cur('products'), $a);
                Blink::flush();
                $tAlt[] = $this->ms(function () use ($origin) {
                    $q = Entry::query()->where('collection', 'products')->where('site', 'en')->whereStatus('published');
                    $titles = $q->pluck('title', 'id');
                    if (! $origin) {
                        return $titles->all();
                    }
                    $origins = Entry::query()->where('collection', 'products')->where('site', 'en')->whereStatus('published')->pluck('origin', 'id');

                    return $titles->mapWithKeys(fn ($title, $id) => [($origins[$id] ?? null) ?: $id => $title])->all();
                }, $b);
            }
            ksort($a);
            ksort($b);
            $this->log(sprintf('entries(1000) origin=%-5s current get(): %s ms | pluck(title,id): %s ms | identical: %s (%d vs %d)',
                $origin ? 'true' : 'false', implode('/', $tCur), implode('/', $tAlt), $a === $b ? 'YES' : 'NO', count($a), count($b)));
        }

        $a = $b = [];
        $tCur = $tAlt = [];
        for ($i = 0; $i < 3; $i++) {
            Blink::flush();
            $tCur[] = $this->ms(fn () => $curT('brands'), $a);
            Blink::flush();
            $tAlt[] = $this->ms(function () {
                return Taxonomy::findByHandle('brands')->queryTerms()->where('site', 'en')->pluck('title', 'id')
                    ->mapWithKeys(fn ($title, $id) => [Str::after($id, '::') => $title])->all();
            }, $b);
        }
        ksort($a);
        ksort($b);
        $this->log(sprintf('terms(500)  current get(): %s ms | pluck(title,id) where site: %s ms | identical: %s (%d vs %d)',
            implode('/', $tCur), implode('/', $tAlt), $a === $b ? 'YES' : 'NO', count($a), count($b)));
        $this->assertTrue(true);
    }

    #[Test]
    public function f4_invalidation_attacks(): void
    {
        $this->log('--- F4 invalidation attacks (what does a page view show after each kind of content change?) ---');
        $hasF4 = class_exists(FieldOptionsCache::class);
        Config::set('statamic-livewire-filters.cache_field_options', true);
        $this->freshGeneration();
        $mountOptions = function (string $field, string $condition = 'is') {
            Blink::flush();

            return Livewire::test(LfCheckboxFilter::class, ['blueprint' => 'products.product', 'field' => $field, 'condition' => $condition])->instance()->filterOptions();
        };

        // warm
        $terms = $mountOptions('brands', 'taxonomy');
        $entries = $mountOptions('related');
        $this->log(sprintf('baseline: terms=%d entries=%d', count($terms), count($entries)));

        // (a) save() -> events fire
        Facades\Term::make()->taxonomy('brands')->inDefaultLocale()->slug('brand-saved')->data(['title' => 'Brand Saved'])->save();
        $this->log(sprintf('(a) Term::save():            terms=%d (expect %d)', count($mountOptions('brands', 'taxonomy')), count($terms) + 1));

        // (b) saveQuietly() -> no events (imports, migrations, stache:refresh, manual file edits behave the same)
        Facades\Term::make()->taxonomy('brands')->inDefaultLocale()->slug('brand-quiet')->data(['title' => 'Brand Quiet'])->saveQuietly();
        $this->log(sprintf('(b) Term::saveQuietly():     terms=%d (fresh would be %d) %s', count($mountOptions('brands', 'taxonomy')), count($terms) + 2, $hasF4 ? '<- STALE if lower' : ''));

        // (c) title edit via saveQuietly
        $t = Facades\Term::find('brands::brand-1');
        $t->data(['title' => 'Brand 1 RENAMED'])->saveQuietly();
        $this->log(sprintf('(c) title edit saveQuietly:  brand-1 => "%s" (fresh would be "Brand 1 RENAMED")', $mountOptions('brands', 'taxonomy')['brand-1'] ?? '?'));

        // (d) custom-sort path (IsSortable::getTaxonomyTermsSortedBy) is never cached: does it agree with the cached unsorted path?
        $sorted = Livewire::test(LfCheckboxFilter::class, ['blueprint' => 'products.product', 'field' => 'brands', 'condition' => 'taxonomy', 'sort' => 'title:asc'])->instance()->filterOptions();
        $this->log(sprintf('(d) sort=title:asc path:     brand-1 => "%s" vs unsorted cached path "%s" %s', $sorted['brand-1'] ?? '?', $mountOptions('brands', 'taxonomy')['brand-1'] ?? '?',
            ($sorted['brand-1'] ?? null) === ($mountOptions('brands', 'taxonomy')['brand-1'] ?? null) ? '(consistent)' : '<- INCONSISTENT'));

        // (e) entry unpublished via save (event) / via saveQuietly
        $e = Entry::query()->where('collection', 'products')->where('slug', 'product-1')->first();
        $e->published(false)->save();
        $this->log(sprintf('(e) entry unpublish save():  entries=%d (expect %d)', count($mountOptions('related')), count($entries) - 1));
        $e2 = Entry::query()->where('collection', 'products')->where('slug', 'product-2')->first();
        $e2->published(false)->saveQuietly();
        $this->log(sprintf('(e) entry unpublish quietly: entries=%d (fresh would be %d)', count($mountOptions('related')), count($entries) - 2));

        // (f) dated collection: entry becomes published when its date passes - no event at all
        Facades\Collection::make('events')->dated(true)->futureDateBehavior('private')->pastDateBehavior('public')->save();
        Facades\Blueprint::make()->setContents(['sections' => ['main' => ['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'related_events', 'field' => ['type' => 'entries', 'collections' => ['events'], 'display' => 'Events']],
        ]]]])->setHandle('event')->setNamespace('collections.events')->save();
        Entry::make()->collection('events')->slug('past')->date(Carbon::now()->subDay())->data(['title' => 'Past'])->save();
        Entry::make()->collection('events')->slug('future')->date(Carbon::now()->addMinutes(10))->data(['title' => 'Future'])->save();
        $this->freshGeneration();
        $opts = function () {
            Blink::flush();

            return Livewire::test(LfCheckboxFilter::class, ['blueprint' => 'events.event', 'field' => 'related_events', 'condition' => 'is'])->instance()->filterOptions();
        };
        $now = count($opts());
        Carbon::setTestNow(Carbon::now()->addMinutes(30)); // stays inside the 3600s TTL so the cache entry itself does not expire
        $later = count($opts());
        Carbon::setTestNow();
        $this->log(sprintf('(f) dated collection, future entry: options now=%d, 30 min later (date passed, no event)=%d (fresh would be 2) %s', $now, $later, $later === 2 ? '' : '<- STALE'));

        // (g) use_origin_id toggle keys
        Config::set('statamic-livewire-filters.use_origin_id_for_entries_field', false);
        $k1 = array_keys($mountOptions('related'));
        Config::set('statamic-livewire-filters.use_origin_id_for_entries_field', true);
        $k2 = array_keys($mountOptions('related'));
        $this->log(sprintf('(g) origin flag toggle: keys identical=%s (single-site, no origins: expected identical; separate cache keys exercised)', $k1 === $k2 ? 'yes' : 'no'));

        // (h) generation key + entry key live in the same store: cache:clear wipes both
        if ($hasF4) {
            $gen = Cache::get(FieldOptionsCache::GENERATION_KEY);
            $this->log(sprintf('(h) generation now %s; stored in default store (%s) -> cache:clear resets to 1 together with all field-options keys', var_export($gen, true), config('cache.default')));
        }
        $this->assertTrue(true);
    }
}
