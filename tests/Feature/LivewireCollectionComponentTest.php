<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Livewire\Livewire;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection as LivewireCollectionComponent;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class LivewireCollectionComponentTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    private $music;

    public function setUp(): void
    {
        parent::setUp();

        $this->music = Facades\Collection::make('music')->save();
    }

    /** @test */
    public function it_loads_the_livewire_component_with_parameters()
    {
        $params = [
            'from' => 'music',
            'title:is' => 'I Love Guitars',
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('params', $params);
    }

    /** @test */
    public function it_loads_the_livewire_component_with_parameters_and_changes_them_after_filter_updated_event()
    {
        $params = [
            'from' => 'music',
            'title:is' => 'I Love Guitars',
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('params', $params)
            ->dispatch('filter-updated',
                field: 'item_options',
                condition: 'is',
                payload: 'option1',
                command: 'add',
                modifier: 'any',
            )
            ->assertSet('params', [
                'from' => 'music',
                'title:is' => 'I Love Guitars',
                'item_options:is' => 'option1',
            ])
            ->dispatch('filter-updated',
                field: 'title',
                condition: 'is',
                payload: 'Test',
                command: 'replace',
                modifier: 'any',
            )
            ->assertSet('params', [
                'from' => 'music',
                'title:is' => 'Test',
                'item_options:is' => 'option1',
            ])
            ->dispatch('filter-updated',
                field: 'item_options',
                condition: 'is',
                payload: 'option2',
                command: 'add',
                modifier: 'any',
            )
            ->assertSet('params', [
                'from' => 'music',
                'title:is' => 'Test',
                'item_options:is' => 'option1|option2',
            ])
            ->dispatch('filter-updated',
                field: 'item_options',
                condition: 'is',
                payload: 'option1',
                command: 'remove',
                modifier: 'any',
            )
            ->assertSet('params', [
                'from' => 'music',
                'title:is' => 'Test',
                'item_options:is' => 'option2',
            ]);
    }

    /** @test */
    public function it_clears_all_filters_for_a_field()
    {
        $params = [
            'from' => 'music',
            'title:is' => 'I Love Guitars',
            'item_options:is' => 'option1|option2',
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('params', $params)
            ->dispatch('filter-updated',
                field: 'item_options',
                condition: 'is',
                payload: false,
                command: 'clear',
                modifier: 'any',
            )
            ->assertSet('params', [
                'from' => 'music',
                'title:is' => 'I Love Guitars',
            ]);
    }
}
