<?php

namespace Reach\StatamicLivewireFilters\Support;

use Livewire\Component;
use Livewire\Mechanisms\HandleComponents\ComponentContext;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;

/**
 * In custom query string mode the addon owns the URL, so Livewire's paginator
 * must not push its own history entry too. This flips its `url` effect to
 * `replace`, leaving the addon as the sole writer of a history entry.
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
