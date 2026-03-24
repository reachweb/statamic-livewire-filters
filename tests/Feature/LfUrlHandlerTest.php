<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Tests\TestCase;

class LfUrlHandlerTest extends TestCase
{
    #[Test]
    public function it_only_pushes_browser_history_when_the_url_meaningfully_changes()
    {
        $html = file_get_contents(__DIR__.'/../../resources/views/livewire/utility/url-handler.blade.php');

        $this->assertStringContainsString('const normalizeUrl = (value) => {', $html);
        $this->assertStringContainsString('if (normalizeUrl(currentUrl) === normalizeUrl(nextUrl)) {', $html);
        $this->assertStringContainsString("history.pushState(null, '', nextUrl);", $html);
    }
}
