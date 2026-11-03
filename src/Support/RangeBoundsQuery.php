<?php

namespace Reach\StatamicLivewireFilters\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use JsonException;

class RangeBoundsQuery
{
    /**
     * @param  array<int, array{0: string, 1: ?string}>  $facets  the facet specs registered with the collection (facet engine)
     */
    public static function encode($collections, array $params, ?string $source = null, int $revision = 0, array $selections = [], array $facets = []): string
    {
        $query = [
            'purpose' => 'range-bounds',
            'collections' => $collections,
            'params' => $params,
            'source' => $source,
            'revision' => $revision,
            'selections' => $selections,
        ];

        // The token rides on every listener's call, so it only grows when facets are registered.
        if ($facets !== []) {
            $query['facets'] = $facets;
        }

        return Crypt::encryptString(json_encode($query, JSON_THROW_ON_ERROR));
    }

    public static function paramsForCollection($token, string $collection): ?array
    {
        return self::decodeForCollection($token, $collection)['params'] ?? null;
    }

    public static function decodeForCollection($token, string $collection): ?array
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $query = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException $exception) {
            return null;
        }

        if (! is_array($query) || ($query['purpose'] ?? null) !== 'range-bounds'
            || ! is_array($query['params'] ?? null)) {
            return null;
        }

        $collections = $query['collections'] ?? [];

        if ($collections !== '*'
            && ! in_array($collection, is_string($collections) ? explode('|', $collections) : (array) $collections, true)) {
            return null;
        }

        $source = $query['source'] ?? null;
        $revision = $query['revision'] ?? null;

        if ($source !== null
            && (! is_string($source) || ! is_int($revision) || $revision < 0)) {
            return null;
        }

        $selections = array_key_exists('selections', $query) ? $query['selections'] : [];

        if (! is_array($selections)) {
            return null;
        }

        foreach ($selections as $selectionSource => $selectionRevision) {
            if ((string) $selectionSource === '' || ! is_int($selectionRevision) || $selectionRevision < 1) {
                return null;
            }
        }

        $facets = collect(is_array($query['facets'] ?? null) ? $query['facets'] : [])
            ->map(fn ($spec) => is_array($spec) ? FacetSpec::from($spec[0] ?? null, $spec[1] ?? null) : null)
            ->filter()
            ->values()
            ->all();

        $decoded = [
            'params' => array_merge($query['params'], ['from' => $collections]),
            'source' => $source,
            'revision' => $source === null ? 0 : $revision,
            'selections' => $selections,
        ];

        // Present only when the collection registered facets (facet engine).
        if ($facets !== []) {
            $decoded['facets'] = $facets;
        }

        return $decoded;
    }
}
