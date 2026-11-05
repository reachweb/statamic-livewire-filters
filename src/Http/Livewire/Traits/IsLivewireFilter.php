<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Livewire\Attributes\Locked;
use Reach\StatamicLivewireFilters\Exceptions\TaxonomyFieldHandleMismatchException;
use Reach\StatamicLivewireFilters\Http\Livewire\LivewireCollection;
use Reach\StatamicLivewireFilters\Support\CustomQueryString;
use Reach\StatamicLivewireFilters\Support\ParamKey;
use Statamic\Facades\Taxonomy;
use Statamic\Support\Arr;

trait IsLivewireFilter
{
    use HandleFieldOptions, HandleStatamicQueries;

    #[Locked]
    public $field;

    #[Locked]
    public $statamic_field;

    #[Locked]
    public $blueprint;

    #[Locked]
    public $collection;

    #[Locked]
    public $condition;

    #[Locked]
    public $modifier = 'any';

    /** Set once a render shows a bare `wire:ignore` on the root: the client morph discards every re-render, so none is sent. */
    #[Locked]
    public bool $clientRendered = false;

    /** The payload of the last filter-updated, until it is cleared: a clear-all compares it with what its sweep removed. */
    #[Locked]
    public mixed $lastSentPayload = null;

    /** Request-scoped (never persisted): a handler in this request changed state the view may depend on. */
    protected bool $filterStateChangedThisRequest = false;

    public function hydrateIsLivewireFilter(): void
    {
        if ($this->clientRendered) {
            $this->skipRender();
        }
    }

    // A client property update can change what the view prints (including the error bag when
    // validation then fails), so mark it before any `updated*` hook or `validate()` runs.
    public function updatingIsLivewireFilter($path, $value): void
    {
        $this->markFilterStateChanged();
    }

    public function renderedIsLivewireFilter($view, $html): void
    {
        if (! $this->clientRendered && $this->htmlRootHasWireIgnore((string) $html)) {
            $this->clientRendered = true;
        }
    }

    /**
     * Only the root start tag is inspected; quoted attribute values (Alpine x-data) are opaque,
     * and `wire:ignore.self` / `wire:ignore.children` still morph, so only the bare name counts.
     */
    protected function htmlRootHasWireIgnore(string $html): bool
    {
        $attribute = '[^\s"\'=<>\/]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?';

        if (! preg_match('/^\s*<[a-zA-Z][\w:-]*((?:\s+'.$attribute.')*)\s*\/?>/', $html, $tag)) {
            return false;
        }

        preg_match_all('/\s('.$attribute.')/', $tag[1], $attributes);

        return in_array('wire:ignore', array_map(fn ($a) => preg_split('/\s*=/', $a, 2)[0], $attributes[1]), true);
    }

    /**
     * One pooled Livewire request can carry several event calls for this component (e.g. a
     * foreign `clear-option` plus a real `params-updated`). skipRender() is component-wide for
     * the request, so a no-op must never suppress the render of a real change handled in the
     * same request, regardless of call order: the store reset clears a skip set by an earlier
     * no-op, the flag guards later ones. Client-rendered roots never need the html.
     */
    protected function markFilterStateChanged(): void
    {
        $this->filterStateChangedThisRequest = true;

        if (! $this->clientRendered) {
            \Livewire\store($this)->set('skipRender', false);
        }
    }

    protected function skipRenderIfFilterStateUnchanged(): void
    {
        if (! $this->filterStateChangedThisRequest) {
            $this->skipRender();
        }
    }

    public function mountIsLivewireFilter($blueprint)
    {
        [$collection, $blueprint] = explode('.', $blueprint);
        $this->collection = $collection;
        $this->blueprint = $blueprint;

        $this->initiateField();

        // Restore the URL's selection before the first render instead of waiting for
        // the collection's `preset-params`, which arrives in a follow-up request.
        if (method_exists($this, 'setPresetValues') && ($urlParams = CustomQueryString::urlParams()) !== []) {
            $this->setPresetValues($urlParams);
        }
    }

    public function initiateField()
    {
        $blueprint = $this->getStatamicBlueprint();
        $field = $this->getStatamicField($blueprint);

        if ($this->condition === 'taxonomy') {
            $this->ensureFieldHandleIsTaxonomy($field);
        }

        $field = $this->processFieldByType($field);

        $this->statamic_field = $field->toArray();
    }

