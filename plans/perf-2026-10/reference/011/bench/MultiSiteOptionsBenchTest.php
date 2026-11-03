<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfCheckboxFilter;
use Reach\StatamicLivewireFilters\Support\FieldOptionsCache;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;

/**
 * F4 multi-site attack + alternative-primitive equivalence (3 sites, partial localizations, origins).
 * Run:  BENCH_TAG=BEFORE vendor/bin/phpunit tests/Bench/MultiSiteOptionsBenchTest.php
 */
class MultiSiteOptionsBenchTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    protected string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheDir = sys_get_temp_dir().'/v1-ms-bench-cache-'.getmypid();
        Config::set('cache.stores.benchstore', ['driver' => 'file', 'path' => $this->cacheDir]);
        Config::set('cache.default', 'benchstore');
        Cache::store('benchstore')->flush();

        Config::set('statamic.system.multisite', true);
        Site::setSites([
            'en' => ['name' => 'English', 'url' => '/', 'locale' => 'en_US'],
            'es' => ['name' => 'Spanish', 'url' => '/es', 'locale' => 'es_ES'],
            'de' => ['name' => 'German', 'url' => '/de', 'locale' => 'de_DE'],
        ]);

        Taxonomy::make('colors')->sites(['en', 'es', 'de'])->save();
        foreach (['red' => 'Red', 'black' => 'Black', 'yellow' => 'Yellow'] as $slug => $title) {
            Facades\Term::make()->taxonomy('colors')->inDefaultLocale()->slug($slug)->data(['title' => $title])->save();
        }
        // es: all localized; de: only red + black localized (yellow falls back to default)
        Facades\Term::find('colors::red')->in('es')->slug('rojo')->data(['title' => 'Rojo'])->save();
        Facades\Term::find('colors::black')->in('es')->slug('negro')->data(['title' => 'Negro'])->save();
        Facades\Term::find('colors::yellow')->in('es')->slug('amarillo')->data(['title' => 'Amarillo'])->save();
        Facades\Term::find('colors::red')->in('de')->slug('rot')->data(['title' => 'Rot'])->save();
        Facades\Term::find('colors::black')->in('de')->slug('schwarz')->data(['title' => 'Schwarz'])->save();

        Facades\Collection::make('clothes')->sites(['en', 'es', 'de'])->taxonomies(['colors'])->save();
        Facades\Blueprint::make()->setContents(['sections' => ['main' => ['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'display' => 'Title']],
            ['handle' => 'colors', 'field' => ['type' => 'terms', 'taxonomies' => ['colors'], 'display' => 'Colors']],
            ['handle' => 'related', 'field' => ['type' => 'entries', 'collections' => ['clothes'], 'display' => 'Related']],
        ]]]])->setHandle('clothes')->setNamespace('collections.clothes')->save();

        // 3 en entries; shirt localized to es (origin = en shirt); hat localized to es as a draft; es-only entry (no origin)
        $shirt = Entry::make()->collection('clothes')->locale('en')->slug('shirt')->data(['title' => 'Shirt']);
        $shirt->save();
        $hat = Entry::make()->collection('clothes')->locale('en')->slug('hat')->data(['title' => 'Hat']);
        $hat->save();
        Entry::make()->collection('clothes')->locale('en')->slug('sock')->data(['title' => 'Sock'])->save();
        Entry::make()->collection('clothes')->locale('es')->slug('camisa')->origin($shirt)->data(['title' => 'Camisa'])->save();
        Entry::make()->collection('clothes')->locale('es')->slug('sombrero')->origin($hat)->published(false)->data(['title' => 'Sombrero'])->save();
        Entry::make()->collection('clothes')->locale('es')->slug('solo-es')->data(['title' => 'Solo ES'])->save();

        Livewire::test(LfCheckboxFilter::class, ['blueprint' => 'clothes.clothes', 'field' => 'colors', 'condition' => 'taxonomy']);
        Blink::flush();
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

    protected function siteOptions(string $site, string $field, string $condition): array
    {
        Site::setCurrent($site);
        Blink::flush();

        return Livewire::test(LfCheckboxFilter::class, ['blueprint' => 'clothes.clothes', 'field' => $field, 'condition' => $condition])->instance()->filterOptions();
    }

    #[Test]
    public function multisite_equivalence_and_leak_attack(): void
    {
        $this->log('--- F4 multi-site: current path per site, pluck alternative equivalence, localization leak attack ---');
        $hasF4 = class_exists(FieldOptionsCache::class);
        if ($hasF4) {
            Config::set('statamic-livewire-filters.cache_field_options', true);
        }

        foreach (['en', 'es', 'de'] as $site) {
            $terms = $this->siteOptions($site, 'colors', 'taxonomy');
            Config::set('statamic-livewire-filters.use_origin_id_for_entries_field', true);
            $entriesOrigin = $this->siteOptions($site, 'related', 'is');
            Config::set('statamic-livewire-filters.use_origin_id_for_entries_field', false);
            $entriesOwn = $this->siteOptions($site, 'related', 'is');

            // alternative primitive
            Site::setCurrent($site);
            Blink::flush();
            $altTerms = Taxonomy::findByHandle('colors')->queryTerms()->where('site', $site)->pluck('title', 'id')
                ->mapWithKeys(fn ($t, $id) => [Str::after($id, '::') => $t])->all();
            $titles = Entry::query()->where('collection', 'clothes')->where('site', $site)->whereStatus('published')->pluck('title', 'id');
            $origins = Entry::query()->where('collection', 'clothes')->where('site', $site)->whereStatus('published')->pluck('origin', 'id');
            $altOrigin = $titles->mapWithKeys(fn ($t, $id) => [($origins[$id] ?? null) ?: $id => $t])->all();
            $altOwn = $titles->all();
            $eq = fn ($a, $b) => (function () use ($a, $b) {
                ksort($a);
                ksort($b);

                return $a === $b ? 'same' : 'DIFF';
            })();
            $this->log(sprintf('site=%s terms=%s | pluck-alt terms %s | entries(origin ids)=%s pluck-alt %s | entries(own ids)=%s pluck-alt %s',
                $site, json_encode($terms), $eq($terms, $altTerms), json_encode(array_values($entriesOrigin)), $eq($entriesOrigin, $altOrigin),
                json_encode(array_values($entriesOwn)), $eq($entriesOwn, $altOwn)));
        }

        // leak attack: localize a title in es (save -> TermSaved), then look at en and de
        Facades\Term::find('colors::red')->in('es')->data(['title' => 'Rojo Nuevo'])->save();
        $this->log(sprintf('after es title edit: en red="%s" (expect Red) | es red="%s" (expect Rojo Nuevo) | de red="%s" (expect Rot)',
            $this->siteOptions('en', 'colors', 'taxonomy')['red'] ?? '?', $this->siteOptions('es', 'colors', 'taxonomy')['red'] ?? '?', $this->siteOptions('de', 'colors', 'taxonomy')['red'] ?? '?'));

        // new localization for a previously-unlocalized term in de
        Facades\Term::find('colors::yellow')->in('de')->slug('gelb')->data(['title' => 'Gelb'])->save();
        $this->log(sprintf('after new de localization: de yellow="%s" (expect Gelb) | en yellow="%s" (expect Yellow)',
            $this->siteOptions('de', 'colors', 'taxonomy')['yellow'] ?? '?', $this->siteOptions('en', 'colors', 'taxonomy')['yellow'] ?? '?'));

        // publish the es draft localization (EntrySaved) -> es entries should now include Sombrero under hat's origin id
        Config::set('statamic-livewire-filters.use_origin_id_for_entries_field', true);
        Entry::query()->where('collection', 'clothes')->where('site', 'es')->where('slug', 'sombrero')->first()->published(true)->save();
        $this->log(sprintf('after publishing es draft: es entries=%s (expect Camisa, Sombrero, Solo ES)', json_encode(array_values($this->siteOptions('es', 'related', 'is')))));
        $this->assertTrue(true);
    }
}
