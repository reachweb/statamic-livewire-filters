<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature\Tags;

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
    }

    protected function visit(string $uri): TestResponse
    {
        $this->viewShouldReturnRaw('layout', '<head>{{ livewire-filters:seo }}</head>{{ template_content }}');

        return $this->get($uri)->assertOk();
    }

    protected function seoHead(TestResponse $response): string
    {
        $html = $response->getContent();

        return str_replace(["\r", "\n"], '', substr($html, 6, strpos($html, '</head>') - 6));
    }

    protected function useCustomQueryString(): void
    {
        Config::set('statamic-livewire-filters.custom_query_string', 'filters');
        Config::set('statamic-livewire-filters.seo_filter_headers', true);
    }

    #[Test]
    public function custom_mode_head_only_carries_page_dependent_tags()
    {
        $this->useCustomQueryString();

        $this->assertSame('', $this->seoHead($this->visit('/')));
        $this->assertSame(
            '<link rel="canonical" href="http://localhost"><meta name="robots" content="noindex,follow">',
            $this->seoHead($this->visit('/?page=2')),
        );
    }

    #[Test]
    public function custom_mode_filter_urls_send_noindex_and_base_canonical_headers()
    {
        $this->useCustomQueryString();

        $response = $this->visit('/filters/option/option1');

        $this->assertSame('', $this->seoHead($response));
        $response->assertHeader('X-Robots-Tag', 'noindex, follow');
        $response->assertHeader('Link', '<http://localhost>; rel="canonical"');

        $this->visit('/')->assertHeaderMissing('X-Robots-Tag')->assertHeaderMissing('Link');
    }

    #[Test]
    public function custom_mode_later_filtered_pages_are_noindex_without_a_conflicting_canonical_header()
    {
        $this->useCustomQueryString();
        Config::set('statamic-livewire-filters.seo_indexable_filters', ['option']);

        $this->visit('/filters/option/option1?page=2')
            ->assertHeader('X-Robots-Tag', 'noindex, follow')
            ->assertHeaderMissing('Link');
    }

    #[Test]
    public function custom_mode_single_value_of_an_indexable_filter_gets_a_self_canonical_header()
    {
        $this->useCustomQueryString();
        Config::set('statamic-livewire-filters.seo_indexable_filters', ['brand', 'option']);

        $this->visit('/filters/option/option2')
            ->assertHeaderMissing('X-Robots-Tag')
            ->assertHeader('Link', '<http://localhost/filters/option/option2>; rel="canonical"');

        foreach (['/filters/option/option1,option2', '/filters/option/option1/title/Entry'] as $uri) {
            $this->visit($uri)
                ->assertHeader('X-Robots-Tag', 'noindex, follow')
                ->assertHeader('Link', '<http://localhost>; rel="canonical"');
        }
    }

    #[Test]
    public function custom_mode_headers_are_sent_on_static_cache_hits_of_the_shared_page()
    {
        $this->useCustomQueryString();
        Config::set('statamic.static_caching.strategy', 'half');

        $this->visit('/filters/option/option1')->assertHeader('X-Robots-Tag', 'noindex, follow');

        // The base page is served from the entry the filter URL created, and stays indexable.
        $this->viewShouldReturnRaw('default', 'changed');
        $base = $this->get('/')->assertOk()->assertDontSee('changed')->assertHeaderMissing('X-Robots-Tag');
        $this->assertSame('', $this->seoHead($base));

        $this->get('/filters/option/option2')->assertDontSee('changed')->assertHeader('X-Robots-Tag', 'noindex, follow');
    }

    #[Test]
    public function custom_mode_headers_are_opt_in()
    {
        Config::set('statamic-livewire-filters.custom_query_string', 'filters');

        $this->visit('/filters/option/option1')->assertHeaderMissing('X-Robots-Tag')->assertHeaderMissing('Link');
    }

    #[Test]
    public function livewire_mode_strips_params_from_the_canonical_of_filtered_pages()
    {
        Config::set('statamic-livewire-filters.enable_query_string', true);

        $this->assertSame(
            '<link rel="canonical" href="http://localhost"><meta name="robots" content="noindex,follow">',
            $this->seoHead($this->visit('/?params[item_options:is]=option1&utm_source=mail')),
        );
    }

    #[Test]
    public function livewire_mode_single_value_of_an_indexable_filter_keeps_a_self_canonical()
    {
        Config::set('statamic-livewire-filters.enable_query_string', true);
        Config::set('statamic-livewire-filters.seo_indexable_filters', ['item_options']);

        $this->assertSame(
            '<link rel="canonical" href="'.e('http://localhost?'.http_build_query(['params' => ['item_options:is' => 'option2']])).'">',
            $this->seoHead($this->visit('/?params[item_options:is]=option2&utm_source=mail')),
        );
    }

    #[Test]
    public function without_a_url_mode_only_the_page_number_affects_indexing()
    {
        $this->assertSame('<link rel="canonical" href="http://localhost">', $this->seoHead($this->visit('/?params[item_options:is]=option1')));
        $this->assertSame(
            '<link rel="canonical" href="http://localhost"><meta name="robots" content="noindex,follow">',
            $this->seoHead($this->visit('/?page=2')),
        );
    }
}
