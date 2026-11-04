<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfCheckboxFilter;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Stache\Query\TermQueryBuilder;

/**
 * Proves that resolving term and entry option labels from the Stache indexes
 * returns exactly what the previous `get()`-and-map implementation returned:
 * same keys, same labels, same order, on single-site, partially localized
 * multi-site, numeric-id and `title_format` content. Terms use a keyed
 * `pluck('title', 'id')`; entries read `title`/`origin` from the collection's
 * own store, since a keyed pluck through the aggregate store renumbers numeric ids.
 */
class StacheIndexOptionsTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    protected function makeBlueprint(string $collection, array $fields): void
    {
        Facades\Blueprint::make()->setContents(['sections' => ['main' => ['fields' => $fields]]])
            ->setHandle($collection)->setNamespace('collections.'.$collection)->save();
    }

    /**
     * Mounts a filter as a fresh request would and returns its resolved options.
     */
    protected function mountOptions(string $blueprint, string $field, string $condition = 'is'): array
    {
        Blink::flush();

        return Livewire::test(LfCheckboxFilter::class, ['blueprint' => $blueprint, 'field' => $field, 'condition' => $condition])
            ->instance()->filterOptions();
    }

    /**
     * The term mapping exactly as HandleStatamicQueries::getTaxonomyTerms() computed it before this change.
     */
    protected function legacyTerms(string $taxonomy, string $site): array
    {
        return Taxonomy::findByHandle($taxonomy)->queryTerms()->get()
            ->unique(fn ($term) => $term->inDefaultLocale()->slug())
            ->mapWithKeys(function ($term) use ($site) {
                $default = $term->inDefaultLocale();

                return [$default->slug() => ($term->in($site) ?? $default)->title()];
            })->all();
    }

    /**
     * The entry mapping exactly as HandleStatamicQueries::getCollectionEntries() computed it before this change.
     */
    protected function legacyEntries(string $collection, string $site, bool $useOriginId): array
    {
        return Entry::query()
            ->where('collection', $collection)
            ->where('site', $site)
            ->whereStatus('published')
            ->get()
            ->mapWithKeys(function ($entry) use ($useOriginId) {
                if ($useOriginId) {
                    return [$entry->hasOrigin() ? $entry->origin()->id() : $entry->id() => $entry->title];
                }

                return [$entry->id() => $entry->title];
            })->all();
    }

    #[Test]
    public function the_stache_query_builders_are_the_ones_exercised_by_this_suite(): void
    {
        Taxonomy::make('colors')->save();
        Facades\Collection::make('items')->save();

        $this->assertInstanceOf(TermQueryBuilder::class, Taxonomy::findByHandle('colors')->queryTerms());
        $this->assertInstanceOf(EntryQueryBuilder::class, Entry::query());
    }

    #[Test]
    public function single_site_term_and_entry_options_match_the_legacy_mapping_in_value_and_order(): void
    {
        Taxonomy::make('colors')->save();
        Facades\Term::make()->taxonomy('colors')->inDefaultLocale()->slug('red')->data(['title' => 'Red'])->save();
        Facades\Term::make()->taxonomy('colors')->inDefaultLocale()->slug('black')->data(['title' => 'Black'])->save();
        Facades\Term::make()->taxonomy('colors')->inDefaultLocale()->slug('no-title')->data([])->save();

        Facades\Collection::make('items')->taxonomies(['colors'])->save();
        $this->makeBlueprint('items', [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'colors', 'field' => ['type' => 'terms', 'taxonomies' => ['colors'], 'display' => 'Colors']],
            ['handle' => 'related', 'field' => ['type' => 'entries', 'collections' => ['items'], 'display' => 'Related']],
        ]);
        Entry::make()->collection('items')->slug('zeta')->data(['title' => 'Zeta'])->save();
        Entry::make()->collection('items')->slug('alpha')->data(['title' => 'Alpha'])->save();
        Entry::make()->collection('items')->slug('draft')->published(false)->data(['title' => 'Draft'])->save();

        $terms = $this->mountOptions('items.items', 'colors', 'taxonomy');
        $this->assertSame($this->legacyTerms('colors', 'en'), $terms);
        $this->assertSame(['red' => 'Red', 'black' => 'Black', 'no-title' => 'no-title'], $terms);

        foreach ([true, false] as $useOriginId) {
            Config::set('statamic-livewire-filters.use_origin_id_for_entries_field', $useOriginId);
            $entries = $this->mountOptions('items.items', 'related');
            $this->assertSame($this->legacyEntries('items', 'en', $useOriginId), $entries);
            $this->assertSame(['Zeta', 'Alpha'], array_values($entries), 'draft excluded, legacy order kept');
        }
    }

    #[Test]
    public function numeric_entry_ids_keep_their_ids_as_option_keys(): void
    {
        Facades\Collection::make('items')->save();
        $this->makeBlueprint('items', [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'related', 'field' => ['type' => 'entries', 'collections' => ['items'], 'display' => 'Related']],
        ]);
        Entry::make()->id('100')->collection('items')->slug('hundred')->data(['title' => 'Hundred'])->save();
        Entry::make()->id('200')->collection('items')->slug('two-hundred')->data(['title' => 'Two Hundred'])->save();
        Entry::make()->id('abc')->collection('items')->slug('abc')->data(['title' => 'Abc'])->save();

        foreach ([true, false] as $useOriginId) {
            Config::set('statamic-livewire-filters.use_origin_id_for_entries_field', $useOriginId);
            $entries = $this->mountOptions('items.items', 'related');
            $this->assertSame($this->legacyEntries('items', 'en', $useOriginId), $entries);
            $this->assertEqualsCanonicalizing(['100', '200', 'abc'], array_map('strval', array_keys($entries)));
        }
    }

    #[Test]
    public function partially_localized_sites_match_the_legacy_mapping_per_site_for_terms_and_both_entry_id_modes(): void
    {
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
        Facades\Term::find('colors::red')->in('es')->slug('rojo')->data(['title' => 'Rojo'])->save();
        Facades\Term::find('colors::black')->in('es')->slug('negro')->data(['title' => 'Negro'])->save();
        Facades\Term::find('colors::yellow')->in('es')->slug('amarillo')->data(['title' => 'Amarillo'])->save();
        Facades\Term::find('colors::red')->in('de')->slug('rot')->data(['title' => 'Rot'])->save();
        Facades\Term::find('colors::black')->in('de')->slug('schwarz')->data(['title' => 'Schwarz'])->save();

        Facades\Collection::make('clothes')->sites(['en', 'es', 'de'])->taxonomies(['colors'])->save();
        $this->makeBlueprint('clothes', [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'colors', 'field' => ['type' => 'terms', 'taxonomies' => ['colors'], 'display' => 'Colors']],
            ['handle' => 'related', 'field' => ['type' => 'entries', 'collections' => ['clothes'], 'display' => 'Related']],
        ]);
        $shirt = Entry::make()->collection('clothes')->locale('en')->slug('shirt')->data(['title' => 'Shirt']);
        $shirt->save();
        $hat = Entry::make()->collection('clothes')->locale('en')->slug('hat')->data(['title' => 'Hat']);
        $hat->save();
        Entry::make()->collection('clothes')->locale('en')->slug('sock')->data(['title' => 'Sock'])->save();
        Entry::make()->collection('clothes')->locale('es')->slug('camisa')->origin($shirt)->data(['title' => 'Camisa'])->save();
        Entry::make()->collection('clothes')->locale('es')->slug('sombrero')->origin($hat)->published(false)->data(['title' => 'Sombrero'])->save();
        Entry::make()->collection('clothes')->locale('es')->slug('solo-es')->data(['title' => 'Solo ES'])->save();
        Entry::make()->collection('clothes')->locale('es')->slug('calcetin')->origin(Entry::query()->where('collection', 'clothes')->where('site', 'en')->where('slug', 'sock')->first())->data([])->save();

        $expectedTerms = [
            'en' => ['red' => 'Red', 'black' => 'Black', 'yellow' => 'Yellow'],
            'es' => ['red' => 'Rojo', 'black' => 'Negro', 'yellow' => 'Amarillo'],
            'de' => ['red' => 'Rot', 'black' => 'Schwarz', 'yellow' => 'Yellow'],
        ];

        foreach (['en', 'es', 'de'] as $site) {
            Site::setCurrent($site);

            $terms = $this->mountOptions('clothes.clothes', 'colors', 'taxonomy');
            $this->assertSame($this->legacyTerms('colors', $site), $terms, "terms on $site");
            $this->assertSame($expectedTerms[$site], $terms, "terms on $site");

            foreach ([true, false] as $useOriginId) {
                Config::set('statamic-livewire-filters.use_origin_id_for_entries_field', $useOriginId);
                $this->assertSame(
                    $this->legacyEntries('clothes', $site, $useOriginId),
                    $this->mountOptions('clothes.clothes', 'related'),
                    "entries on $site, origin ids: ".var_export($useOriginId, true)
                );
            }
        }

        Site::setCurrent('es');
        Config::set('statamic-livewire-filters.use_origin_id_for_entries_field', true);
        $this->assertSame(
            [$shirt->id() => 'Camisa', Entry::query()->where('collection', 'clothes')->where('site', 'es')->where('slug', 'solo-es')->first()->id() => 'Solo ES', Entry::query()->where('collection', 'clothes')->where('site', 'en')->where('slug', 'sock')->first()->id() => 'Sock'],
            $this->mountOptions('clothes.clothes', 'related'),
            'origin ids for localizations, own id without an origin, draft excluded, inherited title'
        );

        Site::setCurrent('de');
        $this->assertSame([], $this->mountOptions('clothes.clothes', 'related'));
    }

    #[Test]
    public function a_taxonomy_not_configured_for_the_current_site_falls_back_to_its_default_locale_like_before(): void
    {
        Config::set('statamic.system.multisite', true);
        Site::setSites([
            'en' => ['name' => 'English', 'url' => '/', 'locale' => 'en_US'],
            'es' => ['name' => 'Spanish', 'url' => '/es', 'locale' => 'es_ES'],
        ]);
        Taxonomy::make('colors')->sites(['en'])->save();
        Facades\Term::make()->taxonomy('colors')->inDefaultLocale()->slug('red')->data(['title' => 'Red'])->save();
        Facades\Collection::make('items')->sites(['en', 'es'])->taxonomies(['colors'])->save();
        $this->makeBlueprint('items', [
            ['handle' => 'colors', 'field' => ['type' => 'terms', 'taxonomies' => ['colors'], 'display' => 'Colors']],
        ]);

        Site::setCurrent('es');

        $options = $this->mountOptions('items.items', 'colors', 'taxonomy');

        $this->assertSame($this->legacyTerms('colors', 'es'), $options);
        $this->assertSame(['red' => 'Red'], $options);
    }

    #[Test]
    public function terms_that_only_exist_through_entries_on_other_sites_are_listed_on_every_site(): void
    {
        // Saving these entries syncs "en::green" into the site and id indexes while the
        // title index holds "es::green", so the indexes disagree on the term's key.
        Config::set('statamic.system.multisite', true);
        Site::setSites([
            'en' => ['name' => 'English', 'url' => '/', 'locale' => 'en_US'],
            'es' => ['name' => 'Spanish', 'url' => '/es', 'locale' => 'es_ES'],
        ]);
        Taxonomy::make('colors')->sites(['en', 'es'])->save();
        Facades\Term::make()->taxonomy('colors')->inDefaultLocale()->slug('red')->data(['title' => 'Red'])->save();
        Facades\Collection::make('items')->sites(['en', 'es'])->taxonomies(['colors'])->save();
        $this->makeBlueprint('items', [
            ['handle' => 'colors', 'field' => ['type' => 'terms', 'taxonomies' => ['colors'], 'display' => 'Colors']],
        ]);
        Entry::make()->collection('items')->locale('es')->slug('verde')->data(['colors' => ['green']])->save();
        Entry::make()->collection('items')->locale('en')->slug('blue')->data(['colors' => ['blue']])->save();

        foreach (['en', 'es'] as $site) {
            Site::setCurrent($site);

            $options = $this->mountOptions('items.items', 'colors', 'taxonomy');

            $this->assertSame($this->legacyTerms('colors', $site), $options, "terms on $site");
            $this->assertEqualsCanonicalizing(['red' => 'Red', 'green' => 'green', 'blue' => 'blue'], $options, "terms on $site");
        }
    }

    #[Test]
    public function title_format_collections_match_the_legacy_mapping(): void
    {
        Facades\Collection::make('people')->titleFormats(['en' => '{{ first }} {{ last }}'])->save();
        $this->makeBlueprint('people', [
            ['handle' => 'first', 'field' => ['type' => 'text']],
            ['handle' => 'last', 'field' => ['type' => 'text']],
            ['handle' => 'friends', 'field' => ['type' => 'entries', 'collections' => ['people'], 'display' => 'Friends']],
        ]);
        Entry::make()->collection('people')->slug('ada')->data(['first' => 'Ada', 'last' => 'Lovelace'])->save();
        Entry::make()->collection('people')->slug('alan')->data(['first' => 'Alan', 'last' => 'Turing'])->save();

        $options = $this->mountOptions('people.people', 'friends');

        $this->assertSame($this->legacyEntries('people', 'en', true), $options);
        $this->assertSame(['Ada Lovelace', 'Alan Turing'], array_values($options));
    }
}
