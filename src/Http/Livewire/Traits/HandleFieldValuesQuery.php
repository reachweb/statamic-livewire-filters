<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire\Traits;

use Reach\StatamicLivewireFilters\Support\CountQueryPool;

/**
 * Faceted-query helpers shared by HandleEntriesCount and HandleFieldBounds;
 * a component must not compose both.
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

        // A scope named like this field makes the prefix ambiguous ({scope}:{field});
        // keeping ambiguous keys can only narrow the result, never ignore a filter
        $fieldHandleCollidesWithScope = in_array($fieldHandle, $this->activeScopeNames($params), true);

        foreach ($params as $key => $value) {
            if (! $fieldHandleCollidesWithScope && str_starts_with($key, $fieldHandle.':')) {
                continue;
            }

            if (str_starts_with($key, 'taxonomy:'.$fieldHandle)) {
                continue;
            }

            if ($this->condition === 'query_scope' && $key === $this->modifier.':'.$fieldHandle) {
                continue;
            }

            $baseParams[$key] = $value;
        }

        // Drop the scope registration when no remaining params use it
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

    /**
     * @return array<int, string>
     */
    protected function activeScopeNames($params): array
    {
        if (! isset($params['query_scope']) || ! is_string($params['query_scope'])) {
            return [];
        }

        return explode('|', $params['query_scope']);
    }
}
