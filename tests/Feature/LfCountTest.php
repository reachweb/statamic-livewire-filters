<?php

namespace Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Livewire\Livewire;
use Reach\StatamicLivewireFilters\Http\Livewire\LfCount;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class LfCountTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    protected $collection;

    protected $blueprint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collection = Facades\Collection::make('pages')->save();
        $this->blueprint = Facades\Blueprint::make()->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        [
                            'handle' => 'title',
                            'field' => [
                                'type' => 'text',
                                'display' => 'Title',
                            ],
                        ],
                        [

                            'handle' => 'item_options',
                            'field' => [
                                'type' => 'checkboxes',
                                'display' => 'Checkbox',
                                'listable' => 'hidden',
                                'options' => [
                                    'option1' => 'Option 1',
                                    'option2' => 'Option 2',
                                    'option3' => 'Option 3',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->blueprint->setHandle('pages')->setNamespace('collections.'.$this->collection->handle())->save();

        $this->makeEntry($this->collection, 'a')->set('title', 'I Love Guitars')->set('item_options', 'option1')->save();
        $this->makeEntry($this->collection, 'b')->set('title', 'I Love Drums')->set('item_options', 'option1')->save();
        $this->makeEntry($this->collection, 'c')->set('title', 'I Hate Flutes')->set('item_options', 'option2')->save();

    }

    public function test_it_renders_the_component_and_shows_the_text()
    {
        Livewire::test(LfCount::class)
            ->dispatch('entries-updated', 1)
            ->assertSee('entry');
    }

    public function test_it_listens_to_the_entries_updated_event()
    {
        Livewire::test(LfCount::class)
            ->dispatch('entries-updated', 3)
            ->assertSet('count', 3)
            ->assertSee('3 entries');

        Livewire::test(LfCount::class)
            ->dispatch('entries-updated', 1)
            ->assertSet('count', 1)
            ->assertSee('1 entry');
    }

    public function test_the_bundled_view_updates_in_the_browser_without_a_server_listener()
    {
        $component = Livewire::test(LfCount::class)
            ->assertSeeHtml('x-on:entries-updated.window')
            ->assertSeeHtml('detail?.entriesLabel');

        $this->assertNotContains('entries-updated', $component->effects['listeners'] ?? []);
    }

    /** An event without the label (user code) sets the count from the browser, and the server renders it. */
    public function test_the_bundled_view_falls_back_to_the_server_for_events_without_a_label()
    {
        Livewire::test(LfCount::class)
            ->assertSeeHtml("\$wire.\$set('count', count)")
            ->set('count', 3)
            ->assertSee('3 entries')
            ->set('count', 1)
            ->assertSee('1 entry');
    }

    public function test_a_published_count_view_keeps_the_server_listener()
    {
        app('view')->prependNamespace('statamic-livewire-filters', __DIR__.'/../__fixtures__/views/published-count');

        $component = Livewire::test(LfCount::class);

        $this->assertContains('entries-updated', $component->effects['listeners'] ?? []);

        $component->dispatch('entries-updated', count: 3, active: 0, entriesLabel: 'entries')
            ->assertSet('count', 3)
            ->assertSee('3 entries');
    }

    /** A page rendered before the listener moved to the browser still sends the event to the server. */
    public function test_a_subclass_defining_its_own_hydrate_still_accepts_the_event_after_mount()
    {
        Livewire::test(HydratingCount::class)
            ->dispatch('entries-updated', 3)
            ->assertSet('count', 3);
    }

    protected function makeEntry($collection, $slug)
    {
        return EntryFactory::id($slug)->collection($collection)->slug($slug)->make();
    }
}

class HydratingCount extends LfCount
{
    public function hydrate(): void {}
}
