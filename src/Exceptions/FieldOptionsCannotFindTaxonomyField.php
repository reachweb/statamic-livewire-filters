<?php

namespace Reach\StatamicLivewireFilters\Exceptions;

use Exception;

class FieldOptionsCannotFindTaxonomyField extends Exception
{
    public function __construct($sortyBy, $handle)
    {
        $message = "Cannot find field [{$sortyBy}] in the taxonomy [{$handle}] in order to sort the filter options.";
        parent::__construct($message);
    }
}
