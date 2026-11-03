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

    #[Test]
    public function a_commit_flagged_by_the_view_skips_the_server_relay_and_resets_the_flag()
    {
        $this->filter()
            ->set('syncedByView', true)
            ->set('selected', ['option1'])
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
        $this->filter()
            ->set('syncedByView', true)
            ->set('selected', ['option1'])
            ->assertNotDispatched('filter-updated')
            ->set('selected', ['option1', 'option2'])
            ->assertDispatched('filter-updated', payload: ['option1', 'option2']);
    }

    #[Test]
    public function validation_still_runs_on_a_flagged_commit()
    {
        Config::set('statamic-livewire-filters.validate_filter_values', true);

        $this->filter()
            ->set('syncedByView', true)
            ->set('selected', ['not-an-option'])
            ->assertHasErrors('selected')
            ->assertNotDispatched('filter-updated');
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
}
