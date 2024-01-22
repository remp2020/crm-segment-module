<?php

namespace Crm\SegmentModule;

use Crm\SegmentModule\Repository\SegmentsRepository;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
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

    public function buildSegment(string|ActiveRow $segment): SegmentInterface
    {
        if ($segment instanceof ActiveRow) {
            $segmentRow = $segment;
        } else {
            $segmentRow = $this->segmentsRepository->findByCode($segment);
            if (!$segmentRow) {
                throw new UnexpectedValueException("segment code [{$segment}] does not exist");
            }
        }

        $query = new SegmentQuery($segmentRow->query_string, $segmentRow->table_name, $segmentRow->fields);
        return new Segment($this->database, $query);
    }
}
