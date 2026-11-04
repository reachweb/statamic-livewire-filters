<?php

namespace Reach\StatamicLivewireFilters\Tags;

use Statamic\Tags\Tags;

class LivewireFilters extends Tags
{
    protected static $handle = 'livewire-filters';

    public function head(): string
    {
        if (config('statamic.static_caching.strategy') !== 'full') {
            return '';
        }

        $csrf = json_encode(csrf_token());
        $token = e(csrf_token());

        // Statamic swaps the placeholder in the meta tag before firing 'statamic:csrf.replaced',
        // so the script can tell the event already fired when it runs late (e.g. queued behind a
        // stylesheet). The placeholder is split so Statamic's server-side replacement leaves it alone.
        return <<<HTML
<meta name="livewire-filters-csrf" content="{$token}">
<script>
    window.livewireScriptConfig = window.livewireScriptConfig || { csrf: {$csrf} };
    (function () {
        function start() {
            if (window.__livewireStarted) {
                return;
            }
            if (!window.Livewire) {
                // Every script has run once the page has loaded, so a page without Livewire stops polling.
                if (document.readyState !== 'complete') {
                    setTimeout(start, 50);
                }
                return;
            }
            window.__livewireStarted = true;
            window.Livewire.start();
        }

        document.addEventListener('statamic:csrf.replaced', start, { once: true });

        var token = document.querySelector('meta[name="livewire-filters-csrf"]').content;
        if (token && token !== 'STATAMIC_' + 'CSRF_TOKEN') {
            window.livewireScriptConfig.csrf = token;
            start();
        }
    })();
</script>
HTML;
    }

    public function loadMore(): string
    {
        if (! $this->context->get('has_more_pages')) {
            return '';
        }

        return view('statamic-livewire-filters::livewire.ui.load-more', [
            'auto' => $this->params->bool('auto', false),
            'text' => $this->params->get('text'),
            'class' => $this->params->get('class'),
        ])->render();
    }
}
