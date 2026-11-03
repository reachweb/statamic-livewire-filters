<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfCheckboxFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfDateFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfDualRangeFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfRadioFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfRangeFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfSelectFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfTextFilter;
use Reach\StatamicLivewireFilters\Http\Livewire\LfToggleFilter;
use Reach\StatamicLivewireFilters\Support\RangeBoundsQuery;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

/**
 * Wasted-render cluster: disabled/unchanged counts & bounds, client-rendered (wire:ignore root)
 * views, and foreign clear-option events must not send html back; real changes always must,
 * whatever the call order inside one pooled Livewire request.
 */
class RenderWasteRegressionTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    protected int $boundsRevision = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('statamic-livewire-filters.enable_filter_values_count', true);
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);

        $collection = Facades\Collection::make('cars')->save();
        $opts = fn (array $keys) => array_map(fn ($k) => ['key' => $k, 'value' => ucfirst($k)], $keys);
        Facades\Blueprint::make()->setContents(['sections' => ['main' => ['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'display' => 'Title']],
            ['handle' => 'color', 'field' => ['type' => 'checkboxes', 'display' => 'Color', 'options' => $opts(['red', 'blue', 'black'])]],
            ['handle' => 'fuel', 'field' => ['type' => 'select', 'display' => 'Fuel', 'options' => $opts(['petrol', 'diesel'])]],
            ['handle' => 'ac', 'field' => ['type' => 'radio', 'display' => 'AC', 'options' => $opts(['yes', 'no'])]],
            ['handle' => 'seats', 'field' => ['type' => 'integer', 'display' => 'Seats']],
            ['handle' => 'reg', 'field' => ['type' => 'date', 'display' => 'Registered']],
        ]]]])->setHandle('car')->setNamespace('collections.cars')->save();

        foreach ([['red', 'petrol', 'yes', 2, '2019-01-01'], ['blue', 'diesel', 'no', 5, '2021-01-01'], ['red', 'petrol', 'no', 7, '2024-01-01']] as $i => [$color, $fuel, $ac, $seats, $reg]) {
            EntryFactory::id("c$i")->collection($collection)->slug("c$i")->data([
                'title' => "Car $i", 'color' => [$color], 'fuel' => $fuel, 'ac' => $ac, 'seats' => $seats, 'reg' => $reg,
            ])->create();
        }
    }

    protected function filters(): array
    {
        return [
            'checkbox' => [LfCheckboxFilter::class, ['field' => 'color', 'blueprint' => 'cars.car', 'condition' => 'is']],
            'checkbox-advanced' => [LfCheckboxFilter::class, ['field' => 'color', 'blueprint' => 'cars.car', 'condition' => 'is', 'view' => 'lf-checkbox-advanced']],
            'select' => [LfSelectFilter::class, ['field' => 'fuel', 'blueprint' => 'cars.car', 'condition' => 'is']],
            'radio' => [LfRadioFilter::class, ['field' => 'ac', 'blueprint' => 'cars.car', 'condition' => 'is']],
            'text' => [LfTextFilter::class, ['field' => 'title', 'blueprint' => 'cars.car', 'condition' => 'contains']],
            'toggle' => [LfToggleFilter::class, ['field' => 'color', 'blueprint' => 'cars.car', 'condition' => 'is', 'preset_value' => 'red', 'label' => 'Red only']],
            'date' => [LfDateFilter::class, ['field' => 'reg', 'blueprint' => 'cars.car', 'condition' => 'is_after']],
            'range' => [LfRangeFilter::class, ['field' => 'seats', 'blueprint' => 'cars.car', 'condition' => 'gte', 'min' => 2, 'max' => 9, 'default' => 2]],
            'dual' => [LfDualRangeFilter::class, ['field' => 'seats', 'blueprint' => 'cars.car', 'min' => 2, 'max' => 9]],
        ];
    }

    protected function foreignTag(): array
    {
        return ['field' => 'some_other_field', 'value' => 'x', 'fieldLabel' => 'Other', 'optionLabel' => 'X', 'condition' => 'is'];
    }

    protected function paramsUpdatedArgs(array $params): array
    {
        return [$params, RangeBoundsQuery::encode('cars', $params, 'col', ++$this->boundsRevision, [])];
    }

    protected function dispatchCall(string $event, array $args): array
    {
        return ['path' => '', 'method' => '__dispatch', 'params' => [$event, $args]];
    }

    protected function assertRendered($component, string $message = ''): void
    {
        $this->assertArrayHasKey('html', $component->effects, $message ?: 'expected the component to render html');
    }

    protected function assertRenderSkipped($component, string $message = ''): void
    {
        $this->assertArrayNotHasKey('html', $component->effects, $message ?: 'expected the render to be skipped');
    }

    protected function useFixtureViews(string $dir): void
    {
        app('view')->prependNamespace('statamic-livewire-filters', __DIR__.'/../__fixtures__/views/'.$dir);
    }

    // ----------------------------------------------------------------- R1: disabled features

    #[Test]
    public function params_updated_is_ignored_without_a_render_when_counts_are_disabled()
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', false);

        $component = Livewire::test(...$this->filters()['checkbox']);

        $this->assertContains('params-updated', $component->effects['listeners'], 'the listener stays registered so pages rendered under another config never 500');

        $component->dispatch('params-updated', ...$this->paramsUpdatedArgs(['from' => 'cars']))
            ->assertNotDispatched('counts-updated')
            ->assertSet('statamic_field.counts', ['red' => null, 'blue' => null, 'black' => null]);

        $this->assertRenderSkipped($component);
    }

    #[Test]
    public function params_updated_is_ignored_without_a_render_when_bounds_are_disabled()
    {
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', false);

        foreach (['range', 'dual'] as $name) {
            $component = Livewire::test(...$this->filters()[$name]);

            $this->assertContains('params-updated', $component->effects['listeners']);

            $component->dispatch('params-updated', ['from' => 'cars'])
                ->assertNotDispatched('range-bounds-updated')
                ->assertSet('dynamicMin', null);

            $this->assertRenderSkipped($component, "$name must not render on a disabled bounds update");
        }
    }

    #[Test]
    public function a_page_rendered_while_a_feature_was_enabled_keeps_working_after_it_is_disabled()
    {
        $component = Livewire::test(...$this->filters()['checkbox'])
            ->dispatch('params-updated', ...$this->paramsUpdatedArgs(['from' => 'cars']))
            ->assertDispatched('counts-updated');

        Config::set('statamic-livewire-filters.enable_filter_values_count', false);

        $component->dispatch('params-updated', ...$this->paramsUpdatedArgs(['from' => 'cars', 'fuel:is' => 'petrol']))
            ->assertNotDispatched('counts-updated')
            ->assertSet('statamic_field.counts', ['red' => 2, 'blue' => 1, 'black' => 0]);

        $this->assertRenderSkipped($component);
    }

    // ----------------------------------------------------------------- R2: client-rendered roots

    #[Test]
    public function a_bare_wire_ignore_root_marks_the_filter_as_client_rendered()
    {
        Livewire::test(...$this->filters()['checkbox-advanced'])->assertSet('clientRendered', true);
        Livewire::test(LfSelectFilter::class, ['field' => 'fuel', 'blueprint' => 'cars.car', 'condition' => 'is', 'view' => 'lf-select-advanced'])->assertSet('clientRendered', true);

        foreach (['checkbox', 'select', 'radio', 'text', 'toggle', 'date', 'range', 'dual'] as $name) {
            Livewire::test(...$this->filters()[$name])->assertSet('clientRendered', false);
        }
    }

    #[Test]
    public function wire_ignore_self_children_or_a_quoted_wire_ignore_do_not_count_as_client_rendered()
    {
        $this->useFixtureViews('render-waste');

        foreach (['lf-checkbox-ignore-self', 'lf-checkbox-ignore-children', 'lf-checkbox-quoted-ignore'] as $view) {
            $component = Livewire::test(LfCheckboxFilter::class, ['field' => 'color', 'blueprint' => 'cars.car', 'condition' => 'is', 'view' => $view])
                ->assertSet('clientRendered', false)
                ->set('selected', ['red']);

            $this->assertRendered($component, "$view still needs its html");
        }

        Livewire::test(LfCheckboxFilter::class, ['field' => 'color', 'blueprint' => 'cars.car', 'condition' => 'is', 'view' => 'lf-checkbox-custom-ignore'])
            ->assertSet('clientRendered', true);
    }

    #[Test]
    public function a_client_rendered_filter_skips_the_html_on_every_path_but_keeps_data_and_events_in_sync()
    {
        $component = Livewire::test(...$this->filters()['checkbox-advanced']);

        $component->dispatch('params-updated', ...$this->paramsUpdatedArgs(['from' => 'cars']))
            ->assertDispatched('counts-updated', ['red' => 2, 'blue' => 1, 'black' => 0])
            ->assertSet('statamic_field.counts', ['red' => 2, 'blue' => 1, 'black' => 0]);
        $this->assertRenderSkipped($component, 'counts update');

        $component->set('selected', ['red', 'blue'])
            ->assertDispatched('filter-updated')
            ->assertSet('selected', ['red', 'blue']);
        $this->assertRenderSkipped($component, 'selection');
        $this->assertSame(['red', 'blue'], $component->snapshot['data']['selected'][0], 'the client merges the snapshot even without html');

        $component->dispatch('clear-option', ['field' => 'color', 'value' => 'red', 'fieldLabel' => 'Color', 'optionLabel' => 'Red', 'condition' => 'is'])
            ->assertDispatched('filter-updated')
            ->assertSet('selected', ['blue']);
        $this->assertRenderSkipped($component, 'own clear-option');

        $component->dispatch('clear-all-filters')
            ->assertDispatched('clear-filter')
            ->assertSet('selected', []);
        $this->assertRenderSkipped($component, 'clear-all');

        $component->dispatch('preset-params', ['color:is' => 'black'])
            ->assertSet('selected', ['black']);
        $this->assertRenderSkipped($component, 'preset-params');

        $component->call('$refresh');
        $this->assertRenderSkipped($component, 'refresh');
    }

    #[Test]
    public function a_client_rendered_filter_still_reports_validation_errors()
    {
        Config::set('statamic-livewire-filters.validate_filter_values', true);

        Livewire::test(...$this->filters()['checkbox-advanced'])
            ->set('selected', ['not-an-option'])
            ->assertHasErrors('selected')
            ->assertNotDispatched('filter-updated');
    }

    #[Test]
    public function a_published_view_that_reads_selected_server_side_renders_on_every_path()
    {
        $this->useFixtureViews('render-waste-published');

        $component = Livewire::test(...$this->filters()['checkbox'])->assertSet('clientRendered', false);

        $component->set('selected', ['red'])->assertSeeHtml('data-opt="red" class="active"');
        $this->assertRendered($component, 'selection');

        $component->dispatch('clear-option', ['field' => 'color', 'value' => 'red', 'fieldLabel' => 'Color', 'optionLabel' => 'Red', 'condition' => 'is'])
            ->assertDontSeeHtml('class="active"');
        $this->assertRendered($component, 'own clear-option');

        $component->dispatch('preset-params', ['color:is' => 'blue'])->assertSeeHtml('data-opt="blue" class="active"');
        $this->assertRendered($component, 'preset-params');

        $component->dispatch('clear-all-filters')->assertDontSeeHtml('class="active"');
        $this->assertRendered($component, 'clear-all');

        Config::set('statamic-livewire-filters.validate_filter_values', true);
        $component->set('selected', ['nope'])->assertHasErrors('selected')->assertSeeHtml('<p class="err">');
    }

    #[Test]
    public function unchanged_counts_skip_the_render_and_changed_counts_render()
    {
        $component = Livewire::test(...$this->filters()['checkbox']);

        $component->dispatch('params-updated', ...$this->paramsUpdatedArgs(['from' => 'cars']))->assertSee('(2)');
        $this->assertRendered($component, 'first counts');

        $component->dispatch('params-updated', ...$this->paramsUpdatedArgs(['from' => 'cars']))->assertDispatched('counts-updated');
        $this->assertRenderSkipped($component, 'identical counts');

        $component->dispatch('params-updated', ...$this->paramsUpdatedArgs(['from' => 'cars', 'fuel:is' => 'diesel']))->assertSee('(0)');
        $this->assertRendered($component, 'changed counts');
    }

    #[Test]
    public function unchanged_bounds_skip_the_render_and_changed_bounds_render()
    {
        $component = Livewire::test(...$this->filters()['dual']);

        $component->dispatch('params-updated', ...$this->paramsUpdatedArgs(['from' => 'cars']))->assertSet('dynamicMin', 2)->assertSet('dynamicMax', 7);
        $this->assertRendered($component, 'first bounds');

        $component->dispatch('params-updated', ...$this->paramsUpdatedArgs(['from' => 'cars']))->assertDispatched('range-bounds-updated');
        $this->assertRenderSkipped($component, 'identical bounds');

        $component->dispatch('params-updated', ...$this->paramsUpdatedArgs(['from' => 'cars', 'fuel:is' => 'petrol']))->assertSet('dynamicMax', 7)->assertSet('dynamicMin', 2);
        $this->assertRenderSkipped($component, 'same bounds from other params');

        $component->dispatch('params-updated', ...$this->paramsUpdatedArgs(['from' => 'cars', 'ac:is' => 'yes']))->assertSet('dynamicMax', 3);
        $this->assertRendered($component, 'narrowed bounds');
    }

    // ----------------------------------------------------------------- D#4: foreign clear-option

    #[Test]
    public function clear_option_for_another_field_skips_the_render_for_every_filter_type()
    {
        foreach ($this->filters() as $name => [$class, $params]) {
            $component = Livewire::test($class, $params)->dispatch('clear-option', $this->foreignTag());

            $this->assertRenderSkipped($component, "$name rendered on a foreign clear-option");
            $this->assertEmpty($component->effects['dispatches'] ?? [], "$name dispatched on a foreign clear-option");
        }
    }

    #[Test]
    public function clear_option_for_the_owning_field_still_renders_and_clears()
    {
        $this->useFixtureViews('render-waste-published');

        $component = Livewire::test(...$this->filters()['checkbox'])->set('selected', ['red', 'blue'])
            ->dispatch('clear-option', ['field' => 'color', 'value' => 'red', 'fieldLabel' => 'Color', 'optionLabel' => 'Red', 'condition' => 'is'])
            ->assertSet('selected', ['blue'])
            ->assertDispatched('filter-updated');
        $this->assertRendered($component, 'checkbox');

        $component = Livewire::test(...$this->filters()['radio'])->set('selected', 'yes')
            ->dispatch('clear-option', ['field' => 'ac', 'value' => 'yes', 'fieldLabel' => 'AC', 'optionLabel' => 'Yes', 'condition' => 'is'])
            ->assertSet('selected', '')
            ->assertDispatched('clear-filter');
        $this->assertRendered($component, 'radio');

        $component = Livewire::test(...$this->filters()['range'])->set('selected', 5)
            ->dispatch('clear-option', ['field' => 'seats', 'value' => '5', 'fieldLabel' => 'Seats', 'optionLabel' => '5', 'condition' => 'gte'])
            ->assertSet('selected', 2);
        $this->assertRendered($component, 'range');

        $component = Livewire::test(...$this->filters()['dual'])->set('selectedMin', 4)
            ->dispatch('clear-option', ['field' => 'seats', 'value' => '4', 'fieldLabel' => 'Seats', 'optionLabel' => '4', 'condition' => 'gte'])
            ->assertSet('selectedMin', 2)
            ->assertDispatched('dual-range-preset-values');
        $this->assertRendered($component, 'dual');
    }

    #[Test]
    public function a_pooled_request_renders_when_a_foreign_clear_option_comes_before_a_real_counts_update()
    {
        $component = Livewire::test(...$this->filters()['checkbox'])->update(calls: [
            $this->dispatchCall('clear-option', [$this->foreignTag()]),
            $this->dispatchCall('params-updated', $this->paramsUpdatedArgs(['from' => 'cars'])),
        ]);

        $component->assertDispatched('counts-updated')->assertSee('(2)');
        $this->assertRendered($component);
    }

    #[Test]
    public function a_pooled_request_renders_when_a_real_counts_update_comes_before_a_foreign_clear_option()
    {
        $component = Livewire::test(...$this->filters()['checkbox'])->update(calls: [
            $this->dispatchCall('params-updated', $this->paramsUpdatedArgs(['from' => 'cars'])),
            $this->dispatchCall('clear-option', [$this->foreignTag()]),
        ]);

        $component->assertDispatched('counts-updated')->assertSee('(2)');
        $this->assertRendered($component);
    }

    #[Test]
    public function a_pooled_request_renders_when_a_selection_update_travels_with_a_foreign_clear_option()
    {
        $this->useFixtureViews('render-waste-published');

        $component = Livewire::test(...$this->filters()['checkbox'])->update(
            calls: [$this->dispatchCall('clear-option', [$this->foreignTag()])],
            updates: ['selected' => ['red']],
        );

        $component->assertDispatched('filter-updated')->assertSet('selected', ['red']);
        $this->assertRendered($component);
    }

    #[Test]
    public function a_pooled_request_of_only_no_ops_skips_the_render()
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', false);

        $component = Livewire::test(...$this->filters()['checkbox'])->update(calls: [
            $this->dispatchCall('clear-option', [$this->foreignTag()]),
            $this->dispatchCall('params-updated', $this->paramsUpdatedArgs(['from' => 'cars'])),
            $this->dispatchCall('clear-option', [$this->foreignTag()]),
        ]);

        $this->assertRenderSkipped($component);
        $this->assertEmpty($component->effects['dispatches'] ?? []);
    }

    #[Test]
    public function a_pooled_clear_all_and_counts_update_renders_once_with_the_cleared_selection()
    {
        $component = Livewire::test(...$this->filters()['checkbox'])->set('selected', ['red'])->update(calls: [
            $this->dispatchCall('params-updated', $this->paramsUpdatedArgs(['from' => 'cars'])),
            $this->dispatchCall('clear-all-filters', []),
        ]);

        $component->assertSet('selected', [])->assertDispatched('clear-filter')->assertSee('(2)');
        $this->assertRendered($component);
    }
}
