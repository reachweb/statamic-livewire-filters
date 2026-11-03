<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Livewire\Attributes\Computed;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfCheckboxFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfDateFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfRadioFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfSelectFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfSort;
use Reach\StatamicLivewireFilters\Http\Livewire\LfTags;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;

class EvaluationCountingCheckboxFilter extends LfCheckboxFilter
{
    public static int $evaluations = 0;

    #[Computed]
    public function filterOptions(): array
    {
        static::$evaluations++;

        return parent::filterOptions();
    }
}

/**
 * Guards the slim-snapshot behaviour: `counts` is only materialised in `statamic_field`
 * when `enable_filter_values_count` is on, the computed option properties are no longer
 * persisted to the cache store, and term/entry options stay correct across requests,
 * sites and content changes without any cache invalidation.
 */
class FilterSnapshotAndOptionsTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cache.default', 'array');
        Cache::store('array')->flush();

        Facades\Blueprint::make()->setContents(['sections' => ['main' => ['fields' => [
            ['handle' => 'order', 'field' => ['type' => 'text', 'display' => 'Order']],
        ]]]])->setHandle('colors')->setNamespace('taxonomies.colors')->save();
        Facades\Taxonomy::make('colors')->save();
        Facades\Term::make()->taxonomy('colors')->inDefaultLocale()->slug('red')->data(['title' => 'Red', 'order' => '2'])->save();
        Facades\Term::make()->taxonomy('colors')->inDefaultLocale()->slug('black')->data(['title' => 'Black', 'order' => '1'])->save();

        Facades\Collection::make('items')->taxonomies(['colors'])->save();
        Facades\Blueprint::make()->setContents(['sections' => ['main' => ['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'display' => 'Title']],
            ['handle' => 'colors', 'field' => ['type' => 'terms', 'taxonomies' => ['colors'], 'display' => 'Colors']],
            ['handle' => 'related', 'field' => ['type' => 'entries', 'collections' => ['items'], 'display' => 'Related']],
            ['handle' => 'size', 'field' => ['type' => 'select', 'options' => ['s' => 'Small', 'l' => 'Large'], 'display' => 'Size', 'max_items' => 1]],
            ['handle' => 'when', 'field' => ['type' => 'date', 'display' => 'When']],
        ]]]])->setHandle('item')->setNamespace('collections.items')->save();

        Entry::make()->collection('items')->slug('one')->data(['title' => 'One', 'colors' => ['red'], 'size' => 's'])->save();
        Entry::make()->collection('items')->slug('two')->data(['title' => 'Two', 'colors' => ['black'], 'size' => 'l'])->save();
    }

    /**
     * Mounts a fresh checkbox filter as a new request would (request cache flushed) and returns its options.
     */
    protected function mountOptions(string $field, string $condition = 'is', array $extra = [], string $blueprint = 'items.item'): array
    {
        Blink::flush();

        return Livewire::test(LfCheckboxFilter::class, array_merge(['blueprint' => $blueprint, 'field' => $field, 'condition' => $condition], $extra))
            ->instance()->filterOptions();
    }

    /**
     * Multi-site content is created with its sites configured up front, as it would be on a real
     * install: the in-process Stache memoizes a term's localizations, so re-configuring an
     * existing taxonomy's sites inside one test would not represent a real request.
     */
    protected function seedTwoSites(): void
    {
        Config::set('statamic.system.multisite', true);
        Site::setSites([
            'en' => ['name' => 'English', 'url' => '/', 'locale' => 'en_US'],
            'es' => ['name' => 'Spanish', 'url' => '/es', 'locale' => 'es_ES'],
        ]);

        Facades\Taxonomy::make('shades')->sites(['en', 'es'])->save();
        Facades\Term::make()->taxonomy('shades')->inDefaultLocale()->slug('light')->data(['title' => 'Light'])->save();
        Facades\Term::make()->taxonomy('shades')->inDefaultLocale()->slug('dark')->data(['title' => 'Dark'])->save();

        Facades\Collection::make('goods')->sites(['en', 'es'])->taxonomies(['shades'])->save();
        Facades\Blueprint::make()->setContents(['sections' => ['main' => ['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'display' => 'Title']],
            ['handle' => 'shades', 'field' => ['type' => 'terms', 'taxonomies' => ['shades'], 'display' => 'Shades']],
            ['handle' => 'related', 'field' => ['type' => 'entries', 'collections' => ['goods'], 'display' => 'Related']],
        ]]]])->setHandle('good')->setNamespace('collections.goods')->save();

        Entry::make()->collection('goods')->locale('en')->slug('lamp')->data(['title' => 'Lamp', 'shades' => ['light']])->save();
        Entry::make()->collection('goods')->locale('en')->slug('candle')->data(['title' => 'Candle', 'shades' => ['dark']])->save();
    }

    #[Test]
    public function counts_fall_back_to_the_null_filled_shape_for_every_option_source_when_counts_are_disabled(): void
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', false);

        $cases = [
            'blueprint options' => [LfSelectFilter::class, ['field' => 'size']],
            'terms' => [LfCheckboxFilter::class, ['field' => 'colors', 'condition' => 'taxonomy']],
            'entries' => [LfCheckboxFilter::class, ['field' => 'related']],
            'custom options' => [LfRadioFilter::class, ['field' => 'size', 'options' => ['x' => 'X', 'y' => 'Y']]],
        ];

        foreach ($cases as $label => [$class, $params]) {
            $component = Livewire::test($class, array_merge(['blueprint' => 'items.item', 'condition' => 'is'], $params));
            $instance = $component->instance();
            $expected = array_fill_keys(array_keys($instance->filterOptions()), null);

            $this->assertNotEmpty($expected, $label);
            $this->assertSame($expected, $instance->counts(), "$label: counts() must equal the null-filled option keys");
            $this->assertSame($expected, $instance->counts, "$label: magic \$this->counts must match too (view path)");
            $this->assertArrayNotHasKey('counts', $instance->statamic_field, "$label: no counts array in the snapshot when the feature is off");
            $component->assertDontSeeHtml('class="text-lf-muted ml-1">(');
        }
    }

    #[Test]
    public function advanced_views_seed_alpine_with_null_counts_when_counts_are_disabled(): void
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', false);

        $component = Livewire::test(LfCheckboxFilter::class, ['blueprint' => 'items.item', 'field' => 'colors', 'condition' => 'taxonomy', 'view' => 'lf-checkbox-advanced']);
        $this->assertMatchesRegularExpression('/counts: JSON\.parse\(\'\{\\\\u0022red\\\\u0022:null,\\\\u0022black\\\\u0022:null\}\'\)/', $component->html());

        $component = Livewire::test(LfSelectFilter::class, ['blueprint' => 'items.item', 'field' => 'size', 'condition' => 'is', 'view' => 'lf-select-advanced']);
        $this->assertMatchesRegularExpression('/counts: JSON\.parse\(\'\{\\\\u0022s\\\\u0022:null,\\\\u0022l\\\\u0022:null\}\'\)/', $component->html());
    }

    #[Test]
    public function counts_are_still_materialized_and_dispatched_when_the_feature_is_enabled(): void
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', true);

        foreach ([
            [LfCheckboxFilter::class, ['field' => 'colors', 'condition' => 'taxonomy'], ['red' => 1, 'black' => 1]],
            [LfSelectFilter::class, ['field' => 'size', 'condition' => 'is'], ['s' => 1, 'l' => 1]],
            [LfRadioFilter::class, ['field' => 'size', 'condition' => 'is', 'options' => ['s' => 'S', 'xl' => 'XL']], ['s' => 1, 'xl' => 0]],
        ] as [$class, $params, $expectedCounts]) {
            $component = Livewire::test($class, array_merge(['blueprint' => 'items.item'], $params));
            $this->assertSame(array_fill_keys(array_keys($expectedCounts), null), $component->instance()->statamic_field['counts']);

            $component->dispatch('params-updated', ['from' => 'items'])
                ->assertDispatched('counts-updated', $expectedCounts);
            $this->assertSame($expectedCounts, $component->instance()->counts());
        }
    }

    #[Test]
    public function enabling_counts_after_the_page_was_rendered_without_them_still_computes_counts_on_update(): void
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', false);
        $component = Livewire::test(LfCheckboxFilter::class, ['blueprint' => 'items.item', 'field' => 'colors', 'condition' => 'taxonomy']);
        $this->assertArrayNotHasKey('counts', $component->instance()->statamic_field);

        Config::set('statamic-livewire-filters.enable_filter_values_count', true);
        $component->dispatch('params-updated', ['from' => 'items'])
            ->assertDispatched('counts-updated', ['red' => 1, 'black' => 1]);
    }

    #[Test]
    public function lf_tags_resolves_option_labels_regardless_of_the_counts_flag(): void
    {
        foreach ([false, true] as $flag) {
            Config::set('statamic-livewire-filters.enable_filter_values_count', $flag);
            $one = Entry::query()->where('collection', 'items')->where('slug', 'one')->first()->id();
            $component = Livewire::test(LfTags::class, ['blueprint' => 'items.item', 'fields' => 'colors|related|size'])
                ->dispatch('tags-updated', ['from' => 'items', 'taxonomy:colors:any' => 'red', 'related:is' => $one, 'size:is' => 'l']);
            $labels = $component->instance()->tags->pluck('optionLabel')->all();
            $this->assertSame(['Red', 'One', 'Large'], $labels, 'flag='.var_export($flag, true));
        }
    }

    #[Test]
    public function filter_options_is_evaluated_once_per_request_without_persist(): void
    {
        EvaluationCountingCheckboxFilter::$evaluations = 0;
        $component = Livewire::test(EvaluationCountingCheckboxFilter::class, ['blueprint' => 'items.item', 'field' => 'colors', 'condition' => 'taxonomy']);
        $this->assertSame(1, EvaluationCountingCheckboxFilter::$evaluations, 'mount + render reads $this->filter_options once');

        $instance = $component->instance();
        $instance->filterOptions;
        $instance->filter_options;
        $this->assertSame(1, EvaluationCountingCheckboxFilter::$evaluations, 'memoized within the request');

        $component->set('selected', ['red']);
        $this->assertSame(3, EvaluationCountingCheckboxFilter::$evaluations, 'next request: rules() direct call + one render read');
    }

    #[Test]
    public function no_lw_computed_cache_entries_are_written_on_mount_or_update(): void
    {
        $writes = [];
        Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$writes) {
            if (str_starts_with($event->key, 'lw_computed')) {
                $writes[] = $event->key;
            }
        });

        Livewire::test(LfCheckboxFilter::class, ['blueprint' => 'items.item', 'field' => 'colors', 'condition' => 'taxonomy'])->set('selected', ['red']);
        Livewire::test(LfRadioFilter::class, ['blueprint' => 'items.item', 'field' => 'size', 'condition' => 'is'])->set('selected', 's');
        Livewire::test(LfSelectFilter::class, ['blueprint' => 'items.item', 'field' => 'size', 'condition' => 'is'])->set('selected', 'l');
        Livewire::test(LfDateFilter::class, ['blueprint' => 'items.item', 'field' => 'when', 'condition' => 'is_after'])->set('selected', '2024-01-01');
        Livewire::test(LfSort::class, ['blueprint' => 'items.item', 'fields' => 'title|size'])->set('selected', 'title:asc');
        Livewire::test(LfTags::class, ['blueprint' => 'items.item', 'fields' => 'colors|size'])->dispatch('tags-updated', ['from' => 'items', 'size:is' => 's']);

        $this->assertSame([], $writes);
    }

    #[Test]
    public function lf_tags_produces_identical_tags_on_later_requests_without_a_persisted_cache(): void
    {
        $one = Entry::query()->where('collection', 'items')->where('slug', 'one')->first()->id();
        $params = ['from' => 'items', 'taxonomy:colors:any' => 'red|black', 'related:is' => $one];

        $cacheTraffic = [];
        Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$cacheTraffic) {
            if (str_starts_with($event->key, 'lw_computed')) {
                $cacheTraffic[] = 'write:'.$event->key;
            }
        });
        Event::listen(CacheHit::class, function (CacheHit $event) use (&$cacheTraffic) {
            if (str_starts_with($event->key, 'lw_computed')) {
                $cacheTraffic[] = 'hit:'.$event->key;
            }
        });

        $component = Livewire::test(LfTags::class, ['blueprint' => 'items.item', 'fields' => 'colors|related']);
        Blink::flush();
        $component->dispatch('tags-updated', $params);
        $first = $component->instance()->tags->all();

        Blink::flush();
        $component->dispatch('tags-updated', $params);

        $this->assertSame($first, $component->instance()->tags->all());
        $this->assertSame(['Red', 'Black', 'One'], collect($first)->pluck('optionLabel')->all());
        $this->assertSame([], $cacheTraffic, 'statamicFields is resolved per request, never through the cache store');
    }

    #[Test]
    public function content_changes_are_visible_on_the_next_request_without_any_invalidation(): void
    {
        $this->assertSame(['red' => 'Red', 'black' => 'Black'], $this->mountOptions('colors', 'taxonomy'));
        $this->assertSame(['One', 'Two'], array_values($this->mountOptions('related')));

        Facades\Term::make()->taxonomy('colors')->inDefaultLocale()->slug('blue')->data(['title' => 'Blue'])->saveQuietly();
        $this->assertSame(['red' => 'Red', 'black' => 'Black', 'blue' => 'Blue'], $this->mountOptions('colors', 'taxonomy'));

        Facades\Term::find('colors::blue')->data(['title' => 'Navy'])->saveQuietly();
        $this->assertSame('Navy', $this->mountOptions('colors', 'taxonomy')['blue']);

        Facades\Term::find('colors::blue')->delete();
        $this->assertArrayNotHasKey('blue', $this->mountOptions('colors', 'taxonomy'));

        Entry::query()->where('collection', 'items')->where('slug', 'two')->first()->published(false)->saveQuietly();
        $this->assertSame(['One'], array_values($this->mountOptions('related')));

        Entry::query()->where('collection', 'items')->where('slug', 'one')->first()->delete();
        $this->assertSame([], $this->mountOptions('related'));
    }

    #[Test]
    public function sorted_and_unsorted_term_options_agree_after_a_quiet_title_edit(): void
    {
        $this->mountOptions('colors', 'taxonomy');

        Facades\Term::find('colors::red')->data(['title' => 'Crimson', 'order' => '2'])->saveQuietly();

        $sorted = $this->mountOptions('colors', 'taxonomy', ['sort' => 'order:asc']);
        $unsorted = $this->mountOptions('colors', 'taxonomy');

        $this->assertSame(['black', 'red'], array_keys($sorted));
        $this->assertSame('Crimson', $sorted['red']);
        $this->assertSame('Crimson', $unsorted['red'], 'both resolution paths read live content');
    }

    #[Test]
    public function localized_term_titles_do_not_leak_between_sites(): void
    {
        $this->seedTwoSites();
        Facades\Term::find('shades::light')->in('es')->slug('claro')->data(['title' => 'Claro'])->save();

        $perSite = function (string $site) {
            Site::setCurrent($site);

            return $this->mountOptions('shades', 'taxonomy', [], 'goods.good');
        };

        $this->assertSame(['light' => 'Light', 'dark' => 'Dark'], $perSite('en'));
        $this->assertSame(['light' => 'Claro', 'dark' => 'Dark'], $perSite('es'), 'unlocalized terms fall back to the default locale title');

        Facades\Term::find('shades::light')->in('es')->data(['title' => 'Muy Claro'])->save();
        Facades\Term::find('shades::dark')->in('es')->slug('oscuro')->data(['title' => 'Oscuro'])->save();

        $this->assertSame(['light' => 'Light', 'dark' => 'Dark'], $perSite('en'));
        $this->assertSame(['light' => 'Muy Claro', 'dark' => 'Oscuro'], $perSite('es'));
    }

    #[Test]
    public function origin_id_flag_selects_origin_or_localized_entry_ids_per_site(): void
    {
        $this->seedTwoSites();
        $lamp = Entry::query()->where('collection', 'goods')->where('slug', 'lamp')->first();
        $lampara = Entry::make()->collection('goods')->locale('es')->slug('lampara')->origin($lamp)->data(['title' => 'Lámpara']);
        $lampara->save();
        Site::setCurrent('es');

        Config::set('statamic-livewire-filters.use_origin_id_for_entries_field', true);
        $this->assertSame([$lamp->id() => 'Lámpara'], $this->mountOptions('related', 'is', [], 'goods.good'));

        Config::set('statamic-livewire-filters.use_origin_id_for_entries_field', false);
        $this->assertSame([$lampara->id() => 'Lámpara'], $this->mountOptions('related', 'is', [], 'goods.good'));

        Config::set('statamic-livewire-filters.use_origin_id_for_entries_field', true);
        $this->assertSame([$lamp->id() => 'Lámpara'], $this->mountOptions('related', 'is', [], 'goods.good'));

        Site::setCurrent('en');
        $this->assertSame(['Lamp', 'Candle'], array_values($this->mountOptions('related', 'is', [], 'goods.good')));
    }
}
