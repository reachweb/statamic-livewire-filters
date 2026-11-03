<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Scopes\Multiselect;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

/**
 * Semantics of the Multiselect scope on the Stache builder: array-stored values,
 * numeric values, null/missing fields and the Eloquent path must behave as before;
 * a scalar-stored value now matches (the nested orWhereJsonContains form never did).
 */
class MultiselectScopeSemanticsTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    protected function setUp(): void
    {
        parent::setUp();

        Facades\Collection::make('products')->save();

        $blueprint = Facades\Blueprint::make()->setContents([
            'sections' => ['main' => ['fields' => [
                ['handle' => 'title', 'field' => ['type' => 'text']],
                ['handle' => 'colors', 'field' => ['type' => 'checkboxes', 'options' => ['red' => 'Red', 'blue' => 'Blue', 'green' => 'Green']]],
                ['handle' => 'brand', 'field' => ['type' => 'select', 'max_items' => 1, 'options' => ['acme' => 'Acme', 'globex' => 'Globex']]],
                ['handle' => 'sizes', 'field' => ['type' => 'checkboxes', 'options' => ['1' => 'One', '2' => 'Two']]],
            ]]],
        ]);
        $blueprint->setHandle('product')->setNamespace('collections.products')->save();

        EntryFactory::collection('products')->slug('p-array-red-blue')->data(['title' => 'Array red+blue', 'colors' => ['red', 'blue'], 'brand' => 'acme'])->create();
        EntryFactory::collection('products')->slug('p-array-green')->data(['title' => 'Array green', 'colors' => ['green'], 'brand' => 'globex'])->create();
        EntryFactory::collection('products')->slug('p-scalar-red')->data(['title' => 'Scalar red', 'colors' => 'red', 'brand' => 'acme'])->create();
        EntryFactory::collection('products')->slug('p-null')->data(['title' => 'No colors', 'colors' => null, 'brand' => null])->create();
        EntryFactory::collection('products')->slug('p-missing')->data(['title' => 'Missing field'])->create();
        EntryFactory::collection('products')->slug('p-numeric')->data(['title' => 'Numeric', 'sizes' => ['1', 2]])->create();
    }

    protected function slugsFor(array $params): array
    {
        $query = Facades\Entry::query()->where('collection', 'products');

        (new Multiselect)->apply($query, $params);

        return $query->get()->map->slug()->sort()->values()->all();
    }

    #[Test]
    public function array_stored_values_match_any_selected_value()
    {
        $this->assertSame(['p-array-green', 'p-array-red-blue', 'p-scalar-red'], $this->slugsFor(['multiselect:colors' => 'red|green']));
        $this->assertSame(['p-array-red-blue'], $this->slugsFor(['multiselect:colors' => 'blue']));
    }

    /**
     * A `select` with max_items 1 stores a scalar. whereJsonOverlaps treats it as a
     * one-element set, so it matches; the previous nested orWhereJsonContains form
     * returned nothing for it. This test fails on the pre-change scope by design.
     */
    #[Test]
    public function scalar_stored_values_match_the_multiselect_scope()
    {
        $this->assertSame(['p-array-red-blue', 'p-scalar-red'], $this->slugsFor(['multiselect:colors' => 'red']));
        $this->assertSame(['p-array-red-blue', 'p-scalar-red'], $this->slugsFor(['multiselect:brand' => 'acme']));
        $this->assertSame(['p-array-green'], $this->slugsFor(['multiselect:brand' => 'globex|other']));
    }

    #[Test]
    public function null_and_missing_values_never_match()
    {
        $this->assertSame([], $this->slugsFor(['multiselect:colors' => 'purple']));
        $this->assertNotContains('p-null', $this->slugsFor(['multiselect:colors' => 'red|blue|green']));
        $this->assertNotContains('p-missing', $this->slugsFor(['multiselect:colors' => 'red|blue|green']));
    }

    #[Test]
    public function numeric_strings_and_integers_compare_loosely_like_before()
    {
        $this->assertSame(['p-numeric'], $this->slugsFor(['multiselect:sizes' => '1']));
        $this->assertSame(['p-numeric'], $this->slugsFor(['multiselect:sizes' => '2']));
        $this->assertSame([], $this->slugsFor(['multiselect:sizes' => '3']));
    }

    /**
     * Two multiselect fields AND together. The scalar-stored `brand` is part of the
     * AND, so the pre-change scope returned [] here (the scalar never matched).
     */
    #[Test]
    public function multiple_multiselect_fields_are_anded_together()
    {
        $this->assertSame(['p-array-red-blue', 'p-scalar-red'], $this->slugsFor(['multiselect:colors' => 'red|green', 'multiselect:brand' => 'acme', 'other:param' => 'ignored']));
    }

    #[Test]
    public function non_stache_builders_keep_the_nested_or_where_json_contains_path()
    {
        $query = new class
        {
            public array $calls = [];

            public function where($callback)
            {
                $this->calls[] = 'where(closure)';
                $callback($this);

                return $this;
            }

            public function orWhereJsonContains($field, $value)
            {
                $this->calls[] = "orWhereJsonContains($field,$value)";

                return $this;
            }

            public function whereJsonOverlaps($field, $values)
            {
                $this->calls[] = 'whereJsonOverlaps';

                return $this;
            }
        };

        (new Multiselect)->apply($query, ['multiselect:colors' => 'red|blue']);

        $this->assertSame(['where(closure)', 'orWhereJsonContains(colors,red)', 'orWhereJsonContains(colors,blue)'], $query->calls);
    }
}
