<?php
declare(strict_types=1);

namespace Crm\SegmentModule\Tests\Config;

use Crm\SegmentModule\Models\Config\SegmentSlowRecalculateThreshold;
use DomainException;
use PHPUnit\Framework\TestCase;

class SegmentSlowRecalculateThresholdTest extends TestCase
{
    public function testThreshold(): void
    {
        $threshold = new SegmentSlowRecalculateThreshold(10);
        $this->assertSame(10, $threshold->thresholdInSeconds);
    }

    public function testNegativeThreshold(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Threshold must be a positive number.');

        new SegmentSlowRecalculateThreshold(-1);
    }

    public function testNotSetThreshold(): void
    {
        $threshold = new SegmentSlowRecalculateThreshold(null);
        $this->assertNull($threshold->thresholdInSeconds);
    }
}
