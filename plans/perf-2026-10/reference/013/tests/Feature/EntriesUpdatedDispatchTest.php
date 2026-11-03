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
 * `entries-updated` doubles as the "collection finished rendering" signal for
 * published views (the demo's animations view ends its loading state on it), so
 * every filter, sort or clear must still emit it even when the count and the
 * active-filter count are unchanged. Only a pure page change with both numbers
 * unchanged may skip it.
 */
class EntriesUpdatedDispatchTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    protected function setUp(): void
    {
        parent::setUp();

        Facades\Collection::make('clothes')->save();

        EntryFactory::collection('clothes')->slug('red-shirt')->data(['title' => 'Red Shirt'])->create();
        EntryFactory::collection('clothes')->slug('black-shirt')->data(['title' => 'Black Shirt'])->create();
        EntryFactory::collection('clothes')->slug('yellow-shirt')->data(['title' => 'Yellow Shirt'])->create();
    }

    #[Test]
    public function entries_updated_is_dispatched_on_the_first_render()
    {
        Livewire::test(LivewireCollectionComponent::class, ['params' => ['from' => 'clothes']])
            ->assertDispatched('entries-updated', count: 3, active: 0);
    }

    /**
     * "Shir" -> "Shirt" keeps count (3) and active (1) identical. An unconditional
     * "skip when unchanged" leaves the animations view's cards at opacity-0 here.
     */
    #[Test]
    public function entries_updated_is_dispatched_when_a_filter_change_leaves_count_and_active_unchanged()
    {
        Livewire::test(LivewireCollectionComponent::class, ['params' => ['from' => 'clothes']])
            ->dispatch('filter-updated', field: 'title', condition: 'contains', payload: 'Shir', modifier: 'any')
            ->assertSet('entriesCount', 3)
            ->assertSet('activeFilters', 1)
            ->assertDispatched('entries-updated', count: 3, active: 1)
            ->dispatch('filter-updated', field: 'title', condition: 'contains', payload: 'Shirt', modifier: 'any')
            ->assertSet('entriesCount', 3)
            ->assertSet('activeFilters', 1)
            ->assertDispatched('entries-updated', count: 3, active: 1);
    }

    #[Test]
    public function entries_updated_is_dispatched_when_switching_between_equal_count_values()
    {
        Livewire::test(LivewireCollectionComponent::class, ['params' => ['from' => 'clothes']])
            ->dispatch('filter-updated', field: 'title', condition: 'is', payload: 'Red Shirt', modifier: 'any')
            ->assertSet('entriesCount', 1)
            ->dispatch('filter-updated', field: 'title', condition: 'is', payload: 'Black Shirt', modifier: 'any')
            ->assertSet('entriesCount', 1)
            ->assertDispatched('entries-updated', count: 1, active: 1)
            ->assertSee('Black Shirt')
            ->assertDontSee('Red Shirt');
    }

    #[Test]
    public function a_sort_change_is_dispatched_even_when_the_count_is_unchanged()
    {
        Livewire::test(LivewireCollectionComponent::class, ['params' => ['from' => 'clothes']])
            ->dispatch('sort-updated', sort: 'title:desc')
            ->assertSet('entriesCount', 3)
            ->assertDispatched('entries-updated', count: 3, active: 0);
    }

    /**
     * A page change cannot alter the count or the active filters, so the LfCount
     * round trip is skipped. This is the behaviour change of the plan.
     */
    #[Test]
    public function a_page_change_that_changes_nothing_skips_entries_updated()
    {
        Livewire::test(LivewireCollectionComponent::class, ['params' => ['from' => 'clothes', 'paginate' => 2]])
            ->assertDispatched('entries-updated', count: 3, active: 0)
            ->call('gotoPage', 2, 'page')
            ->assertSet('paginators.page', 2)
            ->assertSet('entriesCount', 3)
            ->assertSee('Yellow Shirt')
            ->assertNotDispatched('entries-updated')
            ->call('previousPage', 'page')
            ->assertSet('paginators.page', 1)
            ->assertNotDispatched('entries-updated')
            ->call('nextPage', 'page')
            ->assertSet('paginators.page', 2)
            ->assertNotDispatched('entries-updated');
    }

    #[Test]
    public function a_load_more_that_changes_nothing_skips_entries_updated()
    {
        Livewire::test(LivewireCollectionComponent::class, ['params' => ['from' => 'clothes', 'paginate' => 2, 'infinite_scroll' => true]])
            ->assertDispatched('entries-updated', count: 3, active: 0)
            ->call('loadMore')
            ->assertSet('paginate', 4)
            ->assertSet('entriesCount', 3)
            ->assertSee('Yellow Shirt')
            ->assertNotDispatched('entries-updated');
    }

    #[Test]
    public function a_page_change_still_dispatches_when_the_total_changed_in_between()
    {
        $component = Livewire::test(LivewireCollectionComponent::class, ['params' => ['from' => 'clothes', 'paginate' => 2]])
            ->assertDispatched('entries-updated', count: 3, active: 0);

        EntryFactory::collection('clothes')->slug('green-shirt')->data(['title' => 'Green Shirt'])->create();

        $component->call('gotoPage', 2, 'page')
            ->assertSet('entriesCount', 4)
            ->assertDispatched('entries-updated', count: 4, active: 0);
    }

    #[Test]
    public function a_filter_change_in_the_request_after_a_page_change_is_dispatched()
    {
        Livewire::test(LivewireCollectionComponent::class, ['params' => ['from' => 'clothes', 'paginate' => 2]])
            ->call('gotoPage', 2, 'page')
            ->assertNotDispatched('entries-updated')
            ->dispatch('filter-updated', field: 'title', condition: 'contains', payload: 'Shirt', modifier: 'any')
            ->assertSet('paginators.page', 1)
            ->assertDispatched('entries-updated', count: 3, active: 1);
    }
}
