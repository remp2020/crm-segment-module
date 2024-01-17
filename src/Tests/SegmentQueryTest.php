<?php

namespace Crm\SegmentModule\Tests;

use Crm\SegmentModule\Models\SegmentQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SegmentQueryTest extends TestCase
{
    public static function dataProvider()
    {
        return [
            'Select_MultipleFieldAliases_ShouldBePreserved' => [
                'fields' => 'id AS foo, id AS bar, email',
                'query' => 'SELECT %fields%',
                'result' => 'SELECT id AS foo, id AS bar, email',
            ],
            'GroupBy_MultipleFieldAliases_ShouldBeMergedAndUnique' => [
                'fields' => 'id AS foo, id AS bar, email',
                'query' => 'GROUP BY %group_by%',
                'result' => 'GROUP BY id, email',
            ],
        ];
    }

    #[DataProvider('dataProvider')]
    public function testQueries($fields, $query, $result)
    {
        $segment = new SegmentQuery($query, '_table', $fields);
        $this->assertEquals($result, $segment->getQuery());
    }
}
