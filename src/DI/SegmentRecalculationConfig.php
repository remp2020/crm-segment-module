<?php

namespace Crm\SegmentModule\DI;

class SegmentRecalculationConfig
{
    public const DEFAULT_RECALCULATION_PERIODICITY = [
        'amount' => 6,
        'unit' => 'hours',
    ];

    private string $dailyRecalculationTime = '04:00';

    private string $hourlyRecalculationMinute = '30';

    public function setDailyRecalculationTime(string $time): void
    {
        $timeObj = \DateTime::createFromFormat('H:i', $time);
        if (!$timeObj || $timeObj->format('H:i') !== $time) {
            throw new \Exception('wrong config daily');
        }
        $this->dailyRecalculationTime = $time;
    }

    public function getDailyRecalculationTime(): string
    {
        return $this->dailyRecalculationTime;
    }

    public function getDefaultRecalculationPeriodicityInterval(): \DateInterval
    {
        return \DateInterval::createFromDateString(
            self::DEFAULT_RECALCULATION_PERIODICITY['amount'] . ' ' . self::DEFAULT_RECALCULATION_PERIODICITY['unit'],
        );
    }

    public function setHourlyRecalculationMinute(string $minute): void
    {
        $minuteObj = \DateTime::createFromFormat('i', $minute);
        if (!$minuteObj || $minuteObj->format('i') !== $minute) {
            throw new \Exception('wrong config hourly');
        }
        $this->hourlyRecalculationMinute = $minute;
    }

    public function getHourlyRecalculationMinute(): string
    {
        return $this->hourlyRecalculationMinute;
    }
}
