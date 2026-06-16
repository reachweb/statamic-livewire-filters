<?php

namespace Reach\StatamicLivewireFilters\Support;

use Illuminate\Http\Request;

class Nocache
{
    public static function matches(Request $request): bool
    {
        $actionPrefix = trim((string) config('statamic.routes.action', '!'), '/');

        return $actionPrefix !== '' && $request->is($actionPrefix.'/nocache');
    }

    /**
     * Resolve the real page path from a nocache request's "url" input,
     * matching request()->path() format ('/' for the home page).
     */
    public static function originalPath(Request $request): ?string
    {
        $url = $request->input('url');

        if (! is_string($url) || $url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path)) {
            return null;
        }

        $path = trim(CustomQueryString::stripPrefix($path), '/');

        return $path === '' ? '/' : $path;
    }
}
