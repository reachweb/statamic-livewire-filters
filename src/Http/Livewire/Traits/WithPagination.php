<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

trait WithPagination
{
    use \Livewire\WithPagination;

    public function withPagination($key, $paginator): array
    {
        $data = [
            $key => $paginator->items(),
            'links' => $paginator->render(),
            'pagination_total' => $paginator->total(),
            'infinite_scroll' => $this->infiniteScroll,
        ];

        if ($this->infiniteScroll) {
            $data['has_more_pages'] = $this->hasMorePages = $paginator->hasMorePages();
        }

        return $data;
    }

    public function loadMore(): void
    {
        if (! $this->infiniteScroll || ! $this->hasMorePages) {
            return;
        }

        $this->paginate = (int) $this->paginate + (int) $this->initialPaginate;
    }
}
