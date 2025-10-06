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

class CustomQueryStringTest extends TestCase
{
    use FakesViews, PreventSavingStacheItemsToDisk;

    protected $collection;

    protected $blueprint;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('statamic-livewire-filters.custom_query_string', 'filters');
        Config::set('statamic-livewire-filters.custom_query_string_aliases', [
            'item_options' => 'item_options:is',
            'title' => 'title:contains',
        ]);

        $this->collection = Facades\Collection::make('pages')
            ->routes('{parent_uri}/{slug}')
            ->structureContents([
                'root' => true,
            ])->save();

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
                                'type' => 'select',
                                'display' => 'Select',
                                'listable' => 'hidden',
                                'options' => [
                                    [
                                        'key' => 'option1',
                                        'value' => 'Option 1',
                                    ],
                                    [
                                        'key' => 'option2',
                                        'value' => 'Option 2',
                                    ],
                                    [
                                        'key' => 'option3',
                                        'value' => 'Option 3',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->blueprint->setHandle('pages')->setNamespace('collections.'.$this->collection->handle())->save();

        $this->makeEntry($this->collection, 'a')->set('title', 'I Love Guitars')->set('item_options', 'option1')->save();
        $this->makeEntry($this->collection, 'b')->set('title', 'I Love Drums')->set('item_options', 'option2')->save();
        $this->makeEntry($this->collection, 'c')->set('title', 'I Hate Flutes')->set('item_options', 'option3')->save();
    }

    protected function makeEntry($collection, $slug)
    {
        return EntryFactory::id($slug)->collection($collection)->slug($slug)->make();
    }

    #[Test]
    public function it_can_load_parameters_from_the_url()
    {
        $this->withStandardFakeViews();

        $this->viewShouldReturnRaw('default', '{{ livewire-collection:pages }}');

        $this->viewShouldReturnRaw('statamic-livewire-filters::livewire.livewire-collection', '<div>{{ entries }} {{ title }} {{ /entries }}</div>');

        $response = $this->get('/filters/item_options/option2');

        // Make sure the params are loaded from the URL
        $this->assertEquals(
            ['item_options:is' => 'option2'],
            request()->query('params')
        );

        // Make sure it ignores the custom query string and loads the original URL
        $this->assertEquals(
            '/',
            request()->path()
        );

        // If the parameter have loaded we should only see item 2
        $response->assertSee('I Love Drums')->assertDontSee('I Love Guitars')->assertDontSee('I Hate Flutes');
    }

    #[Test]
    public function it_dispatched_the_update_url_event_with_the_correct_url()
    {
        $params = [
            'from' => 'pages',
            'title:contains' => 'I Love Guitars',
        ];

        Livewire::test(LivewireCollection::class, ['params' => $params])
            ->dispatch('filter-updated',
                field: 'item_options',
                condition: 'is',
                payload: 'option1',
                command: 'add',
                modifier: 'any',
            )
            ->assertDispatched('update-url')
            ->assertDispatched('update-url', fn ($name, $payload) => str_contains($payload['newUrl'], 'filters/title/I Love Guitars/item_options/option1'));
    }
}
