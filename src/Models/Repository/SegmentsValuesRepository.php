<?php

namespace Crm\SegmentModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class SegmentsValuesRepository extends Repository
{
    protected $tableName = 'segments_values';

    private $segmentsRepository;

    public function __construct(
        Explorer $database,
        SegmentsRepository $segmentsRepository
    ) {
        parent::__construct($database);
        $this->segmentsRepository = $segmentsRepository;
    }

    final public function add(ActiveRow $segment, $date, $value)
    {
        return $this->insert([
            'segment_id' => $segment->id,
            'date' => $date,
            'value' => $value,
        ]);
    }

    final public function valuesBySegmentCode($code)
    {
        return $this->getTable()
            ->where('segment.code', $code);
    }

    final public function mostRecentValues($segmentCode)
    {
        return $this->valuesBySegmentCode($segmentCode)
            ->order('date DESC')
            ->limit(1)
            ->select('*')
            ->fetch();
    }

    final public function cacheSegmentCount(ActiveRow $segment, int $count, float $time)
    {
        $this->segmentsRepository->update($segment, [
            'cache_count' => $count,
            'cache_count_time' => $time,
        ]);
        $this->add($segment, new DateTime(), $count);
    }
}
