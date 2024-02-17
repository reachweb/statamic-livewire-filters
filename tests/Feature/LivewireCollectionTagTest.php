<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Support\Facades\Config;
use Reach\StatamicLivewireFilters\Tags\LivewireCollection;
use Reach\StatamicLivewireFilters\Tests\FakesViews;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;
use Statamic\Facades\Antlers;

class LivewireCollectionTagTest extends TestCase
{
    use FakesViews, PreventSavingStacheItemsToDisk;

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

    protected function makeTaxononomyFakes()
    {
        Facades\Taxonomy::make('colors')->termTemplate('default')->layout('default')->save();
        Facades\Term::make()->taxonomy('colors')->inDefaultLocale()->slug('red')->data(['title' => 'Red'])->save();
        Facades\Term::make()->taxonomy('colors')->inDefaultLocale()->slug('black')->data(['title' => 'Black'])->save();
        Facades\Term::make()->taxonomy('colors')->inDefaultLocale()->slug('yellow')->data(['title' => 'Yellow'])->save();
        Facades\Collection::make('clothes')->routes('clothes/{slug}')->taxonomies(['colors'])->save();
    }

    public function test_if_it_throws_an_exception_for_no_collection()
    {
        $this->expectException(\Reach\StatamicLivewireFilters\Exceptions\NoCollectionException::class);
        $this->expectExceptionMessage('You need to specifiy a collection for the livewire-collection tag.');

        $this->setTagParameters(['title:is' => 'I Love Guitars']);
        $this->collectionTag->index();
    }

    public function test_it_throws_an_exception_for_an_invalid_collection()
    {
        $this->makePosts();

        $this->setTagParameters(['from' => 'music|unknown']);

        $this->expectException(\Statamic\Exceptions\CollectionNotFoundException::class);
        $this->expectExceptionMessage('Collection [unknown] not found');

        $this->collectionTag->index();
    }

    public function test_it_gets_entries_from_a_single_collection()
    {
        $this->makePosts();

        $this->setTagParameters(['from' => 'music']);
        $this->assertStringContainsString('I Love Guitars', $this->collectionTag->index());
    }

    public function test_it_gets_entries_from_multiple_collections()
    {
        $this->makePosts();

        $this->setTagParameters(['from' => 'music|art']);
        $this->assertStringContainsString('I Love Guitars', $this->collectionTag->index());
        $this->assertStringContainsString('I Love Drawing', $this->collectionTag->index());
    }

    public function test_it_sets_term_param_for_taxonony_route()
    {
        Config::set('statamic-livewire-filters.enable_term_routes', true);

        $this->withFakeViews();

        $this->makeTaxononomyFakes();

        $this->viewShouldReturnRaw('layout', '{{ template_content }}');
        $this->viewShouldReturnRaw('statamic-livewire-filters::livewire.livewire-collection', '<div></div>');
        $this->viewShouldReturnRaw('default', '{{ livewire-collection from="clothes" }}');

        Facades\Stache::clear();

        $response = $this->get('/colors/red');

        $response->assertSee('taxonomy:colors:any', false);
    }

    public function test_it_does_not_set_term_param_for_taxonony_route()
    {
        Config::set('statamic-livewire-filters.enable_term_routes', false);

        $this->withFakeViews();

        $this->makeTaxononomyFakes();

        $this->viewShouldReturnRaw('layout', '{{ template_content }}');
        $this->viewShouldReturnRaw('statamic-livewire-filters::livewire.livewire-collection', '<div></div>');
        $this->viewShouldReturnRaw('default', '{{ livewire-collection from="clothes" }}');

        Facades\Stache::clear();

        $response = $this->get('/colors/black');

        ray($response);

        $response->assertDontSee('taxonomy:colors:any', false);
    }

    private function setTagParameters($parameters)
    {
        $this->collectionTag->setParameters($parameters);
    }
}
