<?php

namespace Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;
use Reach\StatamicLivewireFilters\Http\Livewire\Traits\HandleParams;
use Reach\StatamicLivewireFilters\Http\Middleware\HandleFiltersQueryString;
use Reach\StatamicLivewireFilters\Tests\FakesViews;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class CustomQueryStringTest extends TestCase
{
    use FakesViews, PreventSavingStacheItemsToDisk;

    protected $collection;

    protected $blueprint;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('statamic-livewire-filters.custom_query_string', 'filters');
        Config::set('statamic-livewire-filters.custom_query_string_aliases', [
            'item_options' => 'item_options:is',
            'title' => 'title:contains',
        ]);

        $this->collection = Facades\Collection::make('pages')
            ->routes('{parent_uri}/{slug}')
            ->structureContents([
                'root' => true,
            ])->save();

        $this->blueprint = Facades\Blueprint::make()->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        [
                            'handle' => 'title',
                            'field' => [
                                'type' => 'text',
                                'display' => 'Title',
                            ],
                        ],
                        [

                            'handle' => 'item_options',
                            'field' => [
                                'type' => 'select',
                                'display' => 'Select',
                                'listable' => 'hidden',
                                'options' => [
                                    [
                                        'key' => 'option1',
                                        'value' => 'Option 1',
                                    ],
                                    [
                                        'key' => 'option2',
                                        'value' => 'Option 2',
                                    ],
                                    [
                                        'key' => 'option3',
                                        'value' => 'Option 3',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->blueprint->setHandle('pages')->setNamespace('collections.'.$this->collection->handle())->save();

        $this->makeEntry($this->collection, 'a')->set('title', 'I Love Guitars')->set('item_options', 'option1')->save();
        $this->makeEntry($this->collection, 'b')->set('title', 'I Love Drums')->set('item_options', 'option2')->save();
        $this->makeEntry($this->collection, 'c')->set('title', 'I Hate Flutes')->set('item_options', 'option3')->save();
    }

    protected function makeEntry($collection, $slug)
    {
        return EntryFactory::id($slug)->collection($collection)->slug($slug)->make();
    }

    #[Test]
    public function it_can_load_parameters_from_the_url()
    {
        $this->withStandardFakeViews();

        $this->viewShouldReturnRaw('default', '{{ livewire-collection:pages }}');

        $this->viewShouldReturnRaw('statamic-livewire-filters::livewire.livewire-collection', '<div>{{ entries }} {{ title }} {{ /entries }}</div>');

        $response = $this->get('/filters/item_options/option2');

        // Make sure the params are loaded from the URL
        $this->assertEquals(
            ['item_options:is' => 'option2'],
            request()->query('params')
        );

        // Make sure it ignores the custom query string and loads the original URL
        $this->assertEquals(
            '/',
            request()->path()
        );

        // If the parameter have loaded we should only see item 2
        $response->assertSee('I Love Drums')->assertDontSee('I Love Guitars')->assertDontSee('I Hate Flutes');
    }

    #[Test]
    public function it_decodes_url_encoded_values_on_page_load()
    {
        $this->withStandardFakeViews();

        $this->viewShouldReturnRaw('default', '{{ livewire-collection:pages }}');

        $this->viewShouldReturnRaw('statamic-livewire-filters::livewire.livewire-collection', '<div>{{ entries }} {{ title }} {{ /entries }}</div>');

        // URL with encoded space (%20) - should decode to "I Love"
        $response = $this->get('/filters/title/I%20Love');

        // Make sure the params are decoded from the URL
        $this->assertEquals(
            ['title:contains' => 'I Love'],
            request()->query('params')
        );

        // Should match entries containing "I Love" (decoded value)
        $response->assertSee('I Love Guitars')->assertSee('I Love Drums')->assertDontSee('I Hate Flutes');
    }

    #[Test]
    public function it_dispatched_the_update_url_event_with_the_correct_url()
    {
        $params = [
            'from' => 'pages',
            'title:contains' => 'I Love Guitars',
        ];

        Livewire::test(LivewireCollection::class, ['params' => $params])
            ->dispatch('filter-updated',
                field: 'item_options',
                condition: 'is',
                payload: 'option1',
                command: 'add',
                modifier: 'any',
            )
            ->assertDispatched('update-url')
            ->assertDispatched('update-url', fn ($name, $payload) => str_contains($payload['newUrl'], 'filters/title/I Love Guitars/item_options/option1'));
    }

    #[Test]
    public function it_includes_page_parameter_in_url_when_on_page_greater_than_one()
    {
        $params = [
            'from' => 'pages',
            'paginate' => 1,
            'item_options:is' => 'option1',
        ];

        // Simulate user on page 2 with filters applied
        // The preset-params event triggers updateCustomQueryStringUrl
        Livewire::test(LivewireCollection::class, ['params' => $params])
            ->set('paginators.page', 2)
            ->dispatch('preset-params', ['item_options:is' => 'option1'])
            ->assertDispatched('update-url')
            ->assertDispatched('update-url', fn ($name, $payload) => str_contains($payload['newUrl'], 'page=2'));
    }

    #[Test]
    public function it_does_not_include_page_parameter_when_on_page_one()
    {
        $params = [
            'from' => 'pages',
            'paginate' => 1,
            'item_options:is' => 'option1',
        ];

        // On page 1, no page parameter should be in URL
        Livewire::test(LivewireCollection::class, ['params' => $params])
            ->set('paginators.page', 1)
            ->dispatch('preset-params', ['item_options:is' => 'option1'])
            ->assertDispatched('update-url')
            ->assertDispatched('update-url', fn ($name, $payload) => ! str_contains($payload['newUrl'], 'page='));
    }

    #[Test]
    public function it_combines_filter_path_and_page_parameter_correctly()
    {
        $params = [
            'from' => 'pages',
            'paginate' => 1,
            'item_options:is' => 'option1',
        ];

        // Verify the full URL structure: filter path + query string page parameter
        Livewire::test(LivewireCollection::class, ['params' => $params])
            ->set('paginators.page', 2)
            ->dispatch('preset-params', ['item_options:is' => 'option1'])
            ->assertDispatched('update-url')
            ->assertDispatched('update-url', function ($_, $payload) {
                $url = $payload['newUrl'];

                // URL should contain the filter path segment
                $hasFilterPath = str_contains($url, 'filters/item_options/option1');

                // URL should have properly formatted query string (? not duplicated)
                $hasValidQueryString = str_contains($url, '?page=2');
                $noMalformedUrl = substr_count($url, '?') === 1;

                return $hasFilterPath && $hasValidQueryString && $noMalformedUrl;
            });
    }

    #[Test]
    public function it_writes_the_configured_page_name_in_url_when_on_page_greater_than_one()
    {
        $params = [
            'from' => 'pages',
            'paginate' => 1,
            'page_name' => 'results',
            'item_options:is' => 'option1',
        ];

        // The URL must use the custom page_name, not a dead `page` param.
        Livewire::test(LivewireCollection::class, ['params' => $params])
            ->set('paginators.results', 2)
            ->dispatch('preset-params', ['item_options:is' => 'option1'])
            ->assertDispatched('update-url', fn ($name, $payload) => str_contains($payload['newUrl'], 'results=2')
                && ! str_contains($payload['newUrl'], 'page=2'));
    }

    #[Test]
    public function it_does_not_register_a_second_paginator_query_string_writer()
    {
        $params = [
            'from' => 'pages',
            'paginate' => 1,
            'page_name' => 'results',
        ];

        $component = Livewire::test(LivewireCollection::class, ['params' => $params])->instance();

        $method = new \ReflectionMethod($component, 'queryString');
        $method->setAccessible(true);
        $queryString = $method->invoke($component);

        $this->assertArrayNotHasKey('paginators.results', $queryString);
        $this->assertArrayNotHasKey('paginators.page', $queryString);
    }

    #[Test]
    public function it_resolves_current_path_from_non_livewire_requests_without_dropping_query_parameters()
    {
        $request = Request::create('/horizontal?utm_source=google&utm_medium=cpc&utm_campaign=campaign_name&utm_content=content_id', 'GET');
        $this->app->instance('request', $request);

        $component = $this->makeCurrentPathHarness();

        $currentPath = $component->resolveCurrentPathForTest();

        $this->assertSame('horizontal', strtok($currentPath, '?'));

        parse_str((string) parse_url('http://localhost/'.$currentPath, PHP_URL_QUERY), $query);

        $this->assertEquals([
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'campaign_name',
            'utm_content' => 'content_id',
        ], $query);
    }

    #[Test]
    public function it_uses_the_target_url_when_resolving_path_inside_a_statamic_nocache_request()
    {
        $request = Request::create('/!/nocache', 'POST', [
            'url' => 'http://localhost/basic?utm_source=newsletter',
        ]);
        $this->app->instance('request', $request);

        $component = $this->makeCurrentPathHarness();

        $currentPath = $component->resolveCurrentPathForTest();

        $this->assertSame('basic', strtok($currentPath, '?'));

        parse_str((string) parse_url('http://localhost/'.$currentPath, PHP_URL_QUERY), $query);

        $this->assertEquals(['utm_source' => 'newsletter'], $query);
    }

    #[Test]
    public function it_strips_filter_segments_from_the_url_input_on_statamic_nocache_requests()
    {
        $middleware = new HandleFiltersQueryString;

        $request = Request::create('/!/nocache', 'POST', [
            'url' => 'http://localhost/basic/filters/item_options/option1,option2?utm_source=newsletter',
        ]);

        $middleware->handle($request, fn ($r) => $r);

        $this->assertSame(
            'http://localhost/basic?utm_source=newsletter',
            $request->input('url')
        );

        $this->assertEquals(
            ['item_options:is' => 'option1|option2'],
            $request->input('params')
        );
    }

    #[Test]
    public function it_leaves_the_nocache_url_input_alone_when_it_has_no_filter_segments()
    {
        $middleware = new HandleFiltersQueryString;

        $request = Request::create('/!/nocache', 'POST', [
            'url' => 'http://localhost/basic?utm_source=newsletter',
        ]);

        $middleware->handle($request, fn ($r) => $r);

        $this->assertSame(
            'http://localhost/basic?utm_source=newsletter',
            $request->input('url')
        );
    }

    #[Test]
    public function it_rehydrates_livewire_query_string_params_on_statamic_nocache_requests()
    {
        Config::set('statamic-livewire-filters.enable_query_string', true);

        $middleware = new HandleFiltersQueryString;

        $request = Request::create('/!/nocache', 'POST', [
            'url' => 'http://localhost/basic?params[item_options:is]=option1&utm_source=newsletter',
        ]);

        $middleware->handle($request, fn ($r) => $r);

        $this->assertEquals(
            ['item_options:is' => 'option1'],
            $request->query('params')
        );

        $this->assertSame('newsletter', $request->query('utm_source'));

        $this->assertSame(
            'http://localhost/basic?params[item_options:is]=option1&utm_source=newsletter',
            $request->input('url')
        );
    }

    #[Test]
    public function it_never_overwrites_reserved_inputs_when_rehydrating_the_query_string()
    {
        Config::set('statamic-livewire-filters.enable_query_string', true);

        $middleware = new HandleFiltersQueryString;

        $request = Request::create('/!/nocache', 'POST', [
            'url' => 'http://localhost/basic?url=http://evil.test&_token=fake&params[item_options:is]=option1',
        ]);

        $middleware->handle($request, fn ($r) => $r);

        $this->assertSame(
            'http://localhost/basic?url=http://evil.test&_token=fake&params[item_options:is]=option1',
            $request->input('url')
        );

        $this->assertFalse($request->query->has('url'));
        $this->assertFalse($request->query->has('_token'));

        $this->assertEquals(
            ['item_options:is' => 'option1'],
            $request->query('params')
        );
    }

    #[Test]
    public function it_keeps_the_root_slash_when_stripping_filter_segments_on_nocache_requests()
    {
        $middleware = new HandleFiltersQueryString;

        $request = Request::create('/!/nocache', 'POST', [
            'url' => 'http://localhost/filters/item_options/option1,option2',
        ]);

        $middleware->handle($request, fn ($r) => $r);

        $this->assertSame('http://localhost/', $request->input('url'));

        $this->assertEquals(
            ['item_options:is' => 'option1|option2'],
            $request->input('params')
        );
    }

    #[Test]
    public function it_keeps_the_root_slash_and_query_when_stripping_filter_segments_on_nocache_requests()
    {
        $middleware = new HandleFiltersQueryString;

        $request = Request::create('/!/nocache', 'POST', [
            'url' => 'http://localhost/filters/item_options/option1?utm_source=newsletter',
        ]);

        $middleware->handle($request, fn ($r) => $r);

        $this->assertSame('http://localhost/?utm_source=newsletter', $request->input('url'));
    }

    #[Test]
    public function it_ignores_nocache_requests_when_no_query_string_mode_is_active()
    {
        Config::set('statamic-livewire-filters.custom_query_string', false);

        $middleware = new HandleFiltersQueryString;

        $request = Request::create('/!/nocache', 'POST', [
            'url' => 'http://localhost/basic/filters/item_options/option1',
        ]);

        $middleware->handle($request, fn ($r) => $r);

        $this->assertSame(
            'http://localhost/basic/filters/item_options/option1',
            $request->input('url')
        );

        $this->assertNull($request->input('params'));
    }

    #[Test]
    public function it_skips_processing_when_the_custom_query_string_is_not_a_string()
    {
        Config::set('statamic-livewire-filters.custom_query_string', true);

        $middleware = new HandleFiltersQueryString;

        $request = Request::create('/blog/filters/item_options/option1', 'GET');

        $middleware->handle($request, fn ($r) => $r);

        $this->assertSame('blog/filters/item_options/option1', $request->path());
        $this->assertNull($request->input('params'));
    }

    #[Test]
    public function it_does_not_dispatch_update_url_when_the_livewire_query_string_is_enabled()
    {
        Config::set('statamic-livewire-filters.enable_query_string', true);

        $component = $this->makeUrlHandlerHarness(
            params: [
                'from' => 'pages',
                'item_options:is' => 'option1',
            ],
            currentPath: 'horizontal'
        );

        $component->updateCustomQueryStringUrl();

        $this->assertEmpty(
            collect($component->dispatches)->where('name', 'update-url')
        );
    }

    #[Test]
    public function it_does_not_strip_the_custom_prefix_from_paths_when_the_livewire_query_string_is_enabled()
    {
        Config::set('statamic-livewire-filters.enable_query_string', true);

        $request = Request::create('/livewire/update', 'POST');
        $request->headers->set('X-Livewire', 'true');
        $request->headers->set('Referer', 'http://localhost/blog/filters/something');
        $this->app->instance('request', $request);

        $component = $this->makeCurrentPathHarness();

        $this->assertSame('blog/filters/something', $component->resolveCurrentPathForTest());
    }

    #[Test]
    public function it_preserves_existing_query_parameters_when_building_custom_urls()
    {
        $component = $this->makeUrlHandlerHarness(
            params: [
                'from' => 'pages',
                'item_options:is' => 'option1|option2',
            ],
            currentPath: 'horizontal?utm_source=google&utm_medium=cpc&utm_campaign=campaign_name&utm_content=content_id'
        );

        $component->updateCustomQueryStringUrl();

        $updateUrl = collect($component->dispatches)->last(fn ($dispatch) => $dispatch['name'] === 'update-url');

        $this->assertNotNull($updateUrl);

        $url = parse_url($updateUrl['params']['newUrl']);

        parse_str($url['query'] ?? '', $query);

        $this->assertEquals('/horizontal/filters/item_options/option1,option2', $url['path'] ?? null);
        $this->assertEquals([
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'campaign_name',
            'utm_content' => 'content_id',
        ], $query);
    }

    #[Test]
    public function it_removes_stale_page_query_parameters_when_back_on_page_one()
    {
        $component = $this->makeUrlHandlerHarness(
            params: [
                'from' => 'pages',
                'paginate' => 1,
                'item_options:is' => 'option1',
            ],
            currentPath: 'horizontal?page=2&utm_source=google',
            page: 1
        );

        $component->updateCustomQueryStringUrl();

        $updateUrl = collect($component->dispatches)->last(fn ($dispatch) => $dispatch['name'] === 'update-url');

        $this->assertNotNull($updateUrl);

        $url = parse_url($updateUrl['params']['newUrl']);

        parse_str($url['query'] ?? '', $query);

        $this->assertEquals('/horizontal/filters/item_options/option1', $url['path'] ?? null);
        $this->assertArrayNotHasKey('page', $query);
        $this->assertSame('google', $query['utm_source'] ?? null);
    }

    #[Test]
    public function it_removes_legacy_paginator_property_paths_from_custom_urls()
    {
        $component = $this->makeUrlHandlerHarness(
            params: [
                'from' => 'pages',
                'paginate' => 1,
                'page_name' => 'results',
            ],
            currentPath: 'horizontal?paginators.results=1&paginators[results]=1&utm_source=google',
            page: 1
        );

        $component->updateCustomQueryStringUrl();

        $updateUrl = collect($component->dispatches)->last(fn ($dispatch) => $dispatch['name'] === 'update-url');

        $this->assertNotNull($updateUrl);

        $url = parse_url($updateUrl['params']['newUrl']);

        parse_str($url['query'] ?? '', $query);

        $this->assertArrayNotHasKey('paginators_results', $query);
        $this->assertArrayNotHasKey('paginators', $query);
        $this->assertArrayNotHasKey('results', $query);
        $this->assertSame('google', $query['utm_source'] ?? null);
    }

    #[Test]
    public function it_replaces_history_when_canonicalizing_an_initial_request()
    {
        $component = $this->makeUrlHandlerHarness(
            params: [
                'from' => 'pages',
                'paginate' => 1,
            ],
            currentPath: 'horizontal?paginators.page=1',
        );

        $component->updateCustomQueryStringUrl();

        $updateUrl = collect($component->dispatches)->last(fn ($dispatch) => $dispatch['name'] === 'update-url');

        $this->assertNotNull($updateUrl);
        $this->assertTrue($updateUrl['params']['replace']);
    }

    #[Test]
    public function it_pushes_history_for_a_livewire_request()
    {
        $request = Request::create('/livewire/update', 'POST');
        $request->headers->set('X-Livewire', 'true');
        $this->app->instance('request', $request);

        $component = $this->makeUrlHandlerHarness(
            params: [
                'from' => 'pages',
                'paginate' => 1,
            ],
            currentPath: 'horizontal',
            page: 2,
        );

        $component->updateCustomQueryStringUrl();

        $updateUrl = collect($component->dispatches)->last(fn ($dispatch) => $dispatch['name'] === 'update-url');

        $this->assertNotNull($updateUrl);
        $this->assertFalse($updateUrl['params']['replace']);
    }

    #[Test]
    public function it_preserves_existing_page_query_parameters_when_the_component_is_not_paginated()
    {
        $component = $this->makeUrlHandlerHarness(
            params: [
                'from' => 'pages',
                'item_options:is' => 'option1',
            ],
            currentPath: 'horizontal?page=4&utm_source=google',
            page: 1
        );

        $component->updateCustomQueryStringUrl();

        $updateUrl = collect($component->dispatches)->last(fn ($dispatch) => $dispatch['name'] === 'update-url');

        $this->assertNotNull($updateUrl);

        $url = parse_url($updateUrl['params']['newUrl']);

        parse_str($url['query'] ?? '', $query);

        $this->assertEquals('/horizontal/filters/item_options/option1', $url['path'] ?? null);
        $this->assertSame('4', $query['page'] ?? null);
        $this->assertSame('google', $query['utm_source'] ?? null);
    }

    #[Test]
    public function it_parses_filter_params_from_livewire_request_referer()
    {
        $middleware = new HandleFiltersQueryString;

        $request = Request::create('/livewire/update', 'POST');
        $request->headers->set('X-Livewire', 'true');
        $request->headers->set('Referer', 'http://localhost/filters/item_options/option2');

        $middleware->handle($request, function ($req) {
            // The middleware should have parsed filter params from the Referer
            $this->assertEquals(
                ['item_options:is' => 'option2'],
                $req->input('params')
            );

            // The request path should NOT be rewritten for Livewire requests
            $this->assertEquals('livewire/update', $req->path());

            return response('ok');
        });
    }

    #[Test]
    public function it_does_not_rewrite_request_path_for_livewire_requests()
    {
        $middleware = new HandleFiltersQueryString;

        $request = Request::create('/livewire/update', 'POST');
        $request->headers->set('X-Livewire', 'true');
        $request->headers->set('Referer', 'http://localhost/blog/filters/item_options/option1/title/I%20Love');

        $middleware->handle($request, function ($req) {
            // Should parse multiple filter params
            $this->assertEquals(
                ['item_options:is' => 'option1', 'title:contains' => 'I Love'],
                $req->input('params')
            );

            // Request path must remain unchanged for Livewire to route correctly
            $this->assertEquals('livewire/update', $req->path());

            return response('ok');
        });
    }

    protected function makeCurrentPathHarness(): object
    {
        return new class extends LivewireCollection
        {
            public function resolveCurrentPathForTest(): string
            {
                return $this->resolveCurrentPath();
            }
        };
    }

    protected function makeUrlHandlerHarness(array $params, string $currentPath, int $page = 1): object
    {
        return new class($params, $currentPath, $page)
        {
            use HandleParams;

            public array $params;

            public string $currentPath;

            public bool|int $paginate = false;

            public array $dispatches = [];

            protected int $page;

            public function __construct(array $params, string $currentPath, int $page)
            {
                $this->params = $params;
                $this->currentPath = $currentPath;
                $this->paginate = $params['paginate'] ?? false;
                $this->page = $page;
            }

            public function getPage(): int
            {
                return $this->page;
            }

            public function dispatch($name, ...$params): static
            {
                $this->dispatches[] = [
                    'name' => $name,
                    'params' => $params,
                ];

                return $this;
            }
        };
    }
}
