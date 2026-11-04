<?php

namespace Reach\StatamicLivewireFilters\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Reach\StatamicLivewireFilters\Support\CustomQueryString;
use Reach\StatamicLivewireFilters\Support\Seo;
use Symfony\Component\HttpFoundation\Response;

/**
 * Robots and canonical headers for custom query string filter URLs. Every filter
 * URL of a page shares the base page's static cache entry, so these signals can't
 * live in the cached <head>. This runs as global middleware so the headers are
 * added after Statamic's half measure cache has captured the response headers.
 */
class AddFilterSeoHeaders
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        if (! config('statamic-livewire-filters.seo_filter_headers')
            || ! $response instanceof Response
            || ! $response->isSuccessful()
            || ! $request->attributes->get(HandleFiltersQueryString::FILTER_URL_ATTRIBUTE)) {
            return $response;
        }

        $params = is_array($request->input('params')) ? $request->input('params') : [];
        $filters = Seo::filters($params);
        $indexable = Seo::indexableFilters($filters, $params);
        $page = Seo::page($request);

        if (! $indexable || $page > 1) {
            $response->headers->set('X-Robots-Tag', 'noindex, follow');
        }

        // Later pages get their canonical from the head, which is cached per query string.
        if ($page === 1) {
            // The request URL is already rewritten to the base page. The self canonical is
            // rebuilt from the parsed filters, so unknown or trailing segments never reach it.
            $canonical = $indexable
                ? rtrim($request->url(), '/').'/'.CustomQueryString::prefix().'/'.Seo::canonicalSegments($filters)
                : $request->url();

            $canonical = strtr($canonical, ['<' => '%3C', '>' => '%3E']);

            $response->headers->set('Link', '<'.$canonical.'>; rel="canonical"', false);
        }

        return $response;
    }
}
