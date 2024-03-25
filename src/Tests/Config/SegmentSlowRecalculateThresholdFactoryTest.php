<?php
declare(strict_types=1);

namespace Crm\SegmentModule\Tests\Config;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\SegmentModule\Models\Config\SegmentSlowRecalculateThresholdFactory;
use PHPUnit\Framework\TestCase;

class SegmentSlowRecalculateThresholdFactoryTest extends TestCase
{
    public function testBuild(): void
    {
        $mockedApplicationConfig = $this->createMock(ApplicationConfig::class);
        $mockedApplicationConfig->expects($this->once())
            ->method('get')
            ->with('segment_slow_recalculate_threshold')
            ->willReturn(10);

        $factory = new SegmentSlowRecalculateThresholdFactory($mockedApplicationConfig);
        $this->assertSame(10, $factory->build()->thresholdInSeconds);
    }

    public function testBuildWithDomainException(): void
    {
        $mockedApplicationConfig = $this->createMock(ApplicationConfig::class);
        $mockedApplicationConfig->expects($this->once())
            ->method('get')
            ->with('segment_slow_recalculate_threshold')
            ->willReturn(-1);

        $factory = new SegmentSlowRecalculateThresholdFactory($mockedApplicationConfig);
        $this->assertNull($factory->build()->thresholdInSeconds);
    }

    public function testBuildWithException(): void
    {
        $mockedApplicationConfig = $this->createMock(ApplicationConfig::class);
        $mockedApplicationConfig->expects($this->once())
            ->method('get')
            ->with('segment_slow_recalculate_threshold')
            ->willReturn('not a valid value');

        $factory = new SegmentSlowRecalculateThresholdFactory($mockedApplicationConfig);
        $this->assertNull($factory->build()->thresholdInSeconds);
    }
}
