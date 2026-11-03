<?php

namespace Reach\StatamicLivewireFilters\Support;

use Statamic\Facades\Collection;
use Statamic\Support\Arr;

/**
 * What a faceted (counts or bounds) filter queries: a field, plus the query scope it
 * filters through. The pair alone decides which params a facet's query drops.
 */
class FacetSpec
{
    /**
     * Normalizes a spec from a client event or a token; null when it isn't one.
     *
     * @return array{0: string, 1: ?string}|null
     */
    public static function from($field, $scope = null): ?array
    {
        // The handle reaches a JSON column path on the Eloquent driver.
        if (! is_string($field) || ! preg_match('/^[A-Za-z0-9_-]{1,64}$/', $field)) {
            return null;
        }

        if ($scope !== null && (! is_string($scope) || ! preg_match('/^[A-Za-z0-9_-]{1,64}$/', $scope))) {
            return null;
        }

        return [$field, $scope];
    }

    /**
     * The params without the facet's own filter, so its counts and bounds show what
     * selecting it would match.
     */
    public static function baseParams(array $params, string $fieldHandle, ?string $scope): array
    {
        $baseParams = [];

        // A scope named like this field makes the prefix ambiguous ({scope}:{field});
        // keeping ambiguous keys can only narrow the result, never ignore a filter
        $fieldHandleCollidesWithScope = in_array($fieldHandle, self::activeScopeNames($params), true);

        foreach ($params as $key => $value) {
            if (! $fieldHandleCollidesWithScope && str_starts_with($key, $fieldHandle.':')) {
                continue;
            }

            if (str_starts_with($key, 'taxonomy:'.$fieldHandle)) {
                continue;
            }

            if ($scope !== null && $key === ParamKey::build($fieldHandle, 'query_scope', $scope)) {
                continue;
            }

            $baseParams[$key] = $value;
        }

        // Drop the scope registration when no remaining params use it
        if ($scope !== null && isset($baseParams['query_scope'])) {
            $hasRemainingParams = collect($baseParams)->keys()->contains(
                fn ($key) => str_starts_with($key, $scope.':')
            );

            if (! $hasRemainingParams) {
                $scopes = explode('|', $baseParams['query_scope']);
                $scopes = array_filter($scopes, fn ($registered) => $registered !== $scope);

                if (empty($scopes)) {
                    unset($baseParams['query_scope']);
                } else {
                    $baseParams['query_scope'] = implode('|', $scopes);
                }
            }
        }

        return $baseParams;
    }

    /**
     * The given collections whose blueprints contain the field.
     *
     * @return array<int, string>
     */
    public static function collectionsWithField(string|array $collections, string $fieldHandle): array
    {
        $handles = $collections === '*'
            ? Collection::handles()->all()
            : (is_string($collections) ? explode('|', $collections) : $collections);

        return collect($handles)
            ->unique()
            ->filter(fn ($handle) => Collection::find($handle)?->entryBlueprints()
                ->contains(fn ($blueprint) => $blueprint->hasField($fieldHandle)))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function activeScopeNames($params): array
    {
        $scopes = Arr::getFirst($params, ['query_scope', 'filter']);

        if (! is_string($scopes)) {
            return [];
        }

        return explode('|', $scopes);
    }
}
