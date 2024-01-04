<?php

namespace Reach\StatamicLivewireFilters\Exceptions;

use Exception;

class CommandNotFoundException extends Exception
{
    public function __construct($command)
    {
        $message = "Command [{$command}] does not exist.";
        parent::__construct($message);
    }
}
