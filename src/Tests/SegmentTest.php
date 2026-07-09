<?php

declare(strict_types=1);

namespace Crm\SegmentModule\Tests;

use Crm\SegmentModule\Exceptions\SegmentQueryValidationException;
use Crm\SegmentModule\Models\QueryInterface;
use Crm\SegmentModule\Models\Segment;
use Crm\SegmentModule\Models\SegmentQuery;
use Nette\Database\Explorer;
use PHPUnit\Framework\TestCase;

class SegmentTest extends TestCase
{
    public function testValidateDelegatesToValidatableQuery(): void
    {
        $segment = new Segment(
            $this->createStub(Explorer::class),
            new SegmentQuery('SELECT %fields% FROM %table%', '_table', 'id'),
        );

        $this->expectException(SegmentQueryValidationException::class);
        $segment->validate();
    }

    public function testValidateConsidersNonValidatableQueryValid(): void
    {
        $segment = new Segment(
            $this->createStub(Explorer::class),
            $this->createStub(QueryInterface::class),
        );

        $segment->validate();
        $this->assertTrue(true);
    }
}
