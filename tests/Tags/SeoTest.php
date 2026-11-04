<?php

namespace Reach\StatamicLivewireFilters\Tests\Tags;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Support\Facades\Config;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Tests\FakesViews;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class SeoTest extends TestCase
{
    use FakesViews, PreventSavingStacheItemsToDisk;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('statamic-livewire-filters.custom_query_string_aliases', [
            'option' => 'item_options:is',
            'title' => 'title:contains',
            'cat' => 'query_scope:multiselect:item_options',
        ]);

        $collection = Facades\Collection::make('pages')
            ->routes('{parent_uri}/{slug}')
            ->structureContents(['root' => true])
            ->save();

        Facades\Blueprint::make()->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        ['handle' => 'title', 'field' => ['type' => 'text']],
                        ['handle' => 'item_options', 'field' => ['type' => 'select', 'options' => [
                            ['key' => 'option1', 'value' => 'Option 1'],
                            ['key' => 'option2', 'value' => 'Option 2'],
                        ]]],
                    ],
                ],
            ],
        ])->setHandle('pages')->setNamespace('collections.pages')->save();

        foreach (['a' => 'option1', 'b' => 'option1', 'c' => 'option2'] as $slug => $option) {
            EntryFactory::id($slug)->collection($collection)->slug($slug)->make()
                ->set('title', "Entry {$slug}")->set('item_options', $option)->save();
        }

        $this->withStandardFakeViews();
        $this->viewShouldReturnRaw('default', '{{ livewire-collection:pages paginate="1" }}');
        $this->viewShouldReturnRaw('statamic-livewire-filters::livewire.livewire-collection', '<div>{{ entries }}{{ title }}{{ /entries }}</div>');
        $this->viewShouldReturnRaw('statamic-livewire-filters::livewire.ui.pagination', '', 'blade.php');
        $this->viewShouldReturnRaw('layout', '<html><head><seo>{{ livewire-filters:seo }}</seo></head><body>{{ template_content }}</body></html>');
    }

    protected function seoHead(TestResponse $response): string
    {
        preg_match('#<seo>(.*)</seo>#s', $response->getContent(), $head);

        return str_replace(["\r", "\n"], '', $head[1]);
    }

    protected function useCustomQueryString(array $indexable = []): void
    {
        Config::set('statamic-livewire-filters.custom_query_string', 'filters');
        Config::set('statamic-livewire-filters.seo_filter_headers', true);
        Config::set('statamic-livewire-filters.seo_indexable_filters', $indexable);
    }

    protected function useLivewireQueryString(array $indexable = []): void
    {
        Config::set('statamic-livewire-filters.enable_query_string', true);
        Config::set('statamic-livewire-filters.seo_indexable_filters', $indexable);
    }

    /** Pages whose only job is the layout's tag, for params the test collection can't query. */
    protected function withoutCollection(): void
    {
        $this->viewShouldReturnRaw('default', 'listing');
    }

    protected function selfCanonical(array $params): string
    {
        return '<link rel="canonical" href="'.e('http://localhost?'.http_build_query(['params' => $params])).'">';
    }

    protected string $baseNoindex = '<link rel="canonical" href="http://localhost"><meta name="robots" content="noindex,follow">';

    #[Test]
    public function custom_mode_head_only_carries_page_dependent_tags()
    {
        $this->useCustomQueryString();
        Config::set('statamic-livewire-filters.seo_page_names', ['page', 'p']);

        $this->assertSame('', $this->seoHead($this->get('/')));
        $this->assertSame($this->baseNoindex, $this->seoHead($this->get('/?page=2')));
        $this->assertSame($this->baseNoindex, $this->seoHead($this->get('/?p=3')));
    }

    #[Test]
    public function custom_mode_filter_urls_send_noindex_and_base_canonical_headers()
    {
        $this->useCustomQueryString();

        $response = $this->get('/filters/option/option1');

        $this->assertSame('', $this->seoHead($response));
        $response->assertHeader('X-Robots-Tag', 'noindex, follow');
        $response->assertHeader('Link', '<http://localhost>; rel="canonical"');

        $this->get('/')->assertHeaderMissing('X-Robots-Tag')->assertHeaderMissing('Link');
    }

    #[Test]
    public function custom_mode_urls_without_known_filters_still_point_to_the_base_page()
    {
        $this->useCustomQueryString(['option']);

        $this->get('/filters/nope/x')
            ->assertHeader('X-Robots-Tag', 'noindex, follow')
            ->assertHeader('Link', '<http://localhost>; rel="canonical"');
    }

    #[Test]
    public function custom_mode_later_filtered_pages_are_noindex_without_a_conflicting_canonical_header()
    {
        $this->useCustomQueryString(['option']);
        Config::set('statamic-livewire-filters.seo_page_names', ['p']);

        $this->get('/filters/option/option1?p=2')
            ->assertHeader('X-Robots-Tag', 'noindex, follow')
            ->assertHeaderMissing('Link');
    }

    #[Test]
    public function custom_mode_single_value_of_an_indexable_filter_gets_a_self_canonical_header()
    {
        $this->useCustomQueryString(['brand', 'option']);

        $this->get('/filters/option/option2')
            ->assertHeaderMissing('X-Robots-Tag')
            ->assertHeader('Link', '<http://localhost/filters/option/option2>; rel="canonical"');

        foreach (['/filters/option/option1,option2', '/filters/option/option1/title/Entry'] as $uri) {
            $this->get($uri)
                ->assertHeader('X-Robots-Tag', 'noindex, follow')
                ->assertHeader('Link', '<http://localhost>; rel="canonical"');
        }
    }

    #[Test]
    public function custom_mode_self_canonical_is_rebuilt_from_the_parsed_filters()
    {
        $this->useCustomQueryString(['option', 'title']);
        $this->withoutCollection();

        // Config alias order, junk segments dropped, values encoded.
        $this->get('/filters/title/I%20Love/nope/x/option/option2/odd')
            ->assertHeaderMissing('X-Robots-Tag')
            ->assertHeader('Link', '<http://localhost/filters/option/option2/title/I%20Love>; rel="canonical"');

        $this->get('/filters/option/option1>;rel=preload')
            ->assertHeader('Link', '<http://localhost/filters/option/option1%3E%3Brel%3Dpreload>; rel="canonical"');
    }

    #[Test]
    public function custom_mode_query_scope_aliases_match_by_alias_and_field()
    {
        $this->withoutCollection();

        foreach (['cat', 'item_options'] as $indexable) {
            $this->useCustomQueryString([$indexable]);

            $this->get('/filters/cat/option1')
                ->assertHeaderMissing('X-Robots-Tag')
                ->assertHeader('Link', '<http://localhost/filters/cat/option1>; rel="canonical"');
        }
    }

    #[Test]
    public function custom_mode_headers_are_sent_on_half_cache_hits_of_the_shared_page()
    {
        $this->useCustomQueryString();
        Config::set('statamic.static_caching.strategy', 'half');

        $this->get('/filters/option/option1')->assertHeader('X-Robots-Tag', 'noindex, follow');

        // The base page is served from the entry the filter URL created, and stays indexable.
        $this->viewShouldReturnRaw('default', 'changed');
        $base = $this->get('/')->assertOk()->assertDontSee('changed')->assertHeaderMissing('X-Robots-Tag');
        $this->assertSame('', $this->seoHead($base));

        $this->get('/filters/option/option2')->assertDontSee('changed')->assertHeader('X-Robots-Tag', 'noindex, follow');
    }

    #[Test]
    public function custom_mode_headers_are_sent_on_full_cache_hits_served_by_php()
    {
        $this->useCustomQueryString();
        $path = sys_get_temp_dir().'/lf-seo-static-'.getmypid();
        Config::set('statamic.static_caching.strategy', 'full');
        Config::set('statamic.static_caching.strategies.full.path', $path);

        try {
            $this->get('/filters/option/option1')->assertHeader('X-Robots-Tag', 'noindex, follow');
            $this->assertFileExists($path.'/_.html');
            $this->assertStringNotContainsString('noindex', file_get_contents($path.'/_.html'));

            $this->viewShouldReturnRaw('default', 'changed');
            $this->get('/filters/option/option2')->assertDontSee('changed')->assertHeader('X-Robots-Tag', 'noindex, follow');
        } finally {
            app('files')->deleteDirectory($path);
        }
    }

    #[Test]
    public function custom_mode_failed_filter_urls_get_no_headers()
    {
        $this->useCustomQueryString();

        $this->get('/missing/filters/option/option1')->assertNotFound()
            ->assertHeaderMissing('X-Robots-Tag')->assertHeaderMissing('Link');
    }

    #[Test]
    public function custom_mode_headers_are_opt_in()
    {
        Config::set('statamic-livewire-filters.custom_query_string', 'filters');

        $this->get('/filters/option/option1')->assertHeaderMissing('X-Robots-Tag')->assertHeaderMissing('Link');
    }

    #[Test]
    public function livewire_mode_strips_params_from_the_canonical_of_filtered_pages()
    {
        $this->useLivewireQueryString();

        $this->assertSame($this->baseNoindex, $this->seoHead($this->get('/?params[item_options:is]=option1&utm_source=mail')));
        $this->assertSame($this->baseNoindex, $this->seoHead($this->get('/?params[sort]=title')));
    }

    #[Test]
    public function livewire_mode_single_value_of_an_indexable_filter_keeps_a_sorted_self_canonical()
    {
        $this->useLivewireQueryString(['item_options', 'title']);

        // Collection settings, stray scopes and tracking params stay out; keys are sorted.
        $this->assertSame(
            $this->selfCanonical(['item_options:is' => 'option2', 'title:contains' => 'Entry, c']),
            $this->seoHead($this->get('/?params[title:contains]=Entry,%20c&params[page_name]=p&params[paginate]=9&params[query_scope]=multiselect&params[item_options:is]=option2&utm_source=mail')),
        );
    }

    #[Test]
    public function livewire_mode_matches_indexable_filters_by_key_field_alias_and_scope()
    {
        $this->withoutCollection();

        $cases = [
            ['item_options:is', '/?params[item_options:is]=option2', ['item_options:is' => 'option2']],
            ['option', '/?params[item_options:is]=option2', ['item_options:is' => 'option2']],
            ['brand', '/?params[taxonomy:brand:any]=bmw', ['taxonomy:brand:any' => 'bmw']],
            ['cat', '/?params[multiselect:item_options]=option1&params[query_scope]=multiselect', ['multiselect:item_options' => 'option1', 'query_scope' => 'multiselect']],
        ];

        foreach ($cases as [$indexable, $uri, $canonical]) {
            $this->useLivewireQueryString([$indexable]);

            $this->assertSame($this->selfCanonical($canonical), $this->seoHead($this->get($uri)), $indexable);
        }
    }

    #[Test]
    public function livewire_mode_rejects_multi_values_and_crafted_scope_keys()
    {
        $this->useLivewireQueryString(['item_options']);
        $this->withoutCollection();

        $this->assertSame($this->baseNoindex, $this->seoHead($this->get('/?params[item_options:is]=option1|option2')));
        // `foo` is no registered scope, so the key is a filter on the `foo` field.
        $this->assertSame($this->baseNoindex, $this->seoHead($this->get('/?params[foo:item_options]=x&params[query_scope]=foo')));
        $this->assertSame(
            $this->selfCanonical(['item_options:is' => 'option2']),
            $this->seoHead($this->get('/?params[item_options:is]=option2&params[query_scope][]=x')),
        );
    }

    #[Test]
    public function livewire_mode_later_pages_point_to_the_base_page_even_for_indexable_filters()
    {
        $this->useLivewireQueryString(['item_options']);

        $this->assertSame($this->baseNoindex, $this->seoHead($this->get('/?params[item_options:is]=option1&page=2')));
        $this->assertSame($this->baseNoindex, $this->seoHead($this->get('/?params[page_name]=p&p=2')));
    }

    #[Test]
    public function without_a_url_mode_only_the_page_number_affects_indexing()
    {
        $this->assertSame('<link rel="canonical" href="http://localhost">', $this->seoHead($this->get('/?params[item_options:is]=option1')));
        $this->assertSame($this->baseNoindex, $this->seoHead($this->get('/?page=2')));
    }
}
