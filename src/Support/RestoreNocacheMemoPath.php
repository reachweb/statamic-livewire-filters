<?php

namespace Reach\StatamicLivewireFilters\Support;

use Livewire\Component;
use Livewire\Mechanisms\HandleComponents\ComponentContext;

use function Livewire\after;

class RestoreNocacheMemoPath
{
    /**
     * Behind the static cache, a {{ nocache }} component is dehydrated during a POST
     * to the nocache endpoint, so Livewire stores that endpoint as the memo "path" —
     * making a later request resolve the wrong site on a multisite. Registered after
     * PersistentMiddleware's listener so it can overwrite the memo with the real path.
     */
    public static function register(): void
    {
        after('dehydrate', function (Component $component, ComponentContext $context): void {
            $request = request();

            if (! Nocache::matches($request)) {
                return;
            }

            if (($path = Nocache::originalPath($request)) === null) {
                return;
            }

            $context->addMemo('path', $path);
            $context->addMemo('method', 'GET');
        });
    }
}
