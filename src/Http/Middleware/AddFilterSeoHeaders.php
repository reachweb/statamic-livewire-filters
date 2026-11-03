<?php

namespace Reach\StatamicLivewireFilters\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
            || ! $request->attributes->has(HandleFiltersQueryString::ORIGINAL_PATH_ATTRIBUTE)) {
            return $response;
        }

        $urlParams = Seo::urlParams($request);
        $filters = Seo::filters($urlParams);

        if ($filters === []) {
            return $response;
        }

        $indexable = Seo::indexableFilters($filters, $urlParams);
        $page = Seo::page($request);

        if (! $indexable || $page > 1) {
            $response->headers->set('X-Robots-Tag', 'noindex, follow');
        }

        // Later pages get their canonical from the head, which is cached per query string.
        if ($page === 1) {
            $canonical = $indexable
                ? url($request->attributes->get(HandleFiltersQueryString::ORIGINAL_PATH_ATTRIBUTE))
                : $request->url();

            $response->headers->set('Link', '<'.$canonical.'>; rel="canonical"', false);
        }

        return $response;
    }
}
