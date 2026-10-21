<?php

namespace Reach\StatamicLivewireFilters\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use JsonException;

class RangeBoundsQuery
{
    public static function encode($collections, array $params, ?string $source = null, int $revision = 0, array $selections = []): string
    {
        return Crypt::encryptString(json_encode([
            'purpose' => 'range-bounds',
            'collections' => $collections,
            'params' => $params,
            'source' => $source,
            'revision' => $revision,
            'selections' => $selections,
        ], JSON_THROW_ON_ERROR));
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

        return [
            'params' => array_merge($query['params'], ['from' => $collections]),
            'source' => $source,
            'revision' => $source === null ? 0 : $revision,
            'selections' => $selections,
        ];
    }
}
