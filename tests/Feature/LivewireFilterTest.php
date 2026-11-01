<?php

namespace Tests\Feature;

use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfCheckboxFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfDateFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfRadioFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfRangeFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfSelectFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfTextFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfToggleFilter;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class LivewireFilterTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    protected function setUp(): void
    {
        parent::setUp();

        $collection = Facades\Collection::make('pages')->save();
        Facades\Blueprint::make()->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        ['handle' => 'title', 'field' => ['type' => 'text']],
                        ['handle' => 'item_options', 'field' => ['type' => 'select', 'options' => ['option1' => 'Option 1', 'option2' => 'Option 2']]],
                        ['handle' => 'max_items', 'field' => ['type' => 'integer']],
                        ['handle' => 'item_from', 'field' => ['type' => 'date', 'mode' => 'single']],
                    ],
                ],
            ],
        ])->setHandle('pages')->setNamespace('collections.'.$collection->handle())->save();
    }

    public static function filters(): array
    {
        return [
            'checkbox' => [LfCheckboxFilter::class, ['field' => 'item_options', 'condition' => 'is']],
            'radio' => [LfRadioFilter::class, ['field' => 'item_options', 'condition' => 'is']],
            'select' => [LfSelectFilter::class, ['field' => 'item_options', 'condition' => 'is']],
            'text' => [LfTextFilter::class, ['field' => 'title', 'condition' => 'contains']],
            'toggle' => [LfToggleFilter::class, ['field' => 'item_options', 'condition' => 'is', 'preset_value' => 'option1']],
            'date' => [LfDateFilter::class, ['field' => 'item_from', 'condition' => 'is_after']],
            'range' => [LfRangeFilter::class, ['field' => 'max_items', 'condition' => 'gte', 'min' => 1, 'max' => 4]],
        ];
    }

    #[Test]
    #[DataProvider('filters')]
    public function it_forbids_client_tampering_with_the_view_property(string $component, array $params)
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test($component, [...$params, 'blueprint' => 'pages.pages'])
            ->set('view', 'lf-dual-range');
    }

    #[Test]
    public function the_range_filter_sends_its_own_modifier_when_updated()
    {
        Livewire::test(LfRangeFilter::class, ['field' => 'max_items', 'blueprint' => 'pages.pages', 'condition' => 'gte', 'modifier' => 'custom', 'min' => 1, 'max' => 4])
            ->set('selected', 3)
            ->assertDispatched('filter-updated', field: 'max_items', condition: 'gte', payload: 3, modifier: 'custom');
    }
}
