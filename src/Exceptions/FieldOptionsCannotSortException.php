<?php

namespace Reach\StatamicLivewireFilters\Exceptions;

use Exception;

class FieldOptionsCannotSortException extends Exception
{
    public function __construct($sortyBy, $handle)
    {
        $message = "Cannot sort field [{$handle}] by [{$sortyBy}]. Maybe you have mixed up the field type?";
        parent::__construct($message);
    }
}
