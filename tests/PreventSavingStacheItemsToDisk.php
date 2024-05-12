<?php

namespace Reach\StatamicLivewireFilters\Tests;

use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk as BasePreventsSavingStacheItemsToDisk;

trait PreventSavingStacheItemsToDisk
{
    use BasePreventsSavingStacheItemsToDisk;
}
