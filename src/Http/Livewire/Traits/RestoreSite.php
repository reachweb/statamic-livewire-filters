<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Livewire\Livewire;
use Statamic\Facades\Site;

trait RestoreSite
{
    public function initializeRestoreSite(): void
    {
        // return early if multisite is not enabled
        if (! Site::multiEnabled()) {
            return;
        }

        Site::resolveCurrentUrlUsing(fn () => Livewire::originalUrl());
    }
}
