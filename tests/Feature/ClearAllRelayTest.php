<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Support\Facades\Config;
use Livewire\Attributes\On;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfCheckboxFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfDualRangeFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfRangeFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfTextFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfToggleFilter;
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

    protected function boundsEventParams($collection): array
    {
        return collect($collection->effects['dispatches'] ?? [])->firstWhere('name', 'params-updated')['params'];
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
            $filter->dispatch('params-updated', ...$event)
                ->assertSet($dualRange ? 'selectedMin' : 'selected', 4)
                ->assertSet('dynamicMin', 4)
                ->assertSet('dynamicMax', 6)
                ->assertNotDispatched('filter-updated');
        }

        // A fresh selection after the clear still round-trips and acknowledges.
        $filter->set($dualRange ? 'selectedMin' : 'selected', 5);
        $this->assertSame(1, $this->forwardToCollection($filter, $collection, 'filter-updated'));
        $ack = $this->boundsEventParams($collection);
        $filter->dispatch('params-updated', ...$ack)
            ->assertSet($dualRange ? 'selectedMin' : 'selected', 5)
            ->assertSet('pendingBoundsSelection', null);
    }

    /**
     * Only a filter whose own key is in the kept list relays; filters on other fields,
     * including a dual-range filter whose min/max keys aren't listed, stay silent.
     */
    #[Test]
    public function only_the_filter_managing_a_kept_key_relays()
    {
        $collection = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'clothes', 'taxonomy:colors:any' => 'red']]);
        $text = Livewire::test(LfTextFilter::class, ['field' => 'title', 'blueprint' => 'clothes.clothes', 'condition' => 'contains'])
            ->set('selected', 'Shirt');
        $this->forwardToCollection($text, $collection, 'filter-updated');

        $collection->call('clearAll');
        $kept = $this->clearAllEventParams($collection);
        $this->assertSame(['taxonomy:colors:any'], $kept[0]);
        $this->assertSame(['title:contains' => 'Shirt'], $kept[1]);

        $text->dispatch('clear-all-filters', ...$kept)
            ->assertSet('selected', '')
            ->assertNotDispatched('clear-filter');

        $dual = ['field' => 'max_items', 'blueprint' => 'clothes.clothes', 'min' => 4, 'max' => 6, 'minRange' => 1];

        Livewire::test(LfDualRangeFilter::class, $dual)
            ->dispatch('clear-all-filters', ['taxonomy:colors:any'])
            ->assertNotDispatched('clear-filter');

        Livewire::test(LfDualRangeFilter::class, $dual)
            ->dispatch('clear-all-filters', ['max_items:lte'])
            ->assertDispatched('clear-filter', field: 'max_items', condition: 'dual_range');
    }

    /**
     * clear() declares no parameter, so a subclass that overrides it without one still loads,
     * and passing the kept list through still skips the relay.
     */
    #[Test]
    public function a_subclass_overriding_clear_without_parameters_still_works()
    {
        $args = ['field' => 'colors', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy'];

        Livewire::test(ClearOverridingCheckboxFilter::class, $args)
            ->set('selected', ['red'])
            ->call('clear')
            ->assertSet('selected', [])
            ->assertDispatched('clear-filter', field: 'colors');

        Livewire::test(ClearOverridingCheckboxFilter::class, $args)
            ->set('selected', ['red'])
            ->dispatch('clear-all-filters', [])
            ->assertSet('selected', [])
            ->assertNotDispatched('clear-filter');
    }

    /**
     * A second range filter on the same key that was never used has nothing to acknowledge:
     * after a relay-free clear-all, the next bounds event without the key must settle it.
     */
    #[Test]
    public function a_never_used_range_filter_does_not_stay_pending_after_clear_all()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        $collection = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'clothes']]);
        $args = ['field' => 'max_items', 'blueprint' => 'clothes.clothes', 'condition' => 'gte', 'min' => 4, 'max' => 6];
        $used = Livewire::test(LfRangeFilter::class, $args)->set('selected', 5);
        $idle = Livewire::test(LfRangeFilter::class, $args);
        $this->forwardToCollection($used, $collection, 'filter-updated');

        $collection->call('clearAll');
        $idle->dispatch('clear-all-filters', ...$this->clearAllEventParams($collection))
            ->assertSet('pendingBoundsSelection', [])
            ->dispatch('params-updated', ...$this->boundsEventParams($collection))
            ->assertSet('pendingBoundsSelection', null);
    }

    /**
     * In the browser the post-sweep bounds event and clear-all-filters arrive in one request,
     * bounds first; the filter then has nothing left to wait for.
     */
    #[Test]
    public function a_clear_all_pooled_after_its_bounds_event_leaves_nothing_pending()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        $collection = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'clothes']]);
        $filter = Livewire::test(LfRangeFilter::class, ['field' => 'max_items', 'blueprint' => 'clothes.clothes', 'condition' => 'gte', 'min' => 4, 'max' => 6])
            ->set('selected', 5);
        $this->forwardToCollection($filter, $collection, 'filter-updated');
        $filter->dispatch('params-updated', ...$this->boundsEventParams($collection));

        $collection->call('clearAll');
        $filter->update(calls: [
            ['path' => '', 'method' => '__dispatch', 'params' => ['params-updated', $this->boundsEventParams($collection)]],
            ['path' => '', 'method' => '__dispatch', 'params' => ['clear-all-filters', $this->clearAllEventParams($collection)]],
        ])
            ->assertSet('selected', 4)
            ->assertSet('pendingBoundsSelection', null)
            ->assertNotDispatched('clear-filter');
    }

    /**
     * Two collections on a page: the event only lists the sender's kept keys, so a collection
     * that kept another key re-sends the list with it added, and only the managing filter relays.
     */
    #[Test]
    public function clear_all_still_clears_a_kept_param_of_another_collection()
    {
        $sender = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'clothes']]);
        $other = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'clothes', 'taxonomy:colors:any' => 'red']]);
        $filter = Livewire::test(LfCheckboxFilter::class, ['field' => 'colors', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy'])
            ->set('selected', ['red']);
        $this->forwardToCollection($filter, $sender, 'filter-updated');
        $unrelated = Livewire::test(LfRangeFilter::class, ['field' => 'max_items', 'blueprint' => 'clothes.clothes', 'condition' => 'gte', 'min' => 4, 'max' => 6])
            ->set('selected', 5);
        $this->forwardToCollection($unrelated, $sender, 'filter-updated');

        $sender->call('clearAll');
        $kept = $this->clearAllEventParams($sender);
        $sender->dispatch('clear-all-filters', ...$kept)->assertNotDispatched('clear-all-filters');
        $filter->dispatch('clear-all-filters', ...$kept)->assertNotDispatched('clear-filter');

        $other->dispatch('clear-all-filters', ...$kept)
            ->assertSet('params', ['taxonomy:colors:any' => 'red'])
            ->assertDispatched('clear-all-filters', fn ($event, $params) => $params === [['taxonomy:colors:any'], $kept[1]]);
        $resent = $this->clearAllEventParams($other);

        // Only the filter managing the added key relays, and nobody re-sends again.
        $filter->dispatch('clear-all-filters', ...$resent)->assertDispatched('clear-filter', field: 'colors');
        $unrelated->dispatch('clear-all-filters', ...$resent)->assertNotDispatched('clear-filter');
        $sender->dispatch('clear-all-filters', ...$resent)->assertNotDispatched('clear-all-filters');
        $other->dispatch('clear-all-filters', ...$resent)->assertNotDispatched('clear-all-filters');
        $this->forwardToCollection($filter, $other, 'clear-filter');
        $other->assertSet('params', [])->assertSet('entriesCount', 2);
    }

    /** Each collection keeps a key the other doesn't list; the growing list stops the re-sends. */
    #[Test]
    public function collections_that_each_kept_a_different_key_stop_re_sending()
    {
        $first = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'clothes', 'taxonomy:colors:any' => 'red']]);
        $second = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'clothes', 'max_items:gte' => 5]]);

        $first->call('clearAll');
        $second->dispatch('clear-all-filters', ...$this->clearAllEventParams($first));
        $resent = $this->clearAllEventParams($second);

        $this->assertEqualsCanonicalizing(['taxonomy:colors:any', 'max_items:gte'], $resent[0]);
        $first->dispatch('clear-all-filters', ...$resent)->assertNotDispatched('clear-all-filters');
        $second->dispatch('clear-all-filters', ...$resent)->assertNotDispatched('clear-all-filters');
    }

    public static function filterSelections(): array
    {
        return [
            'text' => [LfTextFilter::class, ['field' => 'title', 'condition' => 'contains'], 'selected', 'Shirt'],
            'list of values' => [LfCheckboxFilter::class, ['field' => 'colors', 'condition' => 'taxonomy'], 'selected', ['red', 'black']],
            'range' => [LfRangeFilter::class, ['field' => 'max_items', 'condition' => 'gte', 'min' => 4, 'max' => 6], 'selected', 5],
            'dual range' => [LfDualRangeFilter::class, ['field' => 'max_items', 'min' => 4, 'max' => 6, 'minRange' => 1], 'selectedMin', 5],
            'toggle' => [LfToggleFilter::class, ['field' => 'title', 'condition' => 'is', 'preset_value' => 'Red Shirt'], 'selected', true],
        ];
    }

    protected function lastDispatchedFilterUpdate($filter): array
    {
        return collect($filter->effects['dispatches'] ?? [])->firstWhere('name', 'filter-updated')['params'];
    }

    /**
     * The sweep ran before the selection reached the collection, so its filter-updated lands
     * after the sweep. Without a relay the param would stick while the filter shows nothing.
     */
    #[Test]
    #[DataProvider('filterSelections')]
    public function a_selection_the_sweep_missed_does_not_stick(string $component, array $args, string $property, mixed $value)
    {
        $collection = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'clothes']]);
        $filter = Livewire::test($component, ['blueprint' => 'clothes.clothes', ...$args]);

        $collection->call('clearAll');
        $clearAll = $this->clearAllEventParams($collection);

        $filter->set($property, $value);
        $lateUpdate = $this->lastDispatchedFilterUpdate($filter);
        $filter->dispatch('clear-all-filters', ...$clearAll);

        $collection->dispatch('filter-updated', ...$lateUpdate);
        $this->assertNotSame([], $collection->get('params'), 'the late update should land after the sweep');

        $this->assertSame(1, $this->forwardToCollection($filter, $collection, 'clear-filter'), 'the filter should relay');
        $collection->assertSet('params', [])->assertSet('entriesCount', 2);
    }

    /** The saving stays: a selection the sweep removed needs no relay. */
    #[Test]
    #[DataProvider('filterSelections')]
    public function a_selection_the_sweep_removed_needs_no_relay(string $component, array $args, string $property, mixed $value)
    {
        $collection = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'clothes']]);
        $filter = Livewire::test($component, ['blueprint' => 'clothes.clothes', ...$args])->set($property, $value);
        $this->assertSame(1, $this->forwardToCollection($filter, $collection, 'filter-updated'));

        $collection->call('clearAll')->assertSet('params', []);
        $filter->dispatch('clear-all-filters', ...$this->clearAllEventParams($collection));

        $this->assertSame(0, $this->forwardToCollection($filter, $collection, 'clear-filter'), 'no relay expected');
        $filter->assertSet('lastSentPayload', null);
    }

    /** The sweep removed the old value, but the new one is still on its way. */
    #[Test]
    public function a_value_changed_after_the_sweep_read_the_old_one_does_not_stick()
    {
        $collection = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'clothes']]);
        $filter = Livewire::test(LfTextFilter::class, ['field' => 'title', 'blueprint' => 'clothes.clothes', 'condition' => 'contains'])
            ->set('selected', 'Shirt');
        $this->forwardToCollection($filter, $collection, 'filter-updated');

        $collection->call('clearAll');
        $clearAll = $this->clearAllEventParams($collection);
        $this->assertSame(['title:contains' => 'Shirt'], $clearAll[1]);

        $filter->set('selected', 'Red');
        $lateUpdate = $this->lastDispatchedFilterUpdate($filter);
        $filter->dispatch('clear-all-filters', ...$clearAll);
        $collection->dispatch('filter-updated', ...$lateUpdate)->assertSet('params', ['title:contains' => 'Red']);

        $this->assertSame(1, $this->forwardToCollection($filter, $collection, 'clear-filter'));
        $collection->assertSet('params', []);
    }

    /** A filter that already took its selection back has nothing on its way. */
    #[Test]
    public function a_selection_taken_back_before_the_clear_needs_no_relay()
    {
        $collection = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'clothes']]);

        $range = Livewire::test(LfRangeFilter::class, ['field' => 'max_items', 'blueprint' => 'clothes.clothes', 'condition' => 'gte', 'min' => 4, 'max' => 6])
            ->set('selected', 5)
            ->set('selected', 4)
            ->assertDispatched('clear-filter')
            ->assertSet('lastSentPayload', null);

        $text = Livewire::test(LfTextFilter::class, ['field' => 'title', 'blueprint' => 'clothes.clothes', 'condition' => 'contains'])
            ->set('selected', 'Shirt')
            ->set('selected', '');

        $collection->call('clearAll');
        $clearAll = $this->clearAllEventParams($collection);

        foreach ([$range, $text] as $filter) {
            $filter->dispatch('clear-all-filters', ...$clearAll)->assertNotDispatched('clear-filter');
        }
    }

    /** The bundled view sends filter-updated in the commit's own request, so nothing is left on its way. */
    #[Test]
    public function a_selection_sent_from_the_bundled_view_needs_no_relay()
    {
        $collection = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'clothes']]);
        $filter = Livewire::test(LfCheckboxFilter::class, ['field' => 'colors', 'blueprint' => 'clothes.clothes', 'condition' => 'taxonomy'])
            ->set('selected', ['black'])
            ->assertSet('lastSentPayload', ['black'])
            ->update(updates: ['syncedByView' => true, 'selected' => ['red']])
            ->assertSet('lastSentPayload', null);

        $collection->call('clearAll');
        $filter->dispatch('clear-all-filters', ...$this->clearAllEventParams($collection))->assertNotDispatched('clear-filter');
    }

    /** Each clear starts over, so a second clear-all doesn't relay the same selection again. */
    #[Test]
    public function a_second_clear_all_does_not_relay_again()
    {
        $collection = Livewire::test(LivewireCollection::class, ['params' => ['from' => 'clothes']]);
        $filter = Livewire::test(LfTextFilter::class, ['field' => 'title', 'blueprint' => 'clothes.clothes', 'condition' => 'contains'])
            ->set('selected', 'Shirt');

        $collection->call('clearAll');
        $filter->dispatch('clear-all-filters', ...$this->clearAllEventParams($collection))->assertDispatched('clear-filter');

        $collection->call('clearAll');
        $filter->dispatch('clear-all-filters', ...$this->clearAllEventParams($collection))->assertNotDispatched('clear-filter');
    }

    /** A list without the sweep's params (user code) can't tell, so only the kept keys decide, as before. */
    #[Test]
    public function an_event_without_the_swept_params_only_checks_the_kept_keys()
    {
        $args = ['field' => 'title', 'blueprint' => 'clothes.clothes', 'condition' => 'contains'];

        Livewire::test(LfTextFilter::class, $args)
            ->set('selected', 'Shirt')
            ->dispatch('clear-all-filters', [])
            ->assertNotDispatched('clear-filter');

        Livewire::test(LfTextFilter::class, $args)
            ->set('selected', 'Shirt')
            ->dispatch('clear-all-filters', ['title:contains'])
            ->assertDispatched('clear-filter');
    }
}

class ClearOverridingCheckboxFilter extends LfCheckboxFilter
{
    #[On('clear-all-filters')]
    public function clear()
    {
        parent::clear(...func_get_args());
    }
}
