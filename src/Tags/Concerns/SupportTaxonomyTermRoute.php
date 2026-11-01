<?php

namespace Reach\StatamicLivewireFilters\Tags\Concerns;

use Statamic\Taxonomies\LocalizedTerm;

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
        return $this->context->get('page') instanceof LocalizedTerm;
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
        // Taxonomy conditions match default-locale slugs, while the URL holds the localized slug.
        return $this->context->get('page')->term()->slug();
    }
}
