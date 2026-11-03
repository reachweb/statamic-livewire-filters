<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfCheckboxFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfDualRangeFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfRangeFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

/**
 * Browser-faithful clear-all flows: a filter AND the collection both hear the
 * event, and whatever the filter dispatches to the collection is forwarded.
 * Mirrors the demo /preset page: tag-authored `taxonomy:car_brand:any` plus an
 * lf-checkbox-filter on the same field.
 */
class ClearAllRelayTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    protected function setUp(): void
    {
        parent::setUp();

        Facades\Taxonomy::make('colors')->save();
        Facades\Term::make()->taxonomy('colors')->inDefaultLocale()->slug('red')->data(['title' => 'Red'])->save();
        Facades\Term::make()->taxonomy('colors')->inDefaultLocale()->slug('black')->data(['title' => 'Black'])->save();
        Facades\Collection::make('clothes')->taxonomies(['colors'])->save();

        Facades\Blueprint::make()->setContents(['sections' => ['main' => ['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'display' => 'Title']],
            ['handle' => 'colors', 'field' => ['type' => 'terms', 'taxonomies' => ['colors']]],
            ['handle' => 'max_items', 'field' => ['type' => 'integer', 'display' => 'Max']],
        ]]]])->setHandle('clothes')->setNamespace('collections.clothes')->save();

        EntryFactory::collection('clothes')->slug('red-shirt')->data(['title' => 'Red Shirt', 'colors' => ['red'], 'max_items' => 4])->create();
        EntryFactory::collection('clothes')->slug('black-shirt')->data(['title' => 'Black Shirt', 'colors' => ['black'], 'max_items' => 6])->create();
    }

    protected function forwardToCollection($filter, $collection, string $event): int
    {
        $relays = collect($filter->effects['dispatches'] ?? [])->where('name', $event);

        foreach ($relays as $relay) {
            $collection->dispatch($event, ...$relay['params']);
        }

        return $relays->count();
    }

    /**
     * Range filters read bounds from `bounds-updated` once the collection dispatches
     * it separately (Plan 009 step 4); until that step lands they read `params-updated`.
     */
    protected function boundsEventParams($collection): array
    {
        $dispatches = collect($collection->effects['dispatches'] ?? []);
        $event = $dispatches->firstWhere('name', 'bounds-updated') ?? $dispatches->firstWhere('name', 'params-updated');

        return $event['params'];
    }

    protected function boundsEventName($collection): string
    {
        return collect($collection->effects['dispatches'] ?? [])->firstWhere('name', 'bounds-updated') ? 'bounds-updated' : 'params-updated';
    }

    protected function clearAllEventParams($collection): array
    {
        $event = collect($collection->effects['dispatches'] ?? [])->firstWhere('name', 'clear-all-filters');

        return $event['params'] ?? [];
    }

    public static function clearAllPaths(): array
    {
        return [
            'collection.clearAll() button (/advanced)' => ['collection'],
            'LfTags clear-all-filters event (/preset, /basic)' => ['tags'],
        ];
    }

    /**
     * A tag-authored param that a filter manages must still clear: the sweep
     * exempts it, so the filter's relay is what removes it.
     */
    #[Test]
    #[DataProvider('clearAllPaths')]
    public function clear_all_clears_a_tag_authored_param_that_a_filter_manages(string $path)
    {
        $collection = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'clothes', 'taxonomy:colors:any' => 'red']])
            ->assertSet('params', ['taxonomy:colors:any' => 'red'])
            ->assertSet('clearAllExemptParamKeys', ['taxonomy:colors:any'])
            ->assertSet('entriesCount', 1);

        $filter = Livewire::test(LfCheckboxFilter::class, ['field' => 'colors', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy']);
        $preset = collect($collection->effects['dispatches'])->firstWhere('name', 'preset-params');
        $filter->dispatch('preset-params', ...$preset['params'])->assertSet('selected', ['red']);

        if ($path === 'collection') {
            $collection->call('clearAll');
            $filter->dispatch('clear-all-filters', ...$this->clearAllEventParams($collection));
        } else {
            $collection->dispatch('clear-all-filters');
            $filter->dispatch('clear-all-filters');
        }

        $filter->assertSet('selected', []);
        $this->forwardToCollection($filter, $collection, 'clear-filter');

        $collection->assertSet('params', [])->assertSet('entriesCount', 2);
    }

    /**
     * Init-hook-authored params are exempt from the sweep too; a filter managing one still clears it.
     */
    #[Test]
    public function clear_all_clears_an_init_hook_authored_param_that_a_filter_manages()
    {
        LivewireCollection::hook('init', function ($payload, $next) {
            $this->params['taxonomy:colors:any'] = 'red';

            return $next($payload);
        });

        $collection = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'clothes']])
            ->assertSet('clearAllExemptParamKeys', ['taxonomy:colors:any'])
            ->assertSet('entriesCount', 1);
        $filter = Livewire::test(LfCheckboxFilter::class, ['field' => 'colors', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy'])
            ->set('selected', ['red']);

        $collection->call('clearAll')->assertSet('params', ['taxonomy:colors:any' => 'red']);
        $filter->dispatch('clear-all-filters', ...$this->clearAllEventParams($collection))->assertSet('selected', []);
        $this->assertSame(1, $this->forwardToCollection($filter, $collection, 'clear-filter'));

        $collection->assertSet('params', [])->assertSet('entriesCount', 2);
    }

    /**
     * The round-trip saving: with nothing the collection could not sweep itself,
     * the filters relay nothing (the 3rd POST of /advanced clear-all disappears).
     */
    #[Test]
    public function clear_all_from_the_collection_needs_no_relay_for_runtime_params()
    {
        $collection = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'clothes']]);
        $filter = Livewire::test(LfCheckboxFilter::class, ['field' => 'colors', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy'])
            ->set('selected', ['red']);
        $this->forwardToCollection($filter, $collection, 'filter-updated');
        $collection->assertSet('params', ['taxonomy:colors:any' => 'red'])->assertSet('entriesCount', 1);

        $collection->call('clearAll')->assertSet('params', [])->assertSet('entriesCount', 2);
        $filter->dispatch('clear-all-filters', ...$this->clearAllEventParams($collection))->assertSet('selected', []);

        $this->assertSame(0, $this->forwardToCollection($filter, $collection, 'clear-filter'), 'no clear-filter relay expected');
    }

    #[Test]
    public function the_per_filter_clear_button_still_relays()
    {
        Livewire::test(LfCheckboxFilter::class, ['field' => 'colors', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy'])
            ->set('selected', ['red'])
            ->call('clear')
            ->assertSet('selected', [])
            ->assertDispatched('clear-filter', field: 'colors', condition: 'taxonomy');
    }

    public static function rangeCases(): array
    {
        return [
            'single range, older event first' => [false, false],
            'dual range, older event first' => [true, false],
            'single range, newer event first' => [false, true],
            'dual range, newer event first' => [true, true],
        ];
    }

    /**
     * Through the real collection.clearAll() path (no relay): an older bounds
     * event carrying the active selection arrives after clear-all and must not
     * snap the slider back to it.
     */
    #[Test]
    #[DataProvider('rangeCases')]
    public function a_stale_active_bounds_event_after_clear_all_does_not_snap_back(bool $dualRange, bool $newerFirst)
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        $collection = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'clothes']]);
        $args = $dualRange
            ? ['field' => 'max_items', 'blueprint' => 'clothes.clothes', 'min' => 4, 'max' => 6, 'minRange' => 1]
            : ['field' => 'max_items', 'blueprint' => 'clothes.clothes', 'condition' => 'gte', 'min' => 4, 'max' => 6];
        $filter = Livewire::test($dualRange ? LfDualRangeFilter::class : LfRangeFilter::class, $args)
            ->set($dualRange ? 'selectedMin' : 'selected', 5);

        $this->assertSame(1, $this->forwardToCollection($filter, $collection, 'filter-updated'));
        $olderEvent = $this->boundsEventParams($collection);

        $collection->call('clearAll')->assertSet('params', []);
        $filter->dispatch('clear-all-filters', ...$this->clearAllEventParams($collection))
            ->assertSet($dualRange ? 'selectedMin' : 'selected', 4);
        $this->assertSame(0, $this->forwardToCollection($filter, $collection, 'clear-filter'), 'no relay expected');
        $currentEvent = $this->boundsEventParams($collection);

        $order = $newerFirst ? [$currentEvent, $olderEvent] : [$olderEvent, $currentEvent];

        foreach ($order as $event) {
            $filter->dispatch($this->boundsEventName($collection), ...$event)
                ->assertSet($dualRange ? 'selectedMin' : 'selected', 4)
                ->assertSet('dynamicMin', 4)
                ->assertSet('dynamicMax', 6)
                ->assertNotDispatched('filter-updated');
        }

        // A fresh selection after the clear still round-trips and acknowledges.
        $filter->set($dualRange ? 'selectedMin' : 'selected', 5);
        $this->assertSame(1, $this->forwardToCollection($filter, $collection, 'filter-updated'));
        $ack = $this->boundsEventParams($collection);
        $filter->dispatch($this->boundsEventName($collection), ...$ack)
            ->assertSet($dualRange ? 'selectedMin' : 'selected', 5)
            ->assertSet('pendingBoundsSelection', null);
    }
}
