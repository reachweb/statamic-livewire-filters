<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Reach\StatamicLivewireFilters\Support\ParamKey;

class ParamKeyTest extends TestCase
{
    /**
     * Keys asserted by the existing filter, collection and tags tests.
     */
    public static function keys(): array
    {
        return [
            'field condition' => ['item_options', 'is', 'any', 'item_options:is'],
            'text condition' => ['title', 'contains', 'any', 'title:contains'],
            'condition with underscore' => ['item_from', 'is_after', 'any', 'item_from:is_after'],
            'taxonomy any' => ['colors', 'taxonomy', 'any', 'taxonomy:colors:any'],
            'taxonomy all' => ['brands', 'taxonomy', 'all', 'taxonomy:brands:all'],
            'query scope' => ['sizes', 'query_scope', 'multiselect', 'multiselect:sizes'],
            'resrv scope' => ['resrv_availability', 'query_scope', 'resrv_search', 'resrv_search:resrv_availability'],
            'dual range default' => ['cabins', 'dual_range', 'any', ['min' => 'cabins:gte', 'max' => 'cabins:lte']],
            'dual range custom' => ['cabins', 'dual_range', 'gt|lt', ['min' => 'cabins:gt', 'max' => 'cabins:lt']],
            'dual range date' => ['year', 'dual_range', 'is_after|is_before', ['min' => 'year:is_after', 'max' => 'year:is_before']],
        ];
    }

    #[Test]
    #[DataProvider('keys')]
    public function it_builds_the_emitted_keys(string $field, string $condition, string $modifier, string|array $expected)
    {
        $this->assertSame($expected, ParamKey::build($field, $condition, $modifier));
    }

    #[Test]
    #[DataProvider('keys')]
    public function it_parses_the_keys_it_builds(string $field, string $condition, string $modifier, string|array $expected)
    {
        $scopes = $condition === 'query_scope' ? [$modifier] : [];

        foreach ((array) $expected as $key) {
            $parsed = ParamKey::parse($key, $scopes);

            $this->assertSame($field, $parsed->field);
            $this->assertSame(ParamKey::build($parsed->field, $parsed->condition, $parsed->modifier), $condition === 'dual_range' ? $key : $expected);
        }
    }

    #[Test]
    public function scope_keys_only_parse_as_scopes_for_registered_scopes()
    {
        $this->assertSame('sizes', ParamKey::parse('multiselect:sizes')->condition);

        $parsed = ParamKey::parse('price_range:gte', ['price']);
        $this->assertSame(['price_range', 'gte', null], [$parsed->field, $parsed->condition, $parsed->modifier]);
    }
}
