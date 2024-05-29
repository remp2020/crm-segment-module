<?php
declare(strict_types=1);

namespace Crm\SegmentModule\Api\DailyCountStats;

use Crm\ApplicationModule\Domain\OptionalDateTimeRange;
use Crm\SegmentModule\Repositories\SegmentsValuesRepository;

class DailySegmentValuesQuery
{
    public function __construct(
        private readonly SegmentsValuesRepository $segmentsValuesRepository,
    ) {
    }

    /**
     * @return array<string, int> Key is a date in format YYYY-MM-DD; Value is a count
     */
    public function retrieve(int $segmentId, OptionalDateTimeRange $dateTimeRange): array
    {
        // The reason for DATE_FORMAT is that DATE() function returns date in format 'YYYY-MM-DD 00:00:00' via Nette Database
        $selection = $this->segmentsValuesRepository->getTable()
            ->select('DATE_FORMAT(date, ?) AS formatted_date, MAX(value) AS `count`', ["%Y-%m-%d"])
            ->where('segment_id', $segmentId)
            ->group('formatted_date')
            ->order('formatted_date ASC');

        if ($dateTimeRange->dateFrom !== null) {
            $selection->where('date >= ?', $dateTimeRange->dateFrom);
        }

        if ($dateTimeRange->dateTo !== null) {
            $selection->where('date <= ?', $dateTimeRange->dateTo);
        }

        return $selection->fetchPairs('formatted_date', 'count');
    }
}
