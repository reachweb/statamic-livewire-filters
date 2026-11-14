<?php

namespace Reach\StatamicLivewireFilters\Exceptions;

use Exception;

class InvalidRangeListBucketException extends Exception
{
    public function __construct($bucket, $reason = 'Use min-max, min- or -max.')
    {
        $message = "Invalid range list bucket [{$bucket}]. {$reason}";
        parent::__construct($message);
    }
}
