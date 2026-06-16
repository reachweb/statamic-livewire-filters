<?php

namespace Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Livewire\Drawer\Utils;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;
use Reach\StatamicLivewireFilters\Support\Nocache;
use Reach\StatamicLivewireFilters\Tests\FakesViews;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;
use Statamic\Facades\Site;

class NocacheMemoPathTest extends TestCase
{
    use FakesViews, PreventSavingStacheItemsToDisk;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('statamic.system.multisite', true);

        Site::setSites([
            'en' => [
                'name' => 'English',
                'url' => 'http://localhost/',
                'locale' => 'en_US',
            ],
            'amsterdam' => [
                'name' => 'Amsterdam',
                'url' => 'http://localhost/amsterdam',
                'locale' => 'nl_NL',
            ],
        ]);

        Facades\Collection::make('clothes')->sites(['en', 'amsterdam'])->save();

        Facades\Blueprint::make()->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        ['handle' => 'title', 'field' => ['type' => 'text', 'display' => 'Title']],
                    ],
                ],
            ],
        ])->setHandle('clothes')->setNamespace('collections.clothes')->save();

        Site::setCurrent('en');
        EntryFactory::id('shirt-en')->collection('clothes')->slug('shirt')->locale('en')
            ->data(['title' => 'English Shirt'])->create();

        Site::setCurrent('amsterdam');
        EntryFactory::id('shirt-ams')->collection('clothes')->slug('shirt-ams')->locale('amsterdam')
            ->data(['title' => 'Amsterdam Shirt'])->create();
    }

    #[Test]
    public function it_restores_the_real_page_path_in_the_snapshot_when_rendered_in_a_nocache_request()
    {
        $this->fakeCollectionView();

        // Rendered in a {{ nocache }} region: a POST to the nocache endpoint.
        $this->swapNocacheRequest('http://localhost/amsterdam/clothes');
        Site::setCurrent('amsterdam');

        $html = $this->mountCollection();
        $snapshot = $this->snapshotFrom($html);

        // Must be the real page path, not the nocache endpoint ("!/nocache").
        $this->assertSame('amsterdam/clothes', $snapshot['memo']['path']);
        $this->assertSame('GET', $snapshot['memo']['method']);

        // Entries come from the subsite, not the default site.
        $this->assertStringContainsString('Amsterdam Shirt', $html);
        $this->assertStringNotContainsString('English Shirt', $html);
    }

    #[Test]
    public function it_leaves_the_memo_untouched_for_a_regular_request()
    {
        $this->fakeCollectionView();

        // A plain GET already resolves the correct path; the listener must not interfere.
        $this->app->instance('request', Request::create('http://localhost/amsterdam/clothes', 'GET'));
        Site::setCurrent('amsterdam');

        $snapshot = $this->snapshotFrom($this->mountCollection());

        $this->assertSame('amsterdam/clothes', $snapshot['memo']['path']);
        $this->assertSame('GET', $snapshot['memo']['method']);
    }

    #[Test]
    public function it_restores_the_page_path_in_the_snapshot_with_livewire_query_string_mode()
    {
        Config::set('statamic-livewire-filters.enable_query_string', true);

        $this->fakeCollectionView();

        $this->swapNocacheRequest('http://localhost/amsterdam/clothes?colors=red');
        Site::setCurrent('amsterdam');

        $snapshot = $this->snapshotFrom($this->mountCollection());

        $this->assertSame('amsterdam/clothes', $snapshot['memo']['path']);
    }

    #[Test]
    public function it_resolves_the_subsite_on_a_subsequent_livewire_update_using_the_restored_snapshot()
    {
        $this->fakeCollectionView();

        // 1. Render in a nocache region to produce the snapshot.
        $this->swapNocacheRequest('http://localhost/amsterdam/clothes');
        $snapshot = $this->snapshotFrom($this->mountCollection());

        // 2. Replay it to the update endpoint like a pagination click would.
        $this->swapLivewireUpdateRequest($snapshot);
        Site::setCurrent(null);
        Site::resolveCurrentUrlUsing(fn () => Livewire::originalUrl());

        $this->assertSame('amsterdam', Site::current()->handle());
    }

    #[Test]
    public function it_would_resolve_the_default_site_from_the_uncorrected_nocache_endpoint_path()
    {
        // The bug the fix prevents: an uncorrected nocache path resolves the default site.
        $this->swapLivewireUpdateRequest(['memo' => ['path' => '!/nocache']]);
        Site::setCurrent(null);
        Site::resolveCurrentUrlUsing(fn () => Livewire::originalUrl());

        $this->assertSame('en', Site::current()->handle());
    }

    #[Test]
    public function it_resolves_the_original_page_path_from_a_nocache_url()
    {
        $this->assertSame('amsterdam/clothes', Nocache::originalPath($this->nocacheRequest('http://localhost/amsterdam/clothes')));
        $this->assertSame('amsterdam/clothes', Nocache::originalPath($this->nocacheRequest('http://localhost/amsterdam/clothes?page=2')));
        $this->assertSame('/', Nocache::originalPath($this->nocacheRequest('http://localhost/')));
        $this->assertSame('clothes', Nocache::originalPath($this->nocacheRequest('http://localhost/clothes')));
        $this->assertSame('0', Nocache::originalPath($this->nocacheRequest('http://localhost/0')));
        $this->assertNull(Nocache::originalPath($this->nocacheRequest('')));
    }

    #[Test]
    public function it_strips_the_custom_query_string_prefix_from_the_original_page_path()
    {
        Config::set('statamic-livewire-filters.custom_query_string', 'filters');

        $request = $this->nocacheRequest('http://localhost/amsterdam/clothes/filters/colors/red');

        $this->assertSame('amsterdam/clothes', Nocache::originalPath($request));
    }

    #[Test]
    public function it_strips_the_app_base_path_from_the_original_page_path_for_subdirectory_installs()
    {
        // Statamic served from a subdirectory: the nocache "url" (window.location.href)
        // includes the base path, but request()->path() and the Livewire memo do not.
        $request = Request::create(
            'http://localhost/subdir/!/nocache',
            'POST',
            ['url' => 'http://localhost/subdir/amsterdam/clothes'],
            [],
            [],
            [
                'SCRIPT_NAME' => '/subdir/index.php',
                'SCRIPT_FILENAME' => '/var/www/subdir/index.php',
            ],
        );

        // Sanity check: the request is genuinely served from a subdirectory.
        $this->assertSame('/subdir', $request->getBaseUrl());

        // Must be base-relative, otherwise url()->to() doubles the base path on the next update.
        $this->assertSame('amsterdam/clothes', Nocache::originalPath($request));
    }

    protected function fakeCollectionView(): void
    {
        $this->withFakeViews();
        $this->viewShouldReturnRaw('statamic-livewire-filters::livewire.livewire-collection',
            '<div>{{ entries }}<span>{{ title }}</span>{{ /entries }}</div>');
    }

    protected function mountCollection(): string
    {
        return Livewire::mount(LivewireCollection::class, ['params' => ['from' => 'clothes']]);
    }

    protected function snapshotFrom(string $html): array
    {
        return Utils::extractAttributeDataFromHtml($html, 'wire:snapshot');
    }

    protected function swapNocacheRequest(string $url): void
    {
        $this->app->instance('request', $this->nocacheRequest($url));
    }

    protected function swapLivewireUpdateRequest(array $snapshot): void
    {
        $this->app->instance('request', Request::create(
            'http://localhost/livewire/update',
            'POST',
            ['components' => [['snapshot' => json_encode($snapshot)]]],
            [],
            [],
            ['HTTP_X_LIVEWIRE' => '1'],
        ));
    }

    protected function nocacheRequest(string $url): Request
    {
        return Request::create('http://localhost/!/nocache', 'POST', ['url' => $url]);
    }
}
