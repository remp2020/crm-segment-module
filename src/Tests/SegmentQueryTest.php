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
                'fields' => "id, id AS foo, id AS bar, email, CONCAT(id, ' ', email) AS name",
                'query' => 'SELECT %fields%',
                'result' => "SELECT _table.id, id AS foo, id AS bar, email, CONCAT(id, ' ', email) AS name",
            ],
            'GroupBy_MultipleFieldAliases_ShouldBeMergedAndUnique' => [
                'fields' => 'id AS foo, id AS bar, email',
                'query' => 'GROUP BY %group_by%',
                'result' => 'GROUP BY id, email',
            ],
            'Select_AllSelectFieldsAfterStringSplit_ShouldBePreserved' => [
                'fields' => "id, foo1 AS foo, bar1 AS bar, COALESCE(whatever, 'unk,nown') AS foo2, DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00') AS foo3, CONCAT(id, ' , ', email) AS foo4",
                'query' => 'SELECT %fields%',
                'result' => "SELECT _table.id, foo1 AS foo, bar1 AS bar, COALESCE(whatever, 'unk,nown') AS foo2, DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00') AS foo3, CONCAT(id, ' , ', email) AS foo4",
            ]
        ];
    }

    #[DataProvider('dataProvider')]
    public function testQueries($fields, $query, $result)
    {
        $segment = new SegmentQuery($query, '_table', $fields);
        $this->assertEquals($result, $segment->getQuery());
    }
}
