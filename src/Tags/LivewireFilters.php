<?php

namespace Reach\StatamicLivewireFilters\Tags;

use Reach\StatamicLivewireFilters\Support\CustomQueryString;
use Reach\StatamicLivewireFilters\Support\Seo;
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
        // Setting livewireScriptConfig turns off Livewire's own start on DOMContentLoaded, so the script
        // waits for it too: alpine:init listeners later in the page (Alpine.store, Alpine.data) must be
        // registered before Alpine starts.
        return <<<HTML
<meta name="livewire-filters-csrf" content="{$token}">
<script>
    window.livewireScriptConfig = window.livewireScriptConfig || { csrf: {$csrf} };
    (function () {
        function start() {
            if (window.__livewireStarted) {
                return;
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', start, { once: true });
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

    /**
     * Canonical and robots tags for filtered listing pages. With `canonical="false"`
     * only the robots tag is output, for layouts that already have a canonical.
     */
    public function seo(): string
    {
        $request = request();
        $params = Seo::livewireParams($request);
        $laterPage = Seo::laterPage($request, $params);
        $pageCanonical = $request->url().($laterPage ? '?'.http_build_query($laterPage) : '');

        // Every custom query string filter URL shares the base page's static cache
        // entry, so only page-dependent tags are safe here (the page query keeps its
        // own entry). Filter signals are sent as headers, see `seo_filter_headers`.
        if (CustomQueryString::enabled()) {
            return $laterPage ? $this->seoTags($pageCanonical) : '';
        }

        $filters = Seo::filters($params);

        if ($filters === []) {
            return $this->seoTags($pageCanonical);
        }

        // Only the first page of an indexable filter is indexed. Its later pages
        // point to the base page like any other filtered page.
        if (! $laterPage && Seo::indexableFilters($filters, $params)) {
            return $this->seoTags($request->url().'?'.http_build_query(['params' => Seo::canonicalParams($filters, $params)]));
        }

        return $this->seoTags($request->url(), noindex: true);
    }

    protected function seoTags(string $canonical, bool $noindex = false): string
    {
        return implode("\n", array_filter([
            $this->params->bool('canonical', true) ? '<link rel="canonical" href="'.e($canonical).'">' : null,
            $noindex ? '<meta name="robots" content="noindex,follow">' : null,
        ]));
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
