<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Reach\StatamicLivewireFilters\Support\ParamKey;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Term;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Stache\Query\TermQueryBuilder;

class LfTags extends Component
{
    use Traits\HandleFieldOptions, Traits\HandleStatamicQueries;

    public $view = 'tags';

    #[Locked]
    public string $blueprint;

    #[Locked]
    public string $collection;

    #[Locked]
    public $fields;

    #[Locked]
    public $params;

    #[Locked]
    public $tags;

    /** Field handle => display format, registered by date range filters. */
    #[Locked]
    public array $dateRangeFormats = [];

    public function mount($blueprint, $fields)
    {
        [$collection, $blueprint] = explode('.', $blueprint);
        $this->collection = $collection;
        $this->blueprint = $blueprint;
        $this->fields = explode('|', $fields);
        $this->tags = collect();
    }

    #[Computed]
    public function statamicFields(): Collection
    {
        if ($this->optionsComeFromStacheIndexes()) {
            return $this->resolveStatamicFields();
        }

        // Other drivers hydrate every term and entry to build the options, so keep
        // them for the component's lifetime like the former persisted computed did.
        // Stored as plain arrays so the cache store can unserialize them safely.
        return collect(Cache::remember(
            'statamic-livewire-filters.lf-tags.'.$this->getId().'.fields',
            3600,
            fn () => $this->resolveStatamicFields()->all()
        ));
    }

    protected function optionsComeFromStacheIndexes(): bool
    {
        return Entry::query() instanceof EntryQueryBuilder && Term::query() instanceof TermQueryBuilder;
    }

    protected function resolveStatamicFields(): Collection
    {
        $statamicFields = collect();
        $statamicBlueprint = $this->getStatamicBlueprint();
        foreach ($this->fields as $field_handle) {
            $field = $this->getStatamicField($statamicBlueprint, $field_handle);
            if ($field->type() == 'terms') {
                $field = $this->addTermsToOptions($field);
            }
            if ($field->type() == 'entries') {
                $field = $this->addEntriesToOptions($field);
            }
            if ($field->type() == 'dictionary') {
                $field = $this->addDictionaryToOptions($field);
            }
            $statamicFields->put($field_handle, $field->toArray());
        }

        return $statamicFields;
    }

    #[On('tags-updated')]
    public function updateTags($params)
    {
        $this->params = collect($params)
            ->reject(fn ($value, $key) => $key === 'sort')
            ->reject(fn ($value, $key) => ! Str::contains($key, ':') && ! Str::startsWith($key, 'query_scope'));

        $this->tags = collect();

        // Order is critical here
        $this->parseQueryScopes();
        $this->parseTaxonomyTerms();
        $this->parseConditions();
    }

    public function parseConditions()
    {
        $this->params->each(function ($value, $key) {
            $key = ParamKey::parse($key);
            $values = collect(explode('|', $value));
            $values->each(function ($value) use ($key) {
                $this->addFieldOptionToTags($key->field, $value, $key->condition);
            });
        });
    }

    public function parseTaxonomyTerms()
    {
        $taxonomies = $this->params->filter(fn ($value, $key) => Str::startsWith($key, 'taxonomy:'));

        if ($taxonomies->isEmpty()) {
            return;
        }

        $this->handleTaxonomyTermConditions($taxonomies);
    }

    public function parseQueryScopes()
    {
        $scopes = $this->params->filter(fn ($value, $key) => Str::startsWith($key, 'query_scope'));

        if ($scopes->isEmpty()) {
            return;
        }

        $scopes = collect(explode('|', $scopes->first()));

        $scopes->each(function ($scopeName) {
            $scopeValues = $this->params->filter(function ($value, $key) use ($scopeName) {
                $parsed = ParamKey::parse($key, [$scopeName]);

                // A plain `field:query_scope` key also parses with condition query_scope
                return $parsed->condition === 'query_scope' && $parsed->modifier === $scopeName;
            });
            $this->handleQueryScopeCondition($scopeValues, $scopeName);
        });

        $this->params = $this->params->reject(fn ($value, $key) => Str::startsWith($key, 'query_scope'));

    }

