<?php

namespace Reach\StatamicLivewireFilters\Exceptions;

use Exception;

class InvalidZeroCountModeException extends Exception
{
    public function __construct($mode)
    {
        $message = "Invalid zero_count [{$mode}]. Use show, hide or disable.";
        parent::__construct($message);
    }
}
