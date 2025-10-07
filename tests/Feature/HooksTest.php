<?php

namespace Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;
use Reach\StatamicLivewireFilters\Tests\FakesViews;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class HooksTest extends TestCase
{
    use FakesViews, PreventSavingStacheItemsToDisk;

    protected $music;

    protected function setUp(): void
    {
        parent::setUp();

        $this->music = Facades\Collection::make('music')->save();
        $this->makePosts();
    }

    protected function makeEntry($collection, $slug)
    {
        return EntryFactory::id($slug)->collection($collection)->slug($slug)->make();
    }

    protected function makePosts()
    {
        $this->makeEntry($this->music, 'a')->set('title', 'I Love Guitars')->save();
        $this->makeEntry($this->music, 'b')->set('title', 'I Love Drums')->save();
        $this->makeEntry($this->music, 'c')->set('title', 'I Hate Flutes')->save();
    }

    #[Test]
    public function it_can_hook_into_livewire_fetched_entries()
    {
        $this->withFakeViews();

        $this->viewShouldReturnRaw('statamic-livewire-filters::livewire.livewire-collection', '<div>{{ entries }} {{ bands }} {{ /entries }}</div>');

        LivewireCollection::hook('livewire-fetched-entries', function ($entries, $next) {
            $entries->transform(function ($entry) {
                return $entry->set('bands', 'I Love Rush!');
            });

            return $next($entries);
        });

        $params = [
            'from' => 'music',
            'title:is' => 'I Love Guitars',
        ];

        Livewire::test(LivewireCollection::class, ['params' => $params])
            ->assertSee('I Love Rush!');

    }
}
