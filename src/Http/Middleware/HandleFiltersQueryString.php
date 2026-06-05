<?php

namespace Reach\StatamicLivewireFilters\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

        if ($this->shouldSkip()) {
            return $next($request);
        }

        $isLivewireRequest = $request->hasHeader('X-Livewire');

        if (! $isLivewireRequest && ! $this->shouldProcessRequest($request)) {
            return $next($request);
        }

        if ($isLivewireRequest) {
            $referer = $request->headers->get('referer');
            $refererPath = $referer ? parse_url($referer, PHP_URL_PATH) : null;
            $path = is_string($refererPath)
                ? ltrim($refererPath, '/')
                : $request->path();
        } else {
            $path = $request->path();
        }
        $prefix = CustomQueryString::prefix();

        $segments = explode('/', $path);
        $filterIndex = array_search($prefix, $segments, true);

        if ($filterIndex !== false) {
            // Extract and parse filter segments
            $filterSegments = array_slice($segments, $filterIndex + 1);
            $params = $this->parseFilterSegments($filterSegments);

            // Add params to request
            $request->merge(['params' => $params]);

            // Only rewrite the request path for non-Livewire requests
            if (! $isLivewireRequest) {
                $baseUrl = implode('/', array_slice($segments, 0, $filterIndex));

                // Modify the PathInfo and RequestUri at the Symfony level
                $request->server->set('PATH_INFO', '/'.$baseUrl);
                $request->server->set('REQUEST_URI', '/'.$baseUrl);

                // Update the internal request parsing
                $request->initialize(
                    $request->query->all(),
                    $request->request->all(),
                    $request->attributes->all(),
                    $request->cookies->all(),
                    $request->files->all(),
                    $request->server->all()
                );
            }
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

        $prefix = CustomQueryString::prefix();

        if ($prefix === false) {
            return;
        }

        if (! isset($parsed['path'])) {
            return;
        }

        $segments = explode('/', ltrim($parsed['path'], '/'));
        $filterIndex = array_search($prefix, $segments, true);

        if ($filterIndex === false) {
            return;
        }

        $filterSegments = array_slice($segments, $filterIndex + 1);
        $params = $this->parseFilterSegments($filterSegments);

        if (! empty($params)) {
            $request->merge(['params' => $params]);
        }

        $basePath = '/'.implode('/', array_slice($segments, 0, $filterIndex));

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

    protected function shouldSkip(): bool
    {
        return ! CustomQueryString::enabled();
    }

    protected function shouldProcessRequest(Request $request): bool
    {
        return ! $request->ajax() &&
               ! $request->wantsJson() &&
               ! Str::contains($request->header('X-Livewire'), 'true');
    }

    protected function parseFilterSegments(array $segments): array
    {
        $aliases = array_merge(
            [
                'sort' => 'sort',
            ],
            config('statamic-livewire-filters.custom_query_string_aliases', [])
        );
        $params = [];

        for ($i = 0; $i < count($segments); $i += 2) {
            if (! isset($segments[$i + 1])) {
                break;
            }

            $key = rawurldecode($segments[$i]);
            $value = rawurldecode($segments[$i + 1]);

            if (! isset($aliases[$key])) {
                continue;
            }

            // Convert alias to actual filter key
            $actualKey = $aliases[$key];

            // Handle query_scopes
            if (str_contains($actualKey, 'query_scope')) {
                [$scopeString, $scopeClass, $scopeKey] = explode(':', $actualKey);
                // Add the query scope param to the array
                $params['query_scope'] = $scopeClass;
                // Set the the actual parameter key
                $actualKey = $scopeClass.':'.$scopeKey;
            }

            // Handle multiple values
            $value = str_contains($value, ',')
                ? implode('|', explode(',', $value))
                : $value;

            $params[$actualKey] = $value;
        }

        return $params;
    }
}
