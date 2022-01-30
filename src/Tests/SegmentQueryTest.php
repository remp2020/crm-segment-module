<?php

namespace Crm\SegmentsModule\Tests;

use Crm\SegmentModule\SegmentQuery;
use PHPUnit\Framework\TestCase;

class SegmentQueryTest extends TestCase
{
    public function dataProvider()
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

    /** @dataProvider dataProvider */
    public function testQueries($fields, $query, $result)
    {
        $segment = new SegmentQuery($query, '_table', $fields);
        $this->assertEquals($result, $segment->getQuery());
    }
}
