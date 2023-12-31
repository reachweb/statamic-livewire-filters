<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Livewire\Livewire;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection as LivewireCollectionComponent;
use Reach\StatamicLivewireFilters\Tags\LivewireCollection;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;
use Statamic\Facades\Antlers;

class LivewireCollectionComponentTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    private $music;

    private $art;

    private $books;

    private $foods;

    private $collectionTag;

    public function setUp(): void
    {
        parent::setUp();

        $this->music = Facades\Collection::make('music')->save();
        $this->art = Facades\Collection::make('art')->save();
        $this->books = Facades\Collection::make('books')->save();
        $this->foods = Facades\Collection::make('foods')->save();

        $this->collectionTag = (new LivewireCollection)
            ->setParser(Antlers::parser())
            ->setContext([]);
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

        $this->makeEntry($this->art, 'd')->set('title', 'I Love Drawing')->save();
        $this->makeEntry($this->art, 'e')->set('title', 'I Love Painting')->save();
        $this->makeEntry($this->art, 'f')->set('title', 'I Hate Sculpting')->save();

        $this->makeEntry($this->books, 'g')->set('title', 'I Love Tolkien')->save();
        $this->makeEntry($this->books, 'h')->set('title', 'I Love Lewis')->save();
        $this->makeEntry($this->books, 'i')->set('title', 'I Hate Martin')->save();
    }

    public function test_if_the_tag_loads_the_livewire_component()
    {
        $this->makeEntry($this->music, 'a')->set('title', 'I Love Guitars')->save();

        $this->setTagParameters(['from' => 'music']);

        $this->assertStringContainsString('wire:snapshot', $this->collectionTag->index());
        $this->assertStringContainsString('livewire-collection', $this->collectionTag->index());
    }

    public function test_if_the_tag_loads_the_livewire_component_with_parameters()
    {
        $this->makeEntry($this->music, 'a')->set('title', 'I Love Guitars')->save();

        $params = [
            'from' => 'music',
            'title:is' => 'I Love Guitars',
        ];

        Livewire::test(LivewireCollectionComponent::class, ['params' => $params])
            ->assertSet('params', $params);
    }

    private function setTagParameters($parameters)
    {
        $this->collectionTag->setParameters($parameters);
    }
}
