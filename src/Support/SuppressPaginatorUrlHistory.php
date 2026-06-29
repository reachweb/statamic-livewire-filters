<?php

namespace Reach\StatamicLivewireFilters\Support;

use Livewire\Component;
use Livewire\Mechanisms\HandleComponents\ComponentContext;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;

/**
 * The addon is the sole URL and history writer in custom query string mode.
 * Livewire may still hydrate the paginator from the query string, but its
 * client-side URL effect must not mutate the current history entry first.
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

        foreach (array_keys($context->effects['url']) as $slot) {
            if (str_starts_with((string) $slot, 'paginators.')) {
                unset($context->effects['url'][$slot]);
            }
        }

        if ($context->effects['url'] === []) {
            unset($context->effects['url']);
        }
    }
}
