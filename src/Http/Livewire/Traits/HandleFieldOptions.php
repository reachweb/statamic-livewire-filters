<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Statamic\Facades\Blink;
use Statamic\Facades\Dictionary;
use Statamic\Facades\Site;

trait HandleFieldOptions
{
    use HandleStatamicQueries;

    protected function addTermsToOptions($field)
    {
        $options = Blink::once($this->fieldOptionsCacheKey('terms', $field->config()['taxonomies'] ?? []), function () use ($field) {
            return collect($field->config()['taxonomies'] ?? [])
                ->flatMap(fn ($taxonomy) => $this->getTaxonomyTerms($taxonomy))
                ->all();
        });

        return $this->setFieldOptionsAndCounts($field, $options);
    }

    protected function addEntriesToOptions($field)
    {
        $options = Blink::once($this->fieldOptionsCacheKey('entries', $field->config()['collections'] ?? []), function () use ($field) {
            return collect($field->config()['collections'] ?? [])
                ->flatMap(fn ($collection) => $this->getCollectionEntries($collection))
                ->all();
        });

        return $this->setFieldOptionsAndCounts($field, $options);
    }

    protected function addDictionaryToOptions($field)
    {
        $dictionaryConfig = $field->config()['dictionary'] ?? null;
        $dictionaryType = is_array($dictionaryConfig) ? ($dictionaryConfig['type'] ?? null) : $dictionaryConfig;

        if (! $dictionaryType || ! $dictionary = Dictionary::find($dictionaryType)) {
            return $field;
        }

        if (is_array($dictionaryConfig)) {
            $dictionary->setConfig(collect($dictionaryConfig)->except('type')->all());
        }

        $options = Blink::once($this->fieldOptionsCacheKey('dictionary', $dictionaryConfig), function () use ($dictionary) {
            return collect($dictionary->options())->mapWithKeys(fn ($label, $key) => [$key => $label])->all();
        });

        return $this->setFieldOptionsAndCounts($field, $options);
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

    protected function setFieldOptionsAndCounts($field, array $options)
    {
        $field->setConfig(array_merge(
            $field->config(),
            [
                'options' => $options,
                'counts' => array_fill_keys(array_keys($options), null),
            ]
        ));

        return $field;
    }

    protected function fieldOptionsCacheKey(string $type, $config): string
    {
        return 'statamic-livewire-filters.field-options.'.$type.'.'.md5(serialize([
            'collection' => $this->collection ?? null,
            'site' => Site::current()->handle(),
            'config' => $config,
            'use_origin_id_for_entries_field' => config('statamic-livewire-filters.use_origin_id_for_entries_field'),
        ]));
    }
}
