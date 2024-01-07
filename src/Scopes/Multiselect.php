<?php

namespace Reach\StatamicLivewireFilters\Scopes;

use Illuminate\Support\Str;
use Statamic\Query\Scopes\Scope;

class Multiselect extends Scope
{
    /**
     * Apply the scope.
     *
     * @param  \Statamic\Query\Builder  $query
     * @param  array  $values
     * @return void
     */
    public function apply($query, $values)
    {
        $prefix = $values->get('query_scope').':';

        $filters = collect($values)->filter(function ($value, $key) use ($prefix) {
            return Str::startsWith($key, $prefix);
        })->mapWithKeys(function ($value, $key) use ($prefix) {
            $newKey = Str::after($key, $prefix);

            return [$newKey => explode('|', $value)];
        })->all();

        $field = array_key_first($filters);

        return $query->whereJsonContains($field, $filters[$field]);
    }
}
