<?php

namespace Crm\SegmentModule;

use Crm\SegmentModule\Repository\SegmentsRepository;
use Nette\Database\Explorer;
use Nette\UnexpectedValueException;

class SegmentFactory implements SegmentFactoryInterface
{
    private $segmentsRepository;

    private $database;

    public function __construct(Explorer $database, SegmentsRepository $segmentsRepository)
    {
        $this->database = $database;
        $this->segmentsRepository = $segmentsRepository;
    }

    public function buildSegment(string $segmentIdentifier): SegmentInterface
    {
        $segmentRow = $this->segmentsRepository->findByCode($segmentIdentifier);
        if (!$segmentRow) {
            throw new UnexpectedValueException("segment does not exist: {$segmentIdentifier}");
        }
        $query = new SegmentQuery($segmentRow->query_string, $segmentRow->table_name, $segmentRow->fields);
        return new Segment($this->database, $query);
    }
}
