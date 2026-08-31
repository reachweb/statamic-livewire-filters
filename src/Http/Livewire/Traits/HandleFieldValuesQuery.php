<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Reach\StatamicLivewireFilters\Support\CountQueryPool;

/**
 * Shared faceted-query helpers for traits that react to `params-updated`
 * (HandleEntriesCount, HandleFieldBounds): strip the listening filter's own
 * params so results are faceted on everything except itself, then pluck the
 * field's values through the request-scoped CountQueryPool. Composing traits
 * both `use` this one, so a single component must never use two of them.
 */
trait HandleFieldValuesQuery
{
    protected function resolveCountFieldHandle(): string
    {
        $blueprint = $this->getStatamicBlueprint();

        return $this->getStatamicField($blueprint)->handle();
    }

    protected function removeCurrentFieldFromParams($params, $fieldHandle)
    {
        $baseParams = [];

        foreach ($params as $key => $value) {
            // Check if this is a dual_range field (has min/max keys)
            if ($this->condition === 'dual_range') {
                // Skip both min and max keys for the current field
                if (str_starts_with($key, $fieldHandle.':')) {
                    continue;
                }
            }

            // Skip standard field parameters
            if (str_starts_with($key, $fieldHandle.':')) {
                continue;
            }

            // Skip taxonomy parameters
            if (str_starts_with($key, 'taxonomy:'.$fieldHandle)) {
                continue;
            }

            // Handle query_scope - skip the current field's scoped param
            if ($this->condition === 'query_scope' && $key === $this->modifier.':'.$fieldHandle) {
                continue;
            }

            $baseParams[$key] = $value;
        }

        // Clean up the query_scope param only if no remaining params use this scope
        if ($this->condition === 'query_scope' && isset($baseParams['query_scope'])) {
            $hasRemainingParams = collect($baseParams)->keys()->contains(
                fn ($key) => str_starts_with($key, $this->modifier.':')
            );

            if (! $hasRemainingParams) {
                $scopes = explode('|', $baseParams['query_scope']);
                $scopes = array_filter($scopes, fn ($scope) => $scope !== $this->modifier);

                if (empty($scopes)) {
                    unset($baseParams['query_scope']);
                } else {
                    $baseParams['query_scope'] = implode('|', $scopes);
                }
            }
        }

        return $baseParams;
    }

    protected function getCountFieldValues(array $baseParams, string $fieldHandle)
    {
        return app(CountQueryPool::class)->getFieldValues($this->collection, $baseParams, $fieldHandle);
    }
}
