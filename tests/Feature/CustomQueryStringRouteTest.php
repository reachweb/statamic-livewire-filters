<?php

namespace Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Tests\FakesViews;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;
use Statamic\Facades\Site;
use Statamic\StaticCaching\Cacher;
use Statamic\StaticCaching\NoCache\Session;

class CustomQueryStringRouteTest extends TestCase
{
    use FakesViews, PreventSavingStacheItemsToDisk;

    protected function defineEnvironment($app)
    {
        // The filters route is registered at boot, so the prefix must be set before it.
        $app['config']->set('statamic-livewire-filters.custom_query_string', 'filters');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('statamic-livewire-filters.custom_query_string_aliases', ['title' => 'title:contains']);
        Config::set('statamic.system.multisite', true);

        Site::setSites([
            'en' => ['name' => 'English', 'url' => 'http://localhost/', 'locale' => 'en_US'],
            'fr' => ['name' => 'French', 'url' => 'http://localhost/fr/', 'locale' => 'fr_FR'],
        ]);

        Facades\Collection::make('pages')->sites(['en', 'fr'])->routes('{slug}')->save();
        Facades\Collection::make('cars')->sites(['en', 'fr'])->save();

        Facades\Blueprint::make()->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        ['handle' => 'title', 'field' => ['type' => 'text', 'display' => 'Title']],
                    ],
                ],
            ],
        ])->setHandle('cars')->setNamespace('collections.cars')->save();

        EntryFactory::id('cars-en')->collection('pages')->slug('cars')->locale('en')->data(['title' => 'Cars page'])->create();
        EntryFactory::id('cars-fr')->collection('pages')->slug('voitures')->locale('fr')->data(['title' => 'Page voitures'])->create();
        EntryFactory::id('red')->collection('cars')->slug('red')->locale('en')->data(['title' => 'Red Car'])->create();
        EntryFactory::id('blue')->collection('cars')->slug('blue')->locale('en')->data(['title' => 'Blue Car'])->create();
        EntryFactory::id('rouge')->collection('cars')->slug('rouge')->locale('fr')->data(['title' => 'Voiture Rouge'])->create();
        EntryFactory::id('bleue')->collection('cars')->slug('bleue')->locale('fr')->data(['title' => 'Voiture Bleue'])->create();

        $this->withStandardFakeViews();
        $this->viewShouldReturnRaw('default', '{{ title }}: {{ livewire-collection:cars }}');
        $this->viewShouldReturnRaw('statamic-livewire-filters::livewire.livewire-collection', '<div>{{ entries }}{{ title }}, {{ /entries }}</div>');
    }

    #[Test]
    public function it_serves_the_base_page_with_the_url_params_and_keeps_the_pretty_url()
    {
        $this->get('/cars/filters/title/Red')
            ->assertOk()
            ->assertSee('Cars page')
            ->assertSee('Red Car')
            ->assertDontSee('Blue Car');

        $this->assertSame('cars/filters/title/Red', request()->path());
        $this->assertSame(['title:contains' => 'Red'], request()->input('params'));
    }

    #[Test]
    public function it_resolves_the_base_page_on_a_subsite_with_a_path_prefix()
    {
        $this->get('/fr/voitures/filters/title/Rouge')
            ->assertOk()
            ->assertSee('Page voitures')
            ->assertSee('Voiture Rouge')
            ->assertDontSee('Voiture Bleue')
            ->assertDontSee('Red Car');

        $this->assertSame('fr', Site::current()->handle());
    }

    #[Test]
    public function it_returns_404_when_the_base_page_does_not_exist()
    {
        $this->get('/missing/filters/title/Red')->assertNotFound();
        $this->get('/fr/missing/filters/title/Red')->assertNotFound();
        // The English page has no French localization at this URI.
        $this->get('/fr/cars/filters/title/Red')->assertNotFound();
    }

    #[Test]
    public function a_real_page_whose_url_contains_the_prefix_is_served_as_itself()
    {
        EntryFactory::id('filters-page')->collection('pages')->slug('filters')->locale('en')->data(['title' => 'About our filters'])->create();

        $this->get('/filters')
            ->assertOk()
            ->assertSee('About our filters')
            ->assertSee('Red Car')
            ->assertSee('Blue Car');

        $this->assertNull(request()->input('params'));
    }

    #[Test]
    public function the_static_cache_keys_each_filter_url_on_its_own()
    {
        Config::set('statamic.static_caching.strategy', 'half');

        $this->get('/cars/filters/title/Red')->assertSee('Red Car');

        // Under the old path rewrite this was served the cached /cars response.
        $this->get('/cars/filters/title/Blue')->assertSee('Blue Car')->assertDontSee('Red Car');
        $this->get('/cars')->assertSee('Red Car')->assertSee('Blue Car');

        $this->assertEqualsCanonicalizing(
            ['/cars/filters/title/Red', '/cars/filters/title/Blue', '/cars'],
            app(Cacher::class)->getUrls()->values()->all()
        );
    }

    #[Test]
    public function static_cache_exclusions_match_pretty_urls()
    {
        Config::set('statamic.static_caching.strategy', 'half');
        Config::set('statamic.static_caching.exclude.urls', ['/cars/filters/*']);

        $this->get('/cars/filters/title/Red')->assertSee('Red Car');
        $this->get('/cars')->assertSee('Red Car')->assertSee('Blue Car');

        $this->assertSame(['/cars'], app(Cacher::class)->getUrls()->values()->all());
    }

    #[Test]
    public function nocache_regions_on_a_cache_hit_and_on_the_nocache_endpoint_get_the_url_params()
    {
        Config::set('statamic.static_caching.strategy', 'half');
        $this->viewShouldReturnRaw('default', '{{ nocache }}{{ title }}: {{ livewire-collection:cars }}{{ /nocache }}');

        $this->get('/cars/filters/title/Red')->assertSee('Red Car')->assertDontSee('Blue Car');

        // Every real request builds its own nocache session; the test app would reuse it.
        $this->app->forgetInstance(Session::class);

        // Second request is a cache hit: the controller does not run, the regions still filter.
        $this->get('/cars/filters/title/Red')->assertSee('Red Car')->assertDontSee('Blue Car');

        $this->app->forgetInstance(Session::class);

        // The full measure flow: a cached shell asks /!/nocache for its regions by its pretty URL.
        $regions = $this->post('/!/nocache', ['url' => 'http://localhost/cars/filters/title/Blue'])
            ->assertOk()
            ->json('regions');

        $this->assertNotEmpty($regions);
        // The region still sees the base page's cascade.
        $this->assertStringContainsString('Cars page', implode('', $regions));
        $this->assertStringContainsString('Blue Car', implode('', $regions));
        $this->assertStringNotContainsString('Red Car', implode('', $regions));
    }

    #[Test]
    public function it_serves_nothing_extra_when_the_custom_query_string_is_turned_off()
    {
        Config::set('statamic-livewire-filters.custom_query_string', false);

        // The route exists from boot, but with the feature off the URL is just a missing page.
        $this->get('/cars/filters/title/Red')->assertNotFound();
    }
}
