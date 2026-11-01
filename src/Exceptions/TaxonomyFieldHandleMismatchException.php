<?php

namespace Reach\StatamicLivewireFilters\Exceptions;

use Exception;

class TaxonomyFieldHandleMismatchException extends Exception
{
    public function __construct($field, array $taxonomies)
    {
        $expected = $taxonomies ? ' Rename the field to ['.implode('] or [', $taxonomies).'].' : '';

        $message = "Field [{$field}] uses the taxonomy condition but its handle does not match a taxonomy handle.{$expected} Statamic only filters terms fields whose handle matches the taxonomy handle.";
        parent::__construct($message);
    }
}
