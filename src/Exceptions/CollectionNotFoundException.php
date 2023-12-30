<?php

namespace Reach\StatamicLivewireFilters\Exceptions;

use Exception;

class CollectionNotFoundException extends Exception
{
    public function __construct($collection)
    {
        $message = "Collection '{$collection}' not found.";
        parent::__construct($message);
    }
}
