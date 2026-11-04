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

        return <<<HTML
<script>
    window.livewireScriptConfig = window.livewireScriptConfig || { csrf: {$csrf} };
    (function () {
        function start() {
            if (window.__livewireStarted) {
                return;
            }
            if (!window.Livewire) {
                setTimeout(start, 50);
                return;
            }
            window.__livewireStarted = true;
            window.Livewire.start();
        }

        document.addEventListener('statamic:csrf.replaced', start, { once: true });
    })();
</script>
HTML;
    }

    /**
     * Canonical and robots tags for filtered listing pages.
     */
    public function seo(): string
    {
        $request = request();
        $params = Seo::livewireParams($request);
        $page = Seo::page($request, $params);
        $noindex = '<meta name="robots" content="noindex,follow">';

        // Every custom query string filter URL shares the base page's static cache
        // entry, so only page-dependent tags are safe here (the page query keeps its
        // own entry). Filter signals are sent as headers, see `seo_filter_headers`.
        if (CustomQueryString::enabled()) {
            return $page > 1 ? $this->canonical($request->url())."\n".$noindex : '';
        }

        $filters = Seo::filters($params);
        $selfCanonical = $page === 1 && Seo::indexableFilters($filters, $params);

        $tags = [$this->canonical($selfCanonical
            ? $request->url().'?'.http_build_query(['params' => Seo::canonicalParams($filters, $params)])
            : $request->url())];

        if ($page > 1 || ($filters !== [] && ! $selfCanonical)) {
            $tags[] = $noindex;
        }

        return implode("\n", $tags);
    }

    protected function canonical(string $url): string
    {
        return '<link rel="canonical" href="'.e($url).'">';
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
