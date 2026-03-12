<?php

namespace Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Support\CountEntries;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;

class CountEntriesTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    protected $collection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collection = Facades\Collection::make('pages')->save();
        $blueprint = Facades\Blueprint::make()->setContents([
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
                                'options' => [
                                    'option1' => 'Option 1',
                                    'option2' => 'Option 2',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $blueprint->setHandle('pages')->setNamespace('collections.pages')->save();

        $this->makeEntry($this->collection, 'a')->set('title', 'Entry A')->set('item_options', 'option1')->save();
        $this->makeEntry($this->collection, 'b')->set('title', 'Entry B')->set('item_options', 'option1')->save();
        $this->makeEntry($this->collection, 'c')->set('title', 'Entry C')->set('item_options', 'option2')->save();
    }

    #[Test]
    public function it_plucks_a_single_column_from_entries()
    {
        $params = Parameters::make(['from' => 'pages'], Context::make([]));

        $result = (new CountEntries($params))->pluck('item_options');

        $this->assertCount(3, $result);
        $this->assertContains('option1', $result->all());
        $this->assertContains('option2', $result->all());
    }

    #[Test]
    public function it_returns_empty_collection_when_no_entries_match()
    {
        $params = Parameters::make([
            'from' => 'pages',
            'title:is' => 'nonexistent',
        ], Context::make([]));

        $result = (new CountEntries($params))->pluck('item_options');

        $this->assertTrue($result->isEmpty());
    }

    #[Test]
    public function it_plucks_values_respecting_query_conditions()
    {
        $params = Parameters::make([
            'from' => 'pages',
            'item_options:is' => 'option1',
        ], Context::make([]));

        $result = (new CountEntries($params))->pluck('title');

        $this->assertCount(2, $result);
        $this->assertContains('Entry A', $result->all());
        $this->assertContains('Entry B', $result->all());
        $this->assertNotContains('Entry C', $result->all());
    }

    #[Test]
    public function it_returns_null_values_for_entries_without_the_field()
    {
        $this->makeEntry($this->collection, 'd')->set('title', 'Entry D')->save();

        $params = Parameters::make(['from' => 'pages'], Context::make([]));

        $result = (new CountEntries($params))->pluck('item_options');

        $this->assertCount(4, $result);
        $this->assertContains(null, $result->all());
    }

    protected function makeEntry($collection, $slug)
    {
        return EntryFactory::id($slug)->collection($collection)->slug($slug)->make();
    }
}
