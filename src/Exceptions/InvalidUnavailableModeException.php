<?php

namespace Reach\StatamicLivewireFilters\Exceptions;

use Exception;

class InvalidUnavailableModeException extends Exception
{
    public function __construct($mode)
    {
        $message = "Invalid unavailable [{$mode}]. Use hide or disable.";
        parent::__construct($message);
    }
}
