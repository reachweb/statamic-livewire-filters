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
}
