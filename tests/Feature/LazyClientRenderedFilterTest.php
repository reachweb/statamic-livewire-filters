<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Facades\Reach\StatamicLivewireFilters\Tests\Factories\EntryFactory;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\LfCheckboxFilter;
use Reach\StatamicLivewireFilters\Tests\DispatchesParamsUpdated;
use Reach\StatamicLivewireFilters\Tests\PreventSavingStacheItemsToDisk;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades;

class LazyClientRenderedFilterTest extends TestCase
{
    use DispatchesParamsUpdated, PreventSavingStacheItemsToDisk;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('statamic-livewire-filters.enable_filter_values_count', true);
        $collection = Facades\Collection::make('cars')->save();
        Facades\Blueprint::make()->setContents(['sections' => ['main' => ['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'color', 'field' => ['type' => 'checkboxes', 'options' => [['key' => 'red', 'value' => 'Red'], ['key' => 'blue', 'value' => 'Blue']]]],
        ]]]])->setHandle('car')->setNamespace('collections.cars')->save();
        EntryFactory::id('c0')->collection($collection)->slug('c0')->data(['title' => 'Car', 'color' => ['red']])->create();
    }

    #[Test]
    public function a_lazy_client_rendered_filter_is_detected_on_its_lazy_load_render_and_skips_afterwards()
    {
        $component = Livewire::test(LfCheckboxFilter::class, ['field' => 'color', 'blueprint' => 'cars.car', 'condition' => 'is', 'view' => 'lf-checkbox-advanced', 'lazy' => true]);

        $placeholder = $component->html();
        $this->assertStringContainsString('__lazyLoad', $placeholder);
        $this->assertFalse($component->get('clientRendered'));

        $decoded = html_entity_decode($placeholder, ENT_QUOTES);
        preg_match("/__lazyLoad\\('([^']+)'\\)/", $decoded, $m);
        $component->call('__lazyLoad', $m[1]);
        $this->assertTrue($component->get('clientRendered'));
        $this->assertArrayHasKey('html', $component->effects, 'the lazy-load render itself must be sent');

        $component->dispatch('params-updated', ...$this->paramsUpdatedArguments(['from' => 'cars'], 'cars'))->assertDispatched('counts-updated');
        $this->assertArrayNotHasKey('html', $component->effects);
    }
}
