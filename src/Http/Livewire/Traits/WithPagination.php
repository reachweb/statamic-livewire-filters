<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

trait WithPagination
{
    use \Livewire\WithPagination;

    public function withPagination($key, $paginator): array
    {
        return [
            $key => $paginator->items(),
            'links' => $paginator->render(),
            'pagination_total' => $paginator->total(),
        ];
    }
}
