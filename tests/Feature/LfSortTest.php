<?php

use Livewire\Livewire;
use Reach\StatamicLivewireFilters\Http\Livewire\LfSort;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class LfSortTest extends TestCase
{
    public function setUp(): void
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
                                'type' => 'radio',
                                'display' => 'Radio',
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
    }

    /** @test */
    public function it_can_render_the_lf_sort_component()
    {
        Livewire::test(LfSort::class, ['blueprint' => 'pages.pages', 'fields' => 'title|item_options'])
            ->assertSee('Title asc');
    }

    /** @test */
    public function it_dispatches_the_event_when_selected_changes()
    {
        Livewire::test(LfSort::class, ['blueprint' => 'pages.pages', 'fields' => 'title|item_options'])
            ->set('selected', 'title|asc')
            ->assertSet('selected', 'title|asc')
            ->assertDispatched('sort-updated',
                'title|asc'
            );
    }
}
