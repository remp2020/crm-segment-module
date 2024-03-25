<?php
declare(strict_types=1);

namespace Crm\SegmentModule\Models\Config;

use DomainException;

class SegmentSlowRecalculateThreshold
{
    public function __construct(public readonly ?int $thresholdInSeconds)
    {
        if ($thresholdInSeconds === null) {
            return;
        }

        if ($thresholdInSeconds < 0) {
            throw new DomainException('Threshold must be a positive number.');
        }
    }
}
