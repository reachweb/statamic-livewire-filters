<?php

namespace Reach\StatamicLivewireFilters\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Reach\StatamicLivewireFilters\Support\CustomQueryString;
use Reach\StatamicLivewireFilters\Support\Nocache;

class HandleFiltersQueryString
{
    /**
     * Keys we must never overwrite when hydrating the request from the target URL's
     * query string, since Statamic's nocache controller and Livewire rely on them.
     * Note that `params` is intentionally not reserved: it is the query key Livewire
     * uses to restore filter state when `enable_query_string` is on.
     */
    protected const RESERVED_INPUT_KEYS = ['url', '_token', '_method', 'fingerprint', 'serialized', 'effects'];

    public function handle(Request $request, Closure $next): mixed
    {
        if (Nocache::matches($request)) {
            $this->hydrateNocacheRequestFromUrl($request);

            return $next($request);
        }

        // Plain page loads are served by the filters route, which keeps the pretty URL.
        if (! CustomQueryString::enabled() || ! $request->hasHeader('X-Livewire')) {
            return $next($request);
        }

        $referer = $request->headers->get('referer');
        $refererPath = $referer ? parse_url($referer, PHP_URL_PATH) : null;

        if (is_string($refererPath) && ($split = CustomQueryString::split($refererPath))) {
            $request->merge(['params' => CustomQueryString::parseSegments($split[1])]);
        }

        return $next($request);
    }

    protected function hydrateNocacheRequestFromUrl(Request $request): void
    {
        $url = $request->input('url');

        if (! is_string($url) || $url === '') {
            return;
        }

        $parsed = parse_url($url);

        if (! is_array($parsed)) {
            return;
        }

        if (CustomQueryString::livewireQueryStringEnabled()) {
            $this->hydrateFromOriginalQueryString($request, $parsed);

            return;
        }

        if (! isset($parsed['path']) || ! $split = CustomQueryString::split($parsed['path'])) {
            return;
        }

        [$basePath, $filterSegments] = $split;
        $params = CustomQueryString::parseSegments($filterSegments);

        if (! empty($params)) {
            $request->merge(['params' => $params]);
        }

        $basePath = '/'.$basePath;

        $normalized = ($parsed['scheme'] ?? 'http').'://'.($parsed['host'] ?? $request->getHost());

        if (isset($parsed['port'])) {
            $normalized .= ':'.$parsed['port'];
        }

        $normalized .= $basePath === '/' ? '/' : $basePath;

        if (! empty($parsed['query'])) {
            $normalized .= '?'.$parsed['query'];
        }

        $request->merge(['url' => $normalized]);
    }

    protected function hydrateFromOriginalQueryString(Request $request, array $parsed): void
    {
        if (empty($parsed['query'])) {
            return;
        }

        parse_str($parsed['query'], $originalQuery);

        if (! is_array($originalQuery) || $originalQuery === []) {
            return;
        }

        $safeQuery = array_diff_key($originalQuery, array_flip(self::RESERVED_INPUT_KEYS));

        if ($safeQuery === []) {
            return;
        }

        foreach ($safeQuery as $key => $value) {
            $request->query->set($key, $value);
        }

        $request->merge($safeQuery);
    }
}
