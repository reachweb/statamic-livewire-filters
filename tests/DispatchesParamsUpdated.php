<?php

namespace Reach\StatamicLivewireFilters\Tests;

use Reach\StatamicLivewireFilters\Support\RangeBoundsQuery;

trait DispatchesParamsUpdated
{
    /**
     * The `params-updated` arguments the collection sends: the params and their encrypted query.
     */
    protected function paramsUpdatedArguments(array $params, string $collection = 'pages'): array
    {
        return [$params, RangeBoundsQuery::encode($collection, $params)];
    }
}
