<?php

namespace Reach\StatamicLivewireFilters\Exceptions;

use Exception;

class InvalidRangeListModifierException extends Exception
{
    public function __construct($modifier)
    {
        $message = "Invalid range list modifier [{$modifier}]. Use gt or gte for the min and lt or lte for the max, such as gte|lt.";
        parent::__construct($message);
    }
}