    // The collection tag reads taxonomy:{handle}:{modifier} as a taxonomy handle, and
    // Statamic only associates entries with terms stored under the taxonomy's handle.
    protected function ensureFieldHandleIsTaxonomy($field): void
    {
        $taxonomies = Arr::wrap($field->get('taxonomies'));

        if (($field->type() === 'terms' && ! in_array($this->field, $taxonomies, true)) || ! Taxonomy::findByHandle($this->field)) {
            throw new TaxonomyFieldHandleMismatchException($this->field, $taxonomies);
        }
    }

    protected function processFieldByType($field)
    {
        if ($this->hasCustomOptions()) {
            return $this->addCustomOptionsToConfig($field);
        }

        $fieldType = $field->type();

        // Try to call a type-specific processor method if it exists
        $processorMethod = 'process'.ucfirst($fieldType).'Field';
        if (method_exists($this, $processorMethod)) {
            return $this->$processorMethod($field);
        }

        // Process by configuration
        if ($this->hasOptionsInConfig($field)) {
            $field = $this->transformOptionsArray($field);

            return $this->addCountsArrayToConfig($field);
        }

        // Return unmodified field if no processors applied
        return $field;
    }

    protected function processTermsField($field)
    {
        return $this->addTermsToOptions($field);
    }

    protected function processEntriesField($field)
    {
        return $this->addEntriesToOptions($field);
    }

    protected function processDictionaryField($field)
    {
        return $this->addDictionaryToOptions($field);
    }

    /**
     * A bare clear-all-filters (LfTags, user code) or the per-filter clear button keeps
     * the relay. One from LivewireCollection::clearAll() lists the keys its sweep had to
     * keep and the params it removed, so a filter only relays when it manages a kept key,
     * or when the sweep missed its last selection. clear() reads these with func_get_args()
     * and declares no parameter, so subclasses overriding clear() stay compatible.
     *
     * The selection is being cleared, so this also forgets the last sent payload.
     *
     * @param  mixed  $keptParamKeys  The list from clearAll(); anything else counts as a bare event.
     * @param  mixed  $sweptParams  The params clearAll() removed; without them only the kept keys decide.
     */
    protected function clearAllNeedsRelay($keptParamKeys = null, $sweptParams = null): bool
    {
        $lastSentPayload = $this->lastSentPayload;
        $this->lastSentPayload = null;

        if (! is_array($keptParamKeys)) {
            return true;
        }

        $paramKey = $this->getParamKey();

        $managesKeptKey = collect(is_array($paramKey) ? array_values($paramKey) : [$paramKey])
            ->contains(fn ($key) => in_array($key, $keptParamKeys, true));

        return $managesKeptKey || (is_array($sweptParams) && $this->sweepMissedPayload($lastSentPayload, $sweptParams));
    }

    /**
     * A selection made while the clear-all was on its way reaches the collection after the
     * sweep, then this filter resets, and only a relay can remove the param that landed. A
     * false positive just costs the relay, so the params are compared as the collection stores them.
     *
     * @param  array<string, mixed>  $sweptParams
     */
    protected function sweepMissedPayload(mixed $payload, array $sweptParams): bool
    {
        if ($payload === null || $payload === '' || $payload === []) {
            return false;
        }

        $paramKey = $this->getParamKey();

        $sentParams = is_array($paramKey)
            ? [$paramKey['min'] => $payload['min'] ?? null, $paramKey['max'] => $payload['max'] ?? null]
            : [$paramKey => is_array($payload) ? implode('|', Arr::flatten($payload)) : $payload];

        return collect($sentParams)
            ->contains(fn ($value, $key) => ! array_key_exists($key, $sweptParams) || $sweptParams[$key] != $value);
    }

    public function clearFilters($boundsSelection = null)
    {
        $this->lastSentPayload = null;

        $this->dispatch('clear-filter',
            field: $this->field,
            condition: $this->condition,
            modifier: $this->modifier,
            boundsSelection: $boundsSelection,
        )
            ->to(LivewireCollection::class);
    }

    protected function getParamKey()
    {
        return ParamKey::build($this->field, $this->condition, $this->modifier);
    }
}
