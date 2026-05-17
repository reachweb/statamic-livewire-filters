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
}
