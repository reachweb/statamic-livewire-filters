<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Statamic\Exceptions\CollectionNotFoundException;
use Statamic\Facades;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Statamic\Structures\CollectionStructure;
use Statamic\Tags\Collection\Collection;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;

class CollectionTest extends TestCase
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

        $this->collectionTag = (new Collection)
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

    public function test_it_throws_an_exception_for_an_invalid_collection()
    {
        $this->makePosts();

        $this->setTagParameters(['from' => 'music|unknown']);

        $this->expectException(CollectionNotFoundException::class);
        $this->expectExceptionMessage('Collection [unknown] not found');

        $this->collectionTag->index();
    }

    public function test_it_gets_entries_from_multiple_collections()
    {
        $this->makePosts();

        $this->setTagParameters(['from' => 'music|art']);
        $this->assertCount(6, $this->collectionTag->index());

        $this->setTagParameters(['in' => 'music|art']);
        $this->assertCount(6, $this->collectionTag->index());

        $this->setTagParameters(['folder' => 'music|art']);
        $this->assertCount(6, $this->collectionTag->index());

        $this->setTagParameters(['use' => 'music|art']);
        $this->assertCount(6, $this->collectionTag->index());

        $this->setTagParameters(['collection' => 'music|art']);
        $this->assertCount(6, $this->collectionTag->index());
    }

    private function setTagParameters($parameters)
    {
        $this->collectionTag->setParameters($parameters);
    }
}