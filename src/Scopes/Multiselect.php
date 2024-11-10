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
        collect($values)->filter(function ($value, $key) {
            return Str::startsWith($key, 'multiselect:');
        })->mapWithKeys(function ($value, $key) {
            $newKey = Str::after($key, 'multiselect:');

            return [$newKey => explode('|', $value)];
        })->each(function ($values, $field) use ($query) {
            $query->where(function ($query) use ($field, $values) {
                foreach ($values as $value) {
                    $query->orWhereJsonContains($field, $value);
                }
            });
        });

        return $query;
    }
}
