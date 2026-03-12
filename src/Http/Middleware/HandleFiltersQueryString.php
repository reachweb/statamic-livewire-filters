<?php

namespace Reach\StatamicLivewireFilters\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HandleFiltersQueryString
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($this->shouldSkip()) {
            return $next($request);
        }

        $isLivewireRequest = $request->hasHeader('X-Livewire');

        if (! $isLivewireRequest && ! $this->shouldProcessRequest($request)) {
            return $next($request);
        }

        if ($isLivewireRequest) {
            $previousUrl = url()->previous();
            $path = ltrim(parse_url($previousUrl, PHP_URL_PATH) ?? '/', '/');
        } else {
            $path = $request->path();
        }
        $prefix = config('statamic-livewire-filters.custom_query_string', 'filters');

        $segments = explode('/', $path);
        $filterIndex = array_search($prefix, $segments);

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

    protected function shouldSkip(): bool
    {
        return config('statamic-livewire-filters.custom_query_string') === false || config('statamic-livewire-filters.enable_query_string') === true;
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
