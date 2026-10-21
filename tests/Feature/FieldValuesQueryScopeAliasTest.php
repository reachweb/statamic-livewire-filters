<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfDualRangeFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfRangeFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;
use Statamic\Query\Scopes\Scope;

class FieldValuesQueryScopeAliasTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    #[Test]
    #[DataProvider('scopeParameters')]
    public function dynamic_bounds_respect_scope_aliases_and_precedence(array $scopeParams, int $expectedMax)
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);
        FieldValuesCabinsScope::register();

        $collection = Facades\Collection::make('yachts')->save();
        Facades\Blueprint::make('yachts')->setNamespace('collections.yachts')->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        ['handle' => 'title', 'field' => ['type' => 'text']],
                        ['handle' => 'cabins', 'field' => ['type' => 'text']],
                        ['handle' => 'category', 'field' => ['type' => 'text']],
                    ],
                ],
            ],
        ])->save();

        foreach ([4 => 'catamaran', 6 => 'catamaran', 8 => 'monohull'] as $cabins => $category) {
            EntryFactory::id('yacht-'.$cabins)->collection($collection)->slug('yacht-'.$cabins)
                ->data(['title' => 'Yacht '.$cabins, 'cabins' => $cabins, 'category' => $category])
                ->create();
        }

        $collectionComponent = Livewire::test(LivewireCollection::class, ['params' => array_merge([
            'from' => 'yachts',
            'cabins:category' => 'catamaran',
        ], $scopeParams)])->assertSet('entriesCount', 2);
        $event = collect($collectionComponent->effects['dispatches'])->firstWhere('name', 'params-updated')['params'];

        foreach ([LfRangeFilter::class => 'gte', LfDualRangeFilter::class => 'dual_range'] as $component => $condition) {
            Livewire::test($component, [
                'field' => 'cabins',
                'blueprint' => 'yachts.yachts',
                'condition' => $condition,
                'min' => 2,
                'max' => 10,
            ])
                ->dispatch('params-updated', ...$event)
                ->assertSet('dynamicMin', 4)
                ->assertSet('dynamicMax', $expectedMax);
        }
    }

    public static function scopeParameters(): array
    {
        return [
            'filter alias' => [['filter' => 'cabins'], 6],
            'piped filter alias' => [['filter' => 'multiselect|cabins'], 6],
            'null query_scope falls back to filter' => [['query_scope' => null, 'filter' => 'cabins'], 6],
            'query_scope collision takes precedence' => [['query_scope' => 'cabins', 'filter' => 'multiselect'], 6],
            'unused filter alias cannot create a collision' => [[
                'query_scope' => 'multiselect',
                'filter' => 'cabins',
                'cabins:gte' => 6,
            ], 8],
            'empty query_scope suppresses the alias' => [[
                'query_scope' => '',
                'filter' => 'cabins',
                'cabins:gte' => 6,
            ], 8],
        ];
    }
}

class FieldValuesCabinsScope extends Scope
{
    protected static $handle = 'cabins';

    public function apply($query, $values)
    {
        if (isset($values['cabins:category'])) {
            $query->where('category', $values['cabins:category']);
        }
    }
}
