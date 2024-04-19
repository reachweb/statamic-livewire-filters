<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

trait HandleFieldOptions
{
    protected function addTermsToOptions($field)
    {
        $terms = collect();
        $taxonomies = collect($field->config()['taxonomies']);

        $taxonomies->each(function ($taxonomy) use ($terms) {
            $terms->push($this->getTaxonomyTerms($taxonomy)->all());
        });

        $field->setConfig(array_merge(
            $field->config(),
            [
                'options' => $terms->collapse()->all(),
                'counts' => $terms->collapse()->keys()->flatMap(fn ($slug) => [$slug => null])->all(),
            ]
        ));

        return $field;
    }

    protected function hasOptionsInConfig($field)
    {
        return array_key_exists('options', $field->toArray());
    }

    protected function addCountsArrayToConfig($field)
    {
        $field->setConfig(array_merge(
            $field->config(),
            ['counts' => collect($field->get('options'))->keys()->flatMap(fn ($option) => [$option => null])->all()]
        ));

        return $field;
    }

    protected function hasCustomOptions()
    {
        return isset($this->options) && is_array($this->options);
    }

    protected function addCustomOptionsToConfig($field)
    {
        $options = collect($this->options);
        $field->setConfig(array_merge(
            $field->config(),
            [
                'options' => $this->options,
                'counts' => $options->keys()->flatMap(fn ($option) => [$option => null])->all(),
            ]
        ));

        return $field;
    }
}
