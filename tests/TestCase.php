<?php

namespace Reach\StatamicLivewireFilters\Tests;

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Statamic\Extend\Manifest;
use Statamic\Facades\Site;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected $fakeStacheDirectory = __DIR__.'/__fixtures__/dev-null';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $uses = array_flip(class_uses_recursive(static::class));

        if (isset($uses[PreventSavingStacheItemsToDisk::class])) {
            $this->preventSavingStacheItemsToDisk();
        }

        Site::setSites([
            'en' => [
                'name' => 'English',
                'url' => 'http://localhost/',
                'locale' => 'en_US',
                'lang' => 'en',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $uses = array_flip(class_uses_recursive(static::class));

        if (isset($uses[PreventSavingStacheItemsToDisk::class])) {
            $this->deleteFakeStacheDirectory();
        }

        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [
            \Statamic\Providers\StatamicServiceProvider::class,
            \Livewire\LivewireServiceProvider::class,
            \Reach\StatamicLivewireFilters\ServiceProvider::class,
            \Wilderborn\Partyline\ServiceProvider::class,
            \Spatie\LaravelRay\RayServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return ['Statamic' => 'Statamic\Statamic'];
    }

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $this->registerLivewireUpdateRoute($app);

        $configs = [
            'assets', 'cp', 'forms', 'routes', 'static_caching',
            'stache', 'system', 'users',
        ];

        foreach ($configs as $config) {
            $app['config']->set("statamic.$config", require (__DIR__."/../vendor/statamic/cms/config/{$config}.php"));
        }
    }

    protected function getEnvironmentSetUp($app)
    {
        $app->make(Manifest::class)->manifest = [
            'reach/statamic-livewire-filters' => [
                'id' => 'reach/statamic-livewire-filters',
                'namespace' => 'Reach\\StatamicLivewireFilters',
            ],
        ];

        $app['config']->set('auth.providers.users.driver', 'statamic');
        $app['config']->set('statamic.stache.watcher', false);
        $app['config']->set('statamic.users.repository', 'file');
        $app['config']->set('statamic.stache.stores.users', [
            'class' => \Statamic\Stache\Stores\UsersStore::class,
            'directory' => __DIR__.'/__fixtures__/users',
        ]);

        $app['config']->set('statamic.stache.stores.taxonomies.directory', __DIR__.'/__fixtures__/content/taxonomies');
        $app['config']->set('statamic.stache.stores.terms.directory', __DIR__.'/__fixtures__/content/taxonomies');
        $app['config']->set('statamic.stache.stores.collections.directory', __DIR__.'/__fixtures__/content/collections');
        $app['config']->set('statamic.stache.stores.entries.directory', __DIR__.'/__fixtures__/content/collections');
        $app['config']->set('statamic.stache.stores.navigation.directory', __DIR__.'/__fixtures__/content/navigation');
        $app['config']->set('statamic.stache.stores.globals.directory', __DIR__.'/__fixtures__/content/globals');
        $app['config']->set('statamic.stache.stores.global-variables.directory', __DIR__.'/__fixtures__/content/globals');
        $app['config']->set('statamic.stache.stores.asset-containers.directory', __DIR__.'/__fixtures__/content/assets');
        $app['config']->set('statamic.stache.stores.nav-trees.directory', __DIR__.'/__fixtures__/content/structures/navigation');
        $app['config']->set('statamic.stache.stores.collection-trees.directory', __DIR__.'/__fixtures__/content/structures/collections');
        $app['config']->set('statamic.stache.stores.form-submissions.directory', __DIR__.'/__fixtures__/content/submissions');

        $app['config']->set('statamic.api.enabled', true);
        $app['config']->set('statamic.graphql.enabled', true);
        $app['config']->set('statamic.editions.pro', true);

        $app['config']->set('cache.stores.outpost', [
            'driver' => 'file',
            'path' => storage_path('framework/cache/outpost-data'),
        ]);

        $app['config']->set('statamic.search.indexes.default.driver', 'null');

        $viewPaths = $app['config']->get('view.paths');
        $viewPaths[] = __DIR__.'/__fixtures__/views/';

        $app['config']->set('view.paths', $viewPaths);
    }

    protected function registerLivewireUpdateRoute($app): void
    {
        // Livewire 3.7.4+ uses app()->booted() to register routes which can cause timing issues in tests.
        // We need to set the update route before Statamic's catch-all route is matched.
        $app->booted(function () {
            Livewire::setUpdateRoute(function ($handle) {
                return Route::post('/livewire/update', $handle)
                    ->middleware('web')
                    ->name('livewire.update');
            });
        });
    }
}
