<?php

namespace Reach\StatamicLivewireFilters\Tests\Tags;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Reach\StatamicLivewireFilters\Tags\LivewireFilters;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades\Antlers;
use Statamic\StaticCaching\Replacers\CsrfTokenReplacer;

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

        // Livewire must not start before the page is parsed, or later alpine:init listeners miss the event.
        $this->assertStringContainsString("document.addEventListener('DOMContentLoaded', start", $output);
    }

    public function test_it_starts_livewire_when_the_csrf_token_was_already_replaced()
    {
        Config::set('statamic.static_caching.strategy', 'full');
        $this->startSession();

        $replacer = new CsrfTokenReplacer;

        // The cached copy carries the placeholder, which Statamic's CSRF script swaps
        // before firing 'statamic:csrf.replaced'.
        $cached = new Response($this->headTag->head());
        $replacer->prepareResponseToCache($cached, new Response('<head></head>'));
        $this->assertStringContainsString('<meta name="livewire-filters-csrf" content="STATAMIC_CSRF_TOKEN">', $cached->getContent());
        $this->assertStringNotContainsString(csrf_token(), $cached->getContent());

        // Served through PHP, the placeholder becomes the visitor's token.
        $served = clone $cached;
        $replacer->replaceInCachedResponse($served);
        $this->assertStringContainsString('<meta name="livewire-filters-csrf" content="'.csrf_token().'">', $served->getContent());

        // In both, the script starts Livewire when the meta no longer holds the placeholder,
        // and the comparison survives Statamic's server-side replacement.
        $startWhenReplaced = <<<'JS'
        var token = document.querySelector('meta[name="livewire-filters-csrf"]').content;
        if (token && token !== 'STATAMIC_' + 'CSRF_TOKEN') {
            window.livewireScriptConfig.csrf = token;
            start();
        }
JS;

        $this->assertStringContainsString($startWhenReplaced, $cached->getContent());
        $this->assertStringContainsString($startWhenReplaced, $served->getContent());
    }
}
