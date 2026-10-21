<?php

namespace Reach\StatamicLivewireFilters\Tests\Feature;

use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Support\RangeBoundsQuery;
use Reach\StatamicLivewireFilters\Tests\TestCase;

class RangeBoundsQueryTest extends TestCase
{
    #[Test]
    public function it_round_trips_authenticated_query_revisions()
    {
        $params = ['category:is' => 'catamaran'];
        $selections = ['range-component' => 2, 'dual-range-component' => 4];
        $token = RangeBoundsQuery::encode('yachts', $params, 'collection-component', 3, $selections);
        $expectedParams = array_merge($params, ['from' => 'yachts']);

        $this->assertSame([
            'params' => $expectedParams,
            'source' => 'collection-component',
            'revision' => 3,
            'selections' => $selections,
        ], RangeBoundsQuery::decodeForCollection($token, 'yachts'));
        $this->assertSame($expectedParams, RangeBoundsQuery::paramsForCollection($token, 'yachts'));
    }

    #[Test]
    public function it_preserves_legacy_authenticated_query_params_without_revision_metadata()
    {
        $params = ['category:is' => 'catamaran'];
        $token = Crypt::encryptString(json_encode([
            'purpose' => 'range-bounds',
            'collections' => 'yachts',
            'params' => $params,
        ], JSON_THROW_ON_ERROR));
        $expectedParams = array_merge($params, ['from' => 'yachts']);

        $this->assertSame([
            'params' => $expectedParams,
            'source' => null,
            'revision' => 0,
            'selections' => [],
        ], RangeBoundsQuery::decodeForCollection($token, 'yachts'));
        $this->assertSame($expectedParams, RangeBoundsQuery::paramsForCollection($token, 'yachts'));
    }

    #[Test]
    #[DataProvider('invalidRevisionMetadata')]
    public function it_rejects_invalid_authenticated_revision_metadata(array $metadata)
    {
        $token = Crypt::encryptString(json_encode(array_merge([
            'purpose' => 'range-bounds',
            'collections' => 'yachts',
            'params' => ['category:is' => 'catamaran'],
        ], $metadata), JSON_THROW_ON_ERROR));

        $this->assertNull(RangeBoundsQuery::decodeForCollection($token, 'yachts'));
        $this->assertNull(RangeBoundsQuery::paramsForCollection($token, 'yachts'));
    }

    public static function invalidRevisionMetadata(): array
    {
        return [
            'numeric source' => [['source' => 1, 'revision' => 3]],
            'boolean source' => [['source' => false, 'revision' => 3]],
            'array source' => [['source' => ['collection-component'], 'revision' => 3]],
            'missing revision' => [['source' => 'collection-component']],
            'null revision' => [['source' => 'collection-component', 'revision' => null]],
            'string revision' => [['source' => 'collection-component', 'revision' => '3']],
            'fractional revision' => [['source' => 'collection-component', 'revision' => 3.5]],
            'boolean revision' => [['source' => 'collection-component', 'revision' => true]],
            'negative revision' => [['source' => 'collection-component', 'revision' => -1]],
            'null selections' => [['selections' => null]],
            'string selections' => [['selections' => 'range-component']],
            'empty selection source' => [['selections' => ['' => 1]]],
            'null selection revision' => [['selections' => ['range-component' => null]]],
            'string selection revision' => [['selections' => ['range-component' => '1']]],
            'fractional selection revision' => [['selections' => ['range-component' => 1.5]]],
            'boolean selection revision' => [['selections' => ['range-component' => true]]],
            'zero selection revision' => [['selections' => ['range-component' => 0]]],
            'negative selection revision' => [['selections' => ['range-component' => -1]]],
        ];
    }
}
