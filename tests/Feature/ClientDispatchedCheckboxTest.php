<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfCheckboxFilter;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

/**
 * The bundled checkbox view dispatches filter-updated to the collection from the
 * browser and flags the commit with `syncedByView`; a view without the flag
 * (published before this change) must still get the server relay.
 */
class ClientDispatchedCheckboxTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    protected function setUp(): void
    {
        parent::setUp();

        Facades\Collection::make('pages')->save();
        Facades\Blueprint::make()->setContents(['sections' => ['main' => ['fields' => [
            ['handle' => 'item_options', 'field' => ['type' => 'checkboxes', 'display' => 'Options', 'options' => [
                ['key' => 'option1', 'value' => 'Option 1'],
                ['key' => 'option2', 'value' => 'Option 2'],
            ]]],
        ]]]])->setHandle('pages')->setNamespace('collections.pages')->save();
    }

    protected function filter()
    {
        return Livewire::test(LfCheckboxFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is']);
    }

    /** The bundled view's commit: the flag and the new selection travel in one request. */
    protected function viewCommit($component, array $selected, array $calls = [])
    {
        return $component->update(calls: $calls, updates: ['syncedByView' => true, 'selected' => $selected]);
    }

    #[Test]
    public function a_commit_flagged_by_the_view_skips_the_server_relay_and_resets_the_flag()
    {
        $this->viewCommit($this->filter(), ['option1'])
            ->assertNotDispatched('filter-updated')
            ->assertSet('syncedByView', false)
            ->assertSet('selected', ['option1']);
    }

    #[Test]
    public function an_unflagged_commit_still_relays_for_views_without_the_client_dispatch()
    {
        $this->filter()
            ->set('selected', ['option1'])
            ->assertDispatched('filter-updated', field: 'item_options', condition: 'is', payload: ['option1']);
    }

    #[Test]
    public function the_flag_only_covers_one_commit()
    {
        $this->viewCommit($this->filter(), ['option1'])
            ->assertNotDispatched('filter-updated')
            ->set('selected', ['option1', 'option2'])
            ->assertDispatched('filter-updated', payload: ['option1', 'option2']);
    }

    #[Test]
    public function validation_still_runs_on_a_flagged_commit()
    {
        Config::set('statamic-livewire-filters.validate_filter_values', true);

        $this->viewCommit($this->filter(), ['not-an-option'])
            ->assertHasErrors('selected')
            ->assertNotDispatched('filter-updated')
            ->assertSet('syncedByView', false);
    }

    #[Test]
    public function the_bundled_view_dispatches_to_the_collection_from_the_browser()
    {
        $this->filter()
            ->assertSeeHtml('syncedByView = true')
            ->assertSeeHtml("\$dispatchTo('livewire-collection', 'filter-updated'");
    }

    #[Test]
    public function clearing_an_option_from_tags_still_relays()
    {
        $this->filter()
            ->set('selected', ['option1', 'option2'])
            ->dispatch('clear-option', ['field' => 'item_options', 'value' => 'option1'])
            ->assertSet('selected', ['option2'])
            ->assertDispatched('filter-updated', payload: ['option2']);
    }

    /**
     * A tag removal pooled into the same request as a flagged commit is a server-side
     * change the browser never dispatched, so it must still relay.
     */
    #[Test]
    public function a_clear_option_pooled_with_a_flagged_commit_still_relays()
    {
        $component = $this->filter()->set('selected', ['option1']);
        $clearOption = ['path' => '', 'method' => '__dispatch', 'params' => ['clear-option', [['field' => 'item_options', 'value' => 'option1']]]];

        $this->viewCommit($component, ['option1', 'option2'], [$clearOption])
            ->assertSet('selected', ['option2'])
            ->assertDispatched('filter-updated', payload: ['option2']);
    }

    /** The browser can only send `$selected` as is, so a custom payload needs the server relay. */
    #[Test]
    public function a_subclass_with_its_own_payload_keeps_the_server_relay()
    {
        $component = Livewire::test(PayloadMappingCheckboxFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->assertDontSeeHtml('syncedByView = true');

        $this->viewCommit($component, ['option1'])
            ->assertDispatched('filter-updated', payload: ['mapped-option1']);
    }

    /** Failed validation never reaches the dispatch that resets the flag, so a stale flag would swallow the next relay. */
    #[Test]
    public function the_flag_resets_after_failed_validation_even_when_a_subclass_defines_its_own_dehydrate()
    {
        Config::set('statamic-livewire-filters.validate_filter_values', true);

        $component = Livewire::test(DehydratingCheckboxFilter::class, ['field' => 'item_options', 'blueprint' => 'pages.pages', 'condition' => 'is'])
            ->assertSeeHtml('syncedByView = true');

        $this->viewCommit($component, ['not-an-option'])
            ->assertHasErrors('selected')
            ->assertSet('syncedByView', false);
    }
}

class PayloadMappingCheckboxFilter extends LfCheckboxFilter
{
    protected function payload(): mixed
    {
        return array_map(fn ($value) => 'mapped-'.$value, $this->selected);
    }
}

class DehydratingCheckboxFilter extends LfCheckboxFilter
{
    public function dehydrate(): void {}
}
