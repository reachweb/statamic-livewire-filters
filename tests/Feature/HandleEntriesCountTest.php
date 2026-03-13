<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Reach\StatamicLivewireFilters\Http\Livewire\Traits\HandleEntriesCount;
use Reach\StatamicLivewireFilters\Tests\TestCase;

class HandleEntriesCountTest extends TestCase
{
    #[Test]
    public function it_removes_the_current_query_scope_field_and_preserves_other_scopes_for_count_queries()
    {
        $component = new class
        {
            use HandleEntriesCount;

            public $condition = 'query_scope';

            public $modifier = 'multiselect';

            public function removeParams(array $params, string $fieldHandle): array
            {
                return $this->removeCurrentFieldFromParams($params, $fieldHandle);
            }
        };

        $params = [
            'query_scope' => 'multiselect|some_other_scope',
            'multiselect:car_type' => '4x4|SUV',
            'some_other_scope:origin' => 'japan',
        ];

        $this->assertSame([
            'query_scope' => 'some_other_scope',
            'some_other_scope:origin' => 'japan',
        ], $component->removeParams($params, 'car_type'));
    }

    #[Test]
    public function it_removes_the_query_scope_key_entirely_when_the_current_scope_is_the_only_one()
    {
        $component = new class
        {
            use HandleEntriesCount;

            public $condition = 'query_scope';

            public $modifier = 'multiselect';

            public function removeParams(array $params, string $fieldHandle): array
            {
                return $this->removeCurrentFieldFromParams($params, $fieldHandle);
            }
        };

        $params = [
            'query_scope' => 'multiselect',
            'multiselect:car_type' => '4x4|SUV',
        ];

        $this->assertSame([], $component->removeParams($params, 'car_type'));
    }

    #[Test]
    public function it_keeps_the_scope_when_other_fields_still_use_it()
    {
        $component = new class
        {
            use HandleEntriesCount;

            public $condition = 'query_scope';

            public $modifier = 'multiselect';

            public function removeParams(array $params, string $fieldHandle): array
            {
                return $this->removeCurrentFieldFromParams($params, $fieldHandle);
            }
        };

        $params = [
            'query_scope' => 'multiselect|some_other_scope',
            'multiselect:car_type' => '4x4|SUV',
            'multiselect:origin' => 'japan',
            'some_other_scope:color' => 'red',
        ];

        $this->assertSame([
            'query_scope' => 'multiselect|some_other_scope',
            'multiselect:origin' => 'japan',
            'some_other_scope:color' => 'red',
        ], $component->removeParams($params, 'car_type'));
    }
}
