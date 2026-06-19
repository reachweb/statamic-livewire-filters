<?php

namespace Reach\StatamicLivewireFilters\Tests\Tags;

use Reach\StatamicLivewireFilters\Tags\LivewireFilters;
use Reach\StatamicLivewireFilters\Tests\TestCase;
use Statamic\Facades\Antlers;

class LoadMoreTest extends TestCase
{
    private function tag(array $context = [], array $params = []): LivewireFilters
    {
        $tag = (new LivewireFilters)
            ->setParser(Antlers::parser())
            ->setContext($context);

        $tag->setParameters($params);

        return $tag;
    }

    public function test_it_renders_nothing_when_there_are_no_more_pages()
    {
        $this->assertSame('', $this->tag(['has_more_pages' => false])->loadMore());
    }

    public function test_it_renders_nothing_when_has_more_pages_is_absent()
    {
        $this->assertSame('', $this->tag()->loadMore());
    }

    public function test_it_renders_a_manual_button_by_default()
    {
        $html = $this->tag(['has_more_pages' => true])->loadMore();

        $this->assertStringContainsString('wire:click="loadMore"', $html);
        $this->assertStringNotContainsString('x-intersect', $html);
    }

    public function test_it_adds_an_intersection_observer_in_auto_mode()
    {
        $html = $this->tag(['has_more_pages' => true], ['auto' => 'true'])->loadMore();

        $this->assertStringContainsString('wire:click="loadMore"', $html);
        $this->assertStringContainsString('x-intersect', $html);
        $this->assertStringContainsString('$wire.loadMore()', $html);
        // Guard is reset on both success and failure, and visibility is re-checked
        // afterwards so a short appended page keeps loading instead of stalling.
        $this->assertStringContainsString('.finally(', $html);
        $this->assertStringContainsString('isConnected', $html);
    }

    public function test_it_allows_overriding_the_button_label_and_class()
    {
        $html = $this->tag(['has_more_pages' => true], [
            'text' => 'Show more cars',
            'class' => 'btn btn-primary',
        ])->loadMore();

        $this->assertStringContainsString('Show more cars', $html);
        $this->assertStringContainsString('class="btn btn-primary"', $html);
    }
}
