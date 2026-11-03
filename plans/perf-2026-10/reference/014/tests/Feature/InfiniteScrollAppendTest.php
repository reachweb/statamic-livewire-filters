<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection as LivewireCollectionComponent;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

/**
 * `infinite_scroll="append"`: load more sends only the newest page; pages the
 * client already holds are empty `wire:ignore` shells keyed by generation+page.
 * Titles sort A..G so page boundaries are deterministic (paginate=2).
 */
class InfiniteScrollAppendTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    protected array $params = ['from' => 'clothes', 'paginate' => 2, 'infinite_scroll' => 'append'];

    protected function setUp(): void
    {
        parent::setUp();

        Facades\Collection::make('clothes')->save();

        foreach (['B', 'C', 'D', 'E', 'F'] as $letter) {
            EntryFactory::collection('clothes')->slug(strtolower($letter).'-shirt')->data(['title' => "$letter Shirt"])->create();
        }
    }

    protected function titlesIn(string $html): array
    {
        preg_match_all('/([A-Z]) Shirt/', $html, $m);

        return $m[1];
    }

    /**
     * The page shells of a response, in order, with the titles each one carries.
     *
     * @return array<string, array<int, string>>
     */
    protected function shellsIn(string $html): array
    {
        $chunks = preg_split('/(?=<div wire:key="lf-page-)/', $html);
        array_shift($chunks);

        $shells = [];
        foreach ($chunks as $chunk) {
            preg_match('/^<div wire:key="(lf-page-\d+-\d+)" wire:ignore>/', $chunk, $m);
            $shells[$m[1]] = $this->titlesIn($chunk);
        }

        return $shells;
    }

    /**
     * Model of the client DOM under Livewire's keyed morph with wire:ignore: a
     * shell already in the DOM keeps its content, a new shell takes the response's
     * content, and a shell missing from the response is removed.
     *
     * @param  array<string, array<int, string>>  $dom
     * @return array<string, array<int, string>>
     */
    protected function morph(array $dom, string $html): array
    {
        $next = [];
        foreach ($this->shellsIn($html) as $key => $titles) {
            $next[$key] = $dom[$key] ?? $titles;
        }

        return $next;
    }

    #[Test]
    public function append_mode_is_opt_in_and_the_boolean_flag_keeps_growing_the_page_size()
    {
        $grow = Livewire::test(LivewireCollectionComponent::class, ['params' => ['from' => 'clothes', 'paginate' => 2, 'infinite_scroll' => true]])
            ->assertSet('infiniteScroll', true)
            ->assertSet('appendPages', false)
            ->call('loadMore')
            ->assertSet('paginate', 4)
            ->assertSet('loadedPages', 1);
        $this->assertStringNotContainsString('lf-page-', $grow->html());
        $this->assertSame(['B', 'C', 'D', 'E'], $this->titlesIn($grow->html()));

        $append = Livewire::test(LivewireCollectionComponent::class, ['params' => $this->params])
            ->assertSet('infiniteScroll', true)
            ->assertSet('appendPages', true)
            ->assertSet('initialPaginate', 2)
            ->call('loadMore')
            ->assertSet('paginate', 2)
            ->assertSet('loadedPages', 2);
        $this->assertSame(['lf-page-0-1' => [], 'lf-page-0-2' => ['D', 'E']], $this->shellsIn($append->html()));
    }

    #[Test]
    public function append_mode_cannot_be_enabled_from_url_state()
    {
        config()->set('statamic-livewire-filters.enable_query_string', true);

        Livewire::withQueryParams(['params' => ['infinite_scroll' => 'append']])
            ->test(LivewireCollectionComponent::class, ['params' => ['from' => 'clothes', 'paginate' => 2]])
            ->assertSet('infiniteScroll', false)
            ->assertSet('appendPages', false);
    }

    /**
     * A load-more response carries only the newest page. The `offset` override
     * must survive `blocked_query_params` (it is applied after the strip).
     */
    #[Test]
    public function load_more_renders_only_the_newest_page_with_the_right_offset()
    {
        $component = Livewire::test(LivewireCollectionComponent::class, ['params' => $this->params]);
        $this->assertSame(['B', 'C'], $this->titlesIn($component->html()));
        $component->assertSet('hasMorePages', true)->assertSet('entriesCount', 5);

        $component->call('loadMore');
        $this->assertSame(['D', 'E'], $this->titlesIn($component->html()));
        $component->assertSet('loadedPages', 2)->assertSet('paginate', 2)->assertSet('entriesCount', 5)->assertSet('hasMorePages', true);

        $component->call('loadMore');
        $this->assertSame(['F'], $this->titlesIn($component->html()));
        $component->assertSet('loadedPages', 3)->assertSet('hasMorePages', false);
    }

    #[Test]
    public function has_more_pages_is_false_once_an_exact_multiple_of_the_page_size_is_loaded()
    {
        EntryFactory::collection('clothes')->slug('g-shirt')->data(['title' => 'G Shirt'])->create();

        // 6 entries, page size 2: pages 1..3, then no empty 4th request.
        Livewire::test(LivewireCollectionComponent::class, ['params' => $this->params])
            ->assertSet('hasMorePages', true)
            ->call('loadMore')->assertSet('hasMorePages', true)
            ->call('loadMore')->assertSet('hasMorePages', false)->assertSet('loadedPages', 3)
            ->call('loadMore')->assertSet('hasMorePages', false)->assertSet('loadedPages', 3);
    }

    #[Test]
    public function the_view_receives_loaded_count_and_per_page_in_both_modes()
    {
        $append = Livewire::test(LivewireCollectionComponent::class, ['params' => $this->params]);
        $this->assertSame(2, $append->viewData('loaded_count'));
        $this->assertSame(2, $append->viewData('per_page'));
        $this->assertSame(5, $append->viewData('pagination_total'));
        $this->assertTrue($append->viewData('append_pages'));

        $append->call('loadMore');
        $this->assertSame(4, $append->viewData('loaded_count'));
        $this->assertSame(5, $append->viewData('pagination_total'));

        $grow = Livewire::test(LivewireCollectionComponent::class, ['params' => ['from' => 'clothes', 'paginate' => 2, 'infinite_scroll' => true]])
            ->call('loadMore');
        $this->assertSame(4, $grow->viewData('loaded_count'));
        $this->assertSame(2, $grow->viewData('per_page'));
        $this->assertFalse($grow->viewData('append_pages'));
    }

    #[Test]
    public function filter_sort_and_clear_all_bump_the_generation_and_reset_to_page_one()
    {
        $component = Livewire::test(LivewireCollectionComponent::class, ['params' => $this->params])
            ->call('loadMore')
            ->assertSet('loadedPages', 2);
        $generation = $component->get('pageGeneration');

        $component->dispatch('filter-updated', field: 'title', condition: 'contains', payload: 'Shirt', modifier: 'any')
            ->assertSet('loadedPages', 1);
        $this->assertGreaterThan($generation, $g1 = $component->get('pageGeneration'));
        $this->assertSame(['lf-page-'.$g1.'-1' => ['B', 'C']], $this->shellsIn($component->html()));

        $component->call('loadMore')->dispatch('sort-updated', sort: 'title:desc')->assertSet('loadedPages', 1);
        $this->assertGreaterThan($g1, $g2 = $component->get('pageGeneration'));
        $this->assertSame(['F', 'E'], $this->titlesIn($component->html()));

        $component->call('loadMore')->dispatch('clear-all-filters')->assertSet('loadedPages', 1);
        $this->assertGreaterThan($g2, $component->get('pageGeneration'));
    }

    #[Test]
    public function clear_all_without_active_filters_still_resets_the_loaded_pages()
    {
        $component = Livewire::test(LivewireCollectionComponent::class, ['params' => $this->params])
            ->call('loadMore')
            ->call('loadMore')
            ->assertSet('loadedPages', 3);
        $generation = $component->get('pageGeneration');

        $component->dispatch('clear-all-filters')->assertSet('loadedPages', 1);
        $this->assertGreaterThan($generation, $component->get('pageGeneration'));
        $this->assertSame(['B', 'C'], $this->titlesIn($component->html()));
    }

    /**
     * The total is recounted on every request, so an entry published between
     * two load-more clicks shows up in the count (and in has_more_pages).
     */
    #[Test]
    public function entries_count_reflects_an_entry_published_between_load_more_clicks()
    {
        $component = Livewire::test(LivewireCollectionComponent::class, ['params' => $this->params])
            ->call('loadMore')
            ->assertSet('entriesCount', 5);

        EntryFactory::collection('clothes')->slug('a-shirt')->data(['title' => 'A Shirt'])->create();

        $component->call('loadMore')->assertSet('entriesCount', 6);
    }

    /**
     * Offset paging drifts when an entry is inserted before the current offset.
     * A changed total re-sends every loaded page under a new generation, so the
     * client DOM is replaced instead of receiving a page that repeats an entry.
     */
    #[Test]
    public function load_more_never_leaves_a_duplicate_entry_in_the_client_dom()
    {
        $component = Livewire::test(LivewireCollectionComponent::class, ['params' => $this->params]);
        $dom = $this->morph([], $component->html());

        $component->call('loadMore');
        $dom = $this->morph($dom, $component->html());
        $this->assertSame(['lf-page-0-1' => ['B', 'C'], 'lf-page-0-2' => ['D', 'E']], $dom);

        EntryFactory::collection('clothes')->slug('a-shirt')->data(['title' => 'A Shirt'])->create();

        $component->call('loadMore')->assertSet('pageGeneration', 1)->assertSet('loadedPages', 3)->assertSet('hasMorePages', false);
        $dom = $this->morph($dom, $component->html());

        $this->assertSame(['lf-page-1-1' => [], 'lf-page-1-2' => [], 'lf-page-1-3' => ['A', 'B', 'C', 'D', 'E', 'F']], $dom);
        $flat = array_merge(...array_values($dom));
        $this->assertSame($flat, array_values(array_unique($flat)), 'client DOM holds a duplicate: '.implode(',', $flat));
    }

    #[Test]
    public function an_entry_unpublished_between_clicks_does_not_leave_a_gap_in_the_client_dom()
    {
        $component = Livewire::test(LivewireCollectionComponent::class, ['params' => $this->params]);
        $dom = $this->morph([], $component->html());
        $component->call('loadMore');
        $dom = $this->morph($dom, $component->html());

        Facades\Entry::query()->where('slug', 'c-shirt')->first()->delete();

        $component->call('loadMore')->assertSet('entriesCount', 4)->assertSet('hasMorePages', false);
        $dom = $this->morph($dom, $component->html());

        $this->assertSame(['B', 'D', 'E', 'F'], array_merge(...array_values($dom)));
    }

    #[Test]
    public function a_steady_result_set_keeps_appending_after_a_heal()
    {
        $component = Livewire::test(LivewireCollectionComponent::class, ['params' => ['from' => 'clothes', 'paginate' => 1, 'infinite_scroll' => 'append']]);
        $dom = $this->morph([], $component->html());
        $component->call('loadMore');
        $dom = $this->morph($dom, $component->html());

        EntryFactory::collection('clothes')->slug('a-shirt')->data(['title' => 'A Shirt'])->create();

        $component->call('loadMore')->assertSet('pageGeneration', 1)->assertSet('loadedPages', 3);
        $dom = $this->morph($dom, $component->html());
        $this->assertSame(['A', 'B', 'C'], array_merge(...array_values($dom)));

        $component->call('loadMore')->assertSet('pageGeneration', 1)->assertSet('loadedPages', 4);
        $this->assertSame(['D'], $this->titlesIn($component->html()));
        $dom = $this->morph($dom, $component->html());
        $this->assertSame(['A', 'B', 'C', 'D'], array_merge(...array_values($dom)));
    }
}
