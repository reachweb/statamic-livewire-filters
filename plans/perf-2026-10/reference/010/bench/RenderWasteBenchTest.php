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
 * Verifier V3 bench for the "wasted render" cluster (R1 listeners-by-config, R2 discarded/redundant
 * html, D#4 clear-option non-owner renders).
 *
 * Run: BENCH_TAG=before vendor/bin/phpunit tests/Bench/RenderWasteBenchTest.php
 * Output: $BENCH_OUT or tests/Bench/render-waste-<tag>.md
 */
class RenderWasteBenchTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    public const TERMS = 500;

    protected static array $out = [];

    protected function setUp(): void
    {
        parent::setUp();

        Facades\Taxonomy::make('brands')->save();
        for ($i = 1; $i <= self::TERMS; $i++) {
            Facades\Term::make()->taxonomy('brands')->inDefaultLocale()->slug("brand-$i")->data(['title' => "Brand $i"])->saveQuietly();
        }

        $collection = Facades\Collection::make('cars')->taxonomies(['brands'])->save();
        $opts = fn (array $keys) => array_map(fn ($k) => ['key' => $k, 'value' => ucfirst($k)], $keys);
        Facades\Blueprint::make()->setContents(['sections' => ['main' => ['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'display' => 'Title']],
            ['handle' => 'brands', 'field' => ['type' => 'terms', 'taxonomies' => ['brands'], 'display' => 'Brand', 'mode' => 'select']],
            ['handle' => 'color', 'field' => ['type' => 'checkboxes', 'display' => 'Color', 'options' => $opts(['red', 'blue', 'black', 'white', 'silver', 'green', 'grey', 'yellow'])]],
            ['handle' => 'fuel', 'field' => ['type' => 'select', 'display' => 'Fuel', 'options' => $opts(['petrol', 'diesel', 'electric'])]],
            ['handle' => 'ac', 'field' => ['type' => 'radio', 'display' => 'AC', 'options' => $opts(['yes', 'no'])]],
            ['handle' => 'seats', 'field' => ['type' => 'integer', 'display' => 'Seats']],
            ['handle' => 'reg', 'field' => ['type' => 'date', 'display' => 'Registered']],
        ]]]])->setHandle('car')->setNamespace('collections.cars')->save();

        for ($i = 0; $i < 40; $i++) {
            EntryFactory::id("c$i")->collection($collection)->slug("c$i")->data([
                'title' => "Car $i",
                'brands' => ['brand-'.(($i % self::TERMS) + 1)],
                'color' => [['red', 'blue', 'black', 'white'][$i % 4]],
                'fuel' => ['petrol', 'diesel', 'electric'][$i % 3],
                'ac' => $i % 2 ? 'yes' : 'no',
                'seats' => ($i % 8) + 2,
                'reg' => '20'.(19 + $i % 7).'-01-01',
            ])->create();
        }
    }

    protected static function line(string $s): void
    {
        self::$out[] = $s;
        fwrite(STDERR, $s."\n");
    }

    public static function tearDownAfterClass(): void
    {
        $tag = getenv('BENCH_TAG') ?: 'run';
        $file = getenv('BENCH_OUT') ?: __DIR__.'/render-waste-'.$tag.'.md';
        file_put_contents($file, implode("\n", self::$out)."\n");
        parent::tearDownAfterClass();
    }

    protected function filters(): array
    {
        return [
            'checkbox' => [LfCheckboxFilter::class, ['field' => 'color', 'blueprint' => 'cars.car', 'condition' => 'is']],
            'checkbox-adv(500)' => [LfCheckboxFilter::class, ['field' => 'brands', 'blueprint' => 'cars.car', 'condition' => 'taxonomy', 'view' => 'lf-checkbox-advanced', 'searchable' => true]],
            'select' => [LfSelectFilter::class, ['field' => 'fuel', 'blueprint' => 'cars.car', 'condition' => 'is']],
            'radio' => [LfRadioFilter::class, ['field' => 'ac', 'blueprint' => 'cars.car', 'condition' => 'is']],
            'text' => [LfTextFilter::class, ['field' => 'title', 'blueprint' => 'cars.car', 'condition' => 'contains']],
            'toggle' => [LfToggleFilter::class, ['field' => 'color', 'blueprint' => 'cars.car', 'condition' => 'is', 'preset_value' => 'red', 'label' => 'Red only']],
            'date' => [LfDateFilter::class, ['field' => 'reg', 'blueprint' => 'cars.car', 'condition' => 'is_after']],
            'range' => [LfRangeFilter::class, ['field' => 'seats', 'blueprint' => 'cars.car', 'condition' => 'gte', 'min' => 2, 'max' => 9, 'default' => 2]],
            'dual' => [LfDualRangeFilter::class, ['field' => 'seats', 'blueprint' => 'cars.car', 'min' => 2, 'max' => 9]],
        ];
    }

    protected function paramsUpdatedArgs(array $params = ['from' => 'cars']): array
    {
        $args = [$params];
        if (config('statamic-livewire-filters.enable_dynamic_range_bounds')) {
            $args[] = RangeBoundsQuery::encode('cars', $params, 'col', 1, []);
        }

        return $args;
    }

    /** request bytes the client would upload for this component on a fan-out (snapshot + the __dispatch call) */
    protected function uploadBytes($c, array $args): int
    {
        return strlen(json_encode(['snapshot' => json_encode($c->snapshot), 'updates' => (object) [], 'calls' => [['path' => '', 'method' => '__dispatch', 'params' => ['params-updated', $args]]]]));
    }

    protected function htmlBytes($c): string
    {
        return isset($c->effects['html']) ? (string) strlen($c->effects['html']) : 'skipped';
    }

    /** R1: with a mixed config, which filters are still POSTed on params-updated and what do they render back? */
    #[Test]
    public function r1_params_updated_listeners_and_render_by_config(): void
    {
        self::line('## R1. params-updated fan-out per config: listener registered? / upload bytes / html bytes rendered back');
        self::line('| config | filter | listens | upload B | html B |');
        self::line('|---|---|---|---|---|');
        foreach ([[true, false], [false, true], [false, false], [true, true]] as [$counts, $bounds]) {
            Config::set('statamic-livewire-filters.enable_filter_values_count', $counts);
            Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', $bounds);
            $cfg = 'counts='.($counts ? 1 : 0).' bounds='.($bounds ? 1 : 0);
            $up = 0;
            $html = 0;
            foreach ($this->filters() as $name => [$class, $params]) {
                $c = Livewire::test($class, $params);
                $listens = in_array('params-updated', $c->effects['listeners'] ?? [], true);
                $u = '-';
                $h = '-';
                if ($listens) {
                    $args = $this->paramsUpdatedArgs();
                    $u = $this->uploadBytes($c, $args);
                    $c->dispatch('params-updated', ...$args);
                    $h = $this->htmlBytes($c);
                    $up += $u;
                    $html += is_numeric($h) ? (int) $h : 0;
                }
                self::line("| $cfg | $name | ".($listens ? 'yes' : 'no')." | $u | $h |");
            }
            self::line("| $cfg | TOTAL | | $up | $html |");
        }
        $this->assertTrue(true);
    }

    /** R2: html rendered back per interaction that the client either discards (wire:ignore root) or does not need. */
    #[Test]
    public function r2_html_per_interaction(): void
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', true);
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);
        self::line('## R2. html bytes rendered back per interaction (counts=1 bounds=1)');
        self::line('| component | interaction | response html B | response snapshot B | server ms |');
        self::line('|---|---|---|---|---|');
        $f = $this->filters();

        $cases = [
            ['checkbox-adv(500)', 'params-updated (counts)', fn ($c) => $c->dispatch('params-updated', ...$this->paramsUpdatedArgs(['from' => 'cars', 'fuel:is' => 'petrol']))],
            ['checkbox-adv(500)', 'set selected', fn ($c) => $c->set('selected', ['brand-1'])],
            ['checkbox-adv(500)', 'clear-all-filters', fn ($c) => $c->dispatch('clear-all-filters')],
            ['checkbox', 'set selected', fn ($c) => $c->set('selected', ['red'])],
            ['checkbox', 'params-updated (counts)', fn ($c) => $c->dispatch('params-updated', ...$this->paramsUpdatedArgs(['from' => 'cars', 'fuel:is' => 'petrol']))],
            ['select', 'set selected', fn ($c) => $c->set('selected', 'petrol')],
            ['select', 'params-updated (counts)', fn ($c) => $c->dispatch('params-updated', ...$this->paramsUpdatedArgs(['from' => 'cars', 'ac:is' => 'yes']))],
            ['radio', 'set selected', fn ($c) => $c->set('selected', 'yes')],
            ['radio', 'params-updated (counts)', fn ($c) => $c->dispatch('params-updated', ...$this->paramsUpdatedArgs(['from' => 'cars', 'fuel:is' => 'petrol']))],
        ];
        foreach ($cases as [$name, $label, $action]) {
            [$class, $params] = $f[$name];
            $c = Livewire::test($class, $params);
            $t0 = hrtime(true);
            $action($c);
            $ms = round((hrtime(true) - $t0) / 1e6, 1);
            self::line("| $name | $label | ".$this->htmlBytes($c).' | '.strlen(json_encode($c->snapshot))." | $ms |");
        }

        // plain checkbox with 500 taxonomy terms: the B finding (350 KB per click)
        $c = Livewire::test(LfCheckboxFilter::class, ['field' => 'brands', 'blueprint' => 'cars.car', 'condition' => 'taxonomy']);
        $t0 = hrtime(true);
        $c->set('selected', ['brand-1']);
        $ms = round((hrtime(true) - $t0) / 1e6, 1);
        self::line('| checkbox-plain(500) | set selected | '.$this->htmlBytes($c).' | '.strlen(json_encode($c->snapshot))." | $ms |");
        $t0 = hrtime(true);
        $c->dispatch('params-updated', ...$this->paramsUpdatedArgs(['from' => 'cars', 'fuel:is' => 'petrol']));
        $ms = round((hrtime(true) - $t0) / 1e6, 1);
        self::line('| checkbox-plain(500) | params-updated (counts) | '.$this->htmlBytes($c).' | '.strlen(json_encode($c->snapshot))." | $ms |");
        $this->assertTrue(true);
    }

    /** D#4: clear-option for a tag of ANOTHER field — does the non-owning filter render? */
    #[Test]
    public function d4_clear_option_non_owner_render(): void
    {
        Config::set('statamic-livewire-filters.enable_filter_values_count', true);
        Config::set('statamic-livewire-filters.enable_dynamic_range_bounds', true);
        self::line('## D4. clear-option for a foreign field: html bytes rendered by the non-owning filter');
        self::line('| filter | rendered? | html B |');
        self::line('|---|---|---|');
        $tag = ['field' => 'some_other_field', 'value' => 'x', 'fieldLabel' => 'Other', 'optionLabel' => 'X', 'condition' => 'is'];
        $total = 0;
        foreach ($this->filters() as $name => [$class, $params]) {
            $c = Livewire::test($class, $params);
            $c->dispatch('clear-option', $tag);
            $h = $this->htmlBytes($c);
            $total += is_numeric($h) ? (int) $h : 0;
            self::line("| $name | ".(is_numeric($h) ? 'yes' : 'no')." | $h |");
        }
        self::line("| TOTAL | | $total |");
        $this->assertTrue(true);
    }
}
