<?php

namespace Reach\StatamicLivewireFilters\Tags\Concerns;

trait SupportTaxonomyTermRoute
{
    public function addTaxonomyTermRoute()
    {
        if (! config('statamic-livewire-filters.enable_term_routes')) {
            return;
        }
        if ($this->isTermPage() && $this->isTerm()) {
            $this->params['taxonomy:'.$this->getTaxonomy().':any'] = $this->getTerm();
        }
    }

    protected function isTermPage()
    {
        return $this->context->get('page') instanceof \Statamic\Taxonomies\LocalizedTerm;
    }

    protected function isTerm()
    {
        return $this->context->get('is_term')->value();
    }

    protected function getTaxonomy()
    {
        return $this->context->get('taxonomy')->value()->handle();
    }

    protected function getTerm()
    {
        return $this->context->get('last_segment') ?? $this->context->get('is_term')->augmentable()->slug();
    }
}
