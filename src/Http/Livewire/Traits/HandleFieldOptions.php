<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

trait HandleFieldOptions
{
    use HandleStatamicQueries;

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

    protected function addEntriesToOptions($field)
    {
        $entries = collect();
        $collections = collect($field->config()['collections']);

        $collections->each(function ($collection) use ($entries) {
            $entries->push($this->getCollectionEntries($collection)->all());
        });

        $field->setConfig(array_merge(
            $field->config(),
            [
                'options' => $entries->collapse()->all(),
                'counts' => $entries->collapse()->keys()->flatMap(fn ($slug) => [$slug => null])->all(),
            ]
        ));

        return $field;
    }

    protected function hasOptionsInConfig($field)
    {
        return array_key_exists('options', $field->toArray());
    }

    protected function transformOptionsArray($field)
    {
        $options = collect($field->get('options'));
        if (! is_array($options->first()) || ! array_key_exists('key', $options->first())) {
            return $field;
        }
        $field->setConfig(array_merge(
            $field->config(),
            ['options' => $options->flatMap(fn ($option) => [$option['key'] => $option['value'] ?? $option['key']])->all()]
        ));

        return $field;
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
