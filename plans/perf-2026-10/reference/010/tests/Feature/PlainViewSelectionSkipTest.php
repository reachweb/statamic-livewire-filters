<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfCheckboxFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfRadioFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfSelectFilter;
use Reach\StatamicLivewireFilters\Support\RangeBoundsQuery;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

/**
 * Tier 2 (optional): the addon's own plain checkbox/radio/select views keep the selection
 * client-side, so a selection sends no html back; any published or custom view still renders.
 */
class PlainViewSelectionSkipTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('statamic-livewire-filters.enable_filter_values_count', true);

        $collection = Facades\Collection::make('cars')->save();
        $opts = fn (array $keys) => array_map(fn ($k) => ['key' => $k, 'value' => ucfirst($k)], $keys);
        Facades\Blueprint::make()->setContents(['sections' => ['main' => ['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'display' => 'Title']],
            ['handle' => 'color', 'field' => ['type' => 'checkboxes', 'display' => 'Color', 'options' => $opts(['red', 'blue', 'black'])]],
            ['handle' => 'fuel', 'field' => ['type' => 'select', 'display' => 'Fuel', 'options' => $opts(['petrol', 'diesel'])]],
        ]]]])->setHandle('car')->setNamespace('collections.cars')->save();

        foreach ([['red', 'petrol'], ['blue', 'diesel'], ['red', 'petrol']] as $i => [$color, $fuel]) {
            EntryFactory::id("c$i")->collection($collection)->slug("c$i")->data(['title' => "Car $i", 'color' => [$color], 'fuel' => $fuel])->create();
        }
    }

    protected function checkbox(array $extra = []): array
    {
        return [LfCheckboxFilter::class, ['field' => 'color', 'blueprint' => 'cars.car', 'condition' => 'is'] + $extra];
    }

    #[Test]
    public function a_selection_on_the_stock_plain_views_sends_no_html_but_keeps_data_and_events()
    {
        $component = Livewire::test(...$this->checkbox())->set('selected', ['red'])
            ->assertDispatched('filter-updated')->assertSet('selected', ['red']);
        $this->assertArrayNotHasKey('html', $component->effects);
        $this->assertSame(['red'], $component->snapshot['data']['selected'][0]);

        $component = Livewire::test(LfRadioFilter::class, ['field' => 'fuel', 'blueprint' => 'cars.car', 'condition' => 'is'])->set('selected', 'petrol')
            ->assertDispatched('filter-updated');
        $this->assertArrayNotHasKey('html', $component->effects);

        $component = Livewire::test(LfSelectFilter::class, ['field' => 'fuel', 'blueprint' => 'cars.car', 'condition' => 'is'])->set('selected', 'petrol')
            ->assertDispatched('filter-updated');
        $this->assertArrayNotHasKey('html', $component->effects);

        $component->set('selected', '')->assertDispatched('clear-filter');
        $this->assertArrayHasKey('html', $component->effects, 'clearing goes through clear() and renders');
    }

    #[Test]
    public function the_stock_clear_button_is_always_rendered_and_driven_by_wire_selected()
    {
        Livewire::test(...$this->checkbox())
            ->assertSeeHtml('x-show="$wire.selected.length > 0"')
            ->assertSeeHtml('wire:click="clear"');
    }

    #[Test]
    public function own_clear_option_on_the_stock_checkbox_skips_the_html_and_updates_the_data()
    {
        $component = Livewire::test(...$this->checkbox())->set('selected', ['red', 'blue'])
            ->dispatch('clear-option', ['field' => 'color', 'value' => 'red', 'fieldLabel' => 'Color', 'optionLabel' => 'Red', 'condition' => 'is'])
            ->assertSet('selected', ['blue'])
            ->assertDispatched('filter-updated');
        $this->assertArrayNotHasKey('html', $component->effects);
    }

    #[Test]
    public function a_published_or_custom_view_keeps_rendering_on_a_selection()
    {
        app('view')->prependNamespace('statamic-livewire-filters', __DIR__.'/../__fixtures__/views/render-waste-published');
        $component = Livewire::test(...$this->checkbox())->set('selected', ['red'])->assertSeeHtml('data-opt="red" class="active"');
        $this->assertArrayHasKey('html', $component->effects, 'a published lf-checkbox is not the stock file');

        app('view')->prependNamespace('statamic-livewire-filters', __DIR__.'/../__fixtures__/views/render-waste');
        $component = Livewire::test(...$this->checkbox(['view' => 'lf-checkbox-custom-plain']))->set('selected', ['blue'])->assertSeeHtml('data-opt="blue" class="active"');
        $this->assertArrayHasKey('html', $component->effects, 'a custom view name is never the stock file');
    }

    #[Test]
    public function a_validation_error_on_the_stock_view_still_renders()
    {
        Config::set('statamic-livewire-filters.validate_filter_values', true);

        $component = Livewire::test(...$this->checkbox())->set('selected', ['nope'])->assertHasErrors('selected')->assertNotDispatched('filter-updated');
        $this->assertArrayHasKey('html', $component->effects);
    }

    #[Test]
    public function a_selection_pooled_with_a_counts_change_still_renders_the_new_counts()
    {
        $component = Livewire::test(...$this->checkbox())->update(
            calls: [['path' => '', 'method' => '__dispatch', 'params' => ['params-updated', [['from' => 'cars', 'fuel:is' => 'diesel'], RangeBoundsQuery::encode('cars', ['from' => 'cars', 'fuel:is' => 'diesel'], 'col', 1, [])]]]],
            updates: ['selected' => ['red']],
        );

        $component->assertDispatched('filter-updated')->assertDispatched('counts-updated')->assertSee('(0)');
        $this->assertArrayHasKey('html', $component->effects, 'the counts change must win over the selection skip');
    }
}
