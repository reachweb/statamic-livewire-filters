<?php

namespace Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;
use Reach\StatamicLivewireFilters\Tests\FakesViews;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class SuppressPaginatorUrlHistoryTest extends TestCase
{
    use FakesViews, PreventSavingStacheItemsToDisk;

    protected function setUp(): void
    {
        parent::setUp();

        Facades\Collection::make('pages')
            ->routes('{parent_uri}/{slug}')
            ->structureContents(['root' => true])
            ->save();

        foreach (['a', 'b', 'c'] as $slug) {
            EntryFactory::id($slug)->collection('pages')->slug($slug)->make()->save();
        }
    }

    private function urlEffect(array $params, string $slot): ?array
    {
        $effects = Livewire::test(LivewireCollection::class, ['params' => $params])->effects;

        return $effects['url'][$slot] ?? null;
    }

    #[Test]
    public function it_removes_the_livewire_paginator_url_effect_in_custom_query_string_mode()
    {
        Config::set('statamic-livewire-filters.custom_query_string', 'filters');

        $effect = $this->urlEffect(['from' => 'pages', 'paginate' => 1], 'paginators.page');

        $this->assertNull($effect);
    }

    #[Test]
    public function it_removes_the_livewire_paginator_url_effect_for_a_custom_page_name()
    {
        Config::set('statamic-livewire-filters.custom_query_string', 'filters');

        $effect = $this->urlEffect(['from' => 'pages', 'paginate' => 1, 'page_name' => 'results'], 'paginators.results');

        $this->assertNull($effect);
    }

    #[Test]
    public function it_keeps_the_livewire_paginator_url_effect_removed_after_an_update()
    {
        Config::set('statamic-livewire-filters.custom_query_string', 'filters');

        $component = Livewire::test(LivewireCollection::class, [
            'params' => ['from' => 'pages', 'paginate' => 1],
        ])->set('paginators.page', 2);

        $this->assertArrayNotHasKey('paginators.page', $component->effects['url'] ?? []);
    }

    #[Test]
    public function it_still_hydrates_the_paginator_from_a_custom_mode_deep_link()
    {
        Config::set('statamic-livewire-filters.custom_query_string', 'filters');

        Livewire::withQueryParams(['page' => 2])
            ->test(LivewireCollection::class, [
                'params' => ['from' => 'pages', 'paginate' => 1],
            ])
            ->assertSet('paginators.page', 2);
    }

    #[Test]
    public function it_keeps_the_livewire_paginator_url_on_push_when_the_livewire_query_string_is_enabled()
    {
        Config::set('statamic-livewire-filters.enable_query_string', true);

        $effects = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'pages', 'paginate' => 1]])->effects;

        $this->assertSame('push', $effects['url']['paginators.page']['use']);
        $this->assertArrayHasKey('params', $effects['url']);
        $this->assertSame('push', $effects['url']['params']['use']);
    }

    #[Test]
    public function it_keeps_the_livewire_paginator_url_on_push_with_the_default_configuration()
    {
        $effect = $this->urlEffect(['from' => 'pages', 'paginate' => 1], 'paginators.page');

        $this->assertNotNull($effect);
        $this->assertSame('push', $effect['use']);
    }
}
