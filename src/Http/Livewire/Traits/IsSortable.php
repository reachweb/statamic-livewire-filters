<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Livewire\Attributes\Locked;
use Reach\StatamicLivewireFilters\Exceptions\FieldOptionsCannotFindTaxonomyField;
use Reach\StatamicLivewireFilters\Exceptions\FieldOptionsCannotSortException;
use Statamic\Facades\Taxonomy;

trait IsSortable
{
    #[Locked]
    public $sort;

    public function mountIsSortable(): void
    {
        if ($this->sort === null) {
            return;
        }

        [$sortBy, $sortDirection] = explode(':', $this->sort);
        $fieldType = $this->statamic_field['type'];
        $fieldHandle = $this->statamic_field['handle'];

        switch ($sortBy) {
            case 'key':
            case 'label':
                if ($fieldType !== 'checkboxes' && $fieldType !== 'radio' && $fieldType !== 'select') {
                    throw new FieldOptionsCannotSortException($sortBy, $fieldHandle);
                }
                if ($sortBy === 'key') {
                    $this->sortOptionsByKey($sortDirection);
                } else {
                    $this->sortOptionsByValue($sortDirection);
                }
                break;
            case 'slug':
            case 'title':
                if ($fieldType !== 'terms') {
                    throw new FieldOptionsCannotSortException($sortBy, $fieldHandle);
                }
                if ($sortBy === 'slug') {
                    $this->sortOptionsByKey($sortDirection);
                } else {
                    $this->sortOptionsByValue($sortDirection);
                }
                break;
            default:
                $this->sortCustomField($sortBy, $sortDirection);
        }
    }

    protected function sortOptionsByKey($sortDirection): void
    {
        $options = collect($this->statamic_field['options']);

        if ($sortDirection === 'asc') {
            $options = $options->sortKeys();
        } else {
            $options = $options->sortKeysDesc();
        }

        $this->statamic_field['options'] = $options->all();
    }

    protected function sortOptionsByValue($sortDirection): void
    {
        $options = collect($this->statamic_field['options']);

        if ($sortDirection === 'asc') {
            $options = $options->sort();
        } else {
            $options = $options->sortDesc();
        }

        $this->statamic_field['options'] = $options->all();
    }

    // If the sortyBy is not one of the default ones, we need to search the terms of the field
    protected function sortCustomField($sortBy, $sortDirection): void
    {
        $fieldType = $this->statamic_field['type'];
        $fieldHandle = $this->statamic_field['handle'];

        // If it's not a terms field, it can't have custom fields.
        if ($fieldType !== 'terms') {
            throw new FieldOptionsCannotSortException($sortBy, $fieldHandle);
        }

        $terms = $this->getTaxonomyTermsSortedBy($fieldHandle, $sortBy, $sortDirection);
    }

    protected function getTaxonomyTermsSortedBy($handle, $sortBy, $sortDirection): void
    {
        $taxonomy = Taxonomy::findByHandle($handle);

        // Check if the field exists
        if (! $taxonomy->termBlueprint()->fields()->all()->has($sortBy)) {
            throw new FieldOptionsCannotFindTaxonomyField($sortBy, $handle);
        }

        $this->statamic_field['options'] = $taxonomy->queryTerms()->orderBy($sortBy, $sortDirection)->get()->flatMap(function ($term) {
            return [
                $term->slug() => $term->title(),
            ];
        })->all();
    }
}
