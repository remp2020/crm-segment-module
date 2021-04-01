<?php

namespace Crm\SegmentModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Context;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;

class SegmentsValuesRepository extends Repository
{
    protected $tableName = 'segments_values';

    private $segmentsRepository;

    public function __construct(
        Context $database,
        SegmentsRepository $segmentsRepository
    ) {
        parent::__construct($database);
        $this->segmentsRepository = $segmentsRepository;
    }

    final public function add(IRow $segment, $date, $value)
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

    final public function cacheSegmentCount(ActiveRow $segment, int $count)
    {
        $segment = $this->segmentsRepository->setLock($segment, false);
        $this->segmentsRepository->update($segment, ['cache_count' => $count]);
        $segment = $this->segmentsRepository->setLock($segment, true);

        $this->add($segment, new DateTime(), $count);
    }
}
