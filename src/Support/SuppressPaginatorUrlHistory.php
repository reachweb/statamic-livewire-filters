<?php

namespace Reach\StatamicLivewireFilters\Support;

use Livewire\Component;
use Livewire\Mechanisms\HandleComponents\ComponentContext;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;

/**
 * The addon owns browser history in custom query string mode. Livewire may
 * still replace the current URL, but it must not create a second entry.
 */
class SuppressPaginatorUrlHistory
{
    public static function register(): void
    {
        \Livewire\on('dehydrate', static::handle(...));
    }

    public static function handle(Component $component, ComponentContext $context): void
    {
        if (! $component instanceof LivewireCollection || ! CustomQueryString::enabled()) {
            return;
        }

        if (! isset($context->effects['url']) || ! is_array($context->effects['url'])) {
            return;
        }

        foreach ($context->effects['url'] as $slot => $detail) {
            if (is_array($detail) && isset($detail['use']) && str_starts_with((string) $slot, 'paginators.')) {
                $context->effects['url'][$slot]['use'] = 'replace';
            }
        }
    }
}
