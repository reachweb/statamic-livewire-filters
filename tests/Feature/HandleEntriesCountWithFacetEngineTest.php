<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;

/** Every HandleEntriesCountTest scenario, with the counts routed through the facet engine. */
class HandleEntriesCountWithFacetEngineTest extends HandleEntriesCountTest
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('statamic-livewire-filters.enable_facet_engine', true);
    }
}
