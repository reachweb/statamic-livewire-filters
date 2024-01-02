<?php

namespace Reachweb\StatamicLivewireFilters\Exceptions;

use Exception;

class FieldNotFoundException extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @param string $field
     * @param string $blueprint
     */
    public function __construct($field, $blueprint)
    {
        $message = "Field '{$field}' not found in blueprint '{$blueprint}'.";
        parent::__construct($message);
    }
}
