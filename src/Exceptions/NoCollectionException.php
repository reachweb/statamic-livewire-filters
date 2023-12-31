<?php

namespace Reach\StatamicLivewireFilters\Exceptions;

use Exception;

class NoCollectionException extends Exception
{
    public function __construct()
    {
        $message = 'You need to specifiy a collection for the livewire-collection tag.';
        parent::__construct($message);
    }
}
