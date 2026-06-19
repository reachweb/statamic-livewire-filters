<?php

namespace Reach\StatamicLivewireFilters\Tests\Tags;

use Illuminate\Support\Facades\Config;
use Reach\StatamicLivewireFilters\Tags\LivewireFilters;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades\Antlers;

class HeadTest extends TestCase
{
    private LivewireFilters $headTag;

    protected function setUp(): void
    {
        parent::setUp();

        $this->headTag = (new LivewireFilters)
            ->setParser(Antlers::parser())
            ->setContext([]);
    }

    public function test_it_returns_empty_string_when_static_caching_is_disabled()
    {
        Config::set('statamic.static_caching.strategy', null);

        $this->assertSame('', $this->headTag->head());
    }

    public function test_it_returns_empty_string_for_half_caching()
    {
        Config::set('statamic.static_caching.strategy', 'half');

        $this->assertSame('', $this->headTag->head());
    }

    public function test_it_emits_script_for_full_caching()
    {
        Config::set('statamic.static_caching.strategy', 'full');

        $output = $this->headTag->head();

        $this->assertStringContainsString('window.livewireScriptConfig', $output);
        $this->assertStringContainsString('csrf: '.json_encode(csrf_token()), $output);

        $this->assertStringContainsString("'statamic:csrf.replaced'", $output);

        $this->assertStringContainsString('__livewireStarted', $output);
        $this->assertStringContainsString('Livewire.start()', $output);
    }
}
