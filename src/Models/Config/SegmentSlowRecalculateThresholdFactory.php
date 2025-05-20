<?php
declare(strict_types=1);

namespace Crm\SegmentModule\Models\Config;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Exception;
use Tracy\Debugger;

class SegmentSlowRecalculateThresholdFactory
{
    private const SLOW_RECALCULATE_THRESHOLD_CONFIG_KEY = 'segment_slow_recalculate_threshold';

    public function __construct(
        private readonly ApplicationConfig $applicationConfig,
    ) {
    }

    public function build(): SegmentSlowRecalculateThreshold
    {
        try {
            $threshold = $this->applicationConfig->get(self::SLOW_RECALCULATE_THRESHOLD_CONFIG_KEY);
            if (!is_numeric($threshold)) {
                throw new Exception('Threshold must be a number.');
            }

            return new SegmentSlowRecalculateThreshold($threshold);
        } catch (Exception $exception) {
            $errorMessage = sprintf(
                'Bad value in configuration for `%s`. %s',
                self::SLOW_RECALCULATE_THRESHOLD_CONFIG_KEY,
                $exception->getMessage(),
            );
            Debugger::log($errorMessage, Debugger::WARNING);

            return new SegmentSlowRecalculateThreshold(null);
        }
    }
}