    public function handleTaxonomyTermConditions($taxonomies)
    {
        $taxonomies->each(function ($values, $key) {
            $key = ParamKey::parse($key);
            $terms = collect(explode('|', $values));
            $terms->each(function ($value) use ($key) {
                // Taxonomy tags carry the modifier as their condition
                $this->addFieldOptionToTags($key->field, $value, $key->modifier);
            });
        });

        $this->params = $this->params->reject(fn ($value, $key) => Str::startsWith($key, 'taxonomy:'));
    }

    public function handleQueryScopeCondition($values, $scope = null)
    {
        $values->each(function ($values, $key) use ($scope) {
            $field = ParamKey::parse($key, [$scope ?? Str::before($key, ':')])->field;
            if ($this->isNotTaggable($field)) {
                return;
            }
            $selectedValues = is_array($values) ? collect($values)->flatten() : collect(explode('|', $values));
            $selectedValues->each(function ($value) use ($field) {
                $this->addFieldOptionToTags($field, $value, 'query_scope');
            });
        });

        $this->params = $this->params->except($values->keys());
    }

    public function addFieldOptionToTags($field, $value, $condition = null)
    {
        if ($this->isNotTaggable($field)) {
            return;
        }
        $fieldLabel = $this->statamicFields->get($field)['display'] ?? $field;
        $optionLabel = $this->statamicFields->get($field)['options'][$value] ?? $value;

        $tag = [
            'field' => $field,
            'value' => $value,
            'fieldLabel' => $fieldLabel,
            'optionLabel' => $optionLabel,
            'condition' => $condition,
        ];

        $this->tags->push($this->withDateRangeLabel($tag));
    }

    /**
     * Registration can arrive after the initial tags, so relabel any already built.
     */
    #[On('date-range-filter-registered')]
    public function registerDateRangeFilter($field, $format): void
    {
        // The format comes from the client and isoFormat() is quadratic in its length:
        // real formats are a handful of tokens, so refuse anything longer.
        if (! is_string($field) || ! is_string($format) || $format === '' || mb_strlen($format) > 64 || $this->isNotTaggable($field)) {
            return;
        }

        $this->dateRangeFormats[$field] = $format;
        $this->tags = $this->tags->map(fn ($tag) => $this->withDateRangeLabel($tag));
    }

    /**
     * Date range ends are stored as exclusive UTC bounds; label them as "from"/"to"
     * local dates. New keys only, so published views that print `optionLabel` after
     * the condition still show the raw (accurate) bound.
     */
    protected function withDateRangeLabel(array $tag): array
    {
        $format = $this->dateRangeFormats[$tag['field']] ?? null;

        if ($format === null || ! in_array($tag['condition'], ['is_after', 'is_before'], true)) {
            return $tag;
        }

        $isStart = $tag['condition'] === 'is_after';

        if (! $date = LfDateRangeFilter::paramToDate($tag['value'], $isStart)) {
            return $tag;
        }

        return [
            ...$tag,
            'conditionLabel' => trans('statamic-livewire-filters::ui.'.($isStart ? 'date_from' : 'date_to')),
            'dateLabel' => $date->locale(Site::current()->locale())->isoFormat($format),
        ];
    }

    public function removeOption($field, $value, $condition = null)
    {
        // The condition tells apart tags with the same value, such as the two
        // bounds of a dual-range filter set to the same number.
        $tag = $this->tags->firstOrFail(fn ($item) => $item['field'] === $field
            && $item['value'] === $value
            && ($condition === null || (string) ($item['condition'] ?? '') === $condition));
        $this->dispatch('clear-option', $tag);
    }

    public function clearAll()
    {
        $this->dispatch('clear-all-filters');
    }

    public function isNotTaggable($field)
    {
        return ! in_array($field, $this->fields);
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.ui.'.$this->view);
    }
}
