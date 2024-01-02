<?php

namespace Reach\StatamicLivewireFilters\Exceptions;

use Exception;

class FieldNotFoundException extends Exception
{
    public function __construct($field, $blueprint)
    {
        $message = "Field [{$field}] not found in blueprint '[$blueprint]'.";
        parent::__construct($message);
    }
}
