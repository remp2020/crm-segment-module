<?php

namespace Crm\SegmentModule;

use Crm\SegmentModule\Repository\SegmentsRepository;
use Nette\Database\Context;
use Nette\UnexpectedValueException;

class SegmentFactory implements SegmentFactoryInterface
{
    private $segmentsRepository;

    private $context;

    public function __construct(Context $context, SegmentsRepository $segmentsRepository)
    {
        $this->context = $context;
        $this->segmentsRepository = $segmentsRepository;
    }

    public function buildSegment(string $segmentIdentifier): SegmentInterface
    {
        $segmentRow = $this->segmentsRepository->findByCode($segmentIdentifier);
        if (!$segmentRow) {
            throw new UnexpectedValueException("segment does not exist: {$segmentIdentifier}");
        }
        $query = new SegmentQuery($segmentRow->query_string, $segmentRow->table_name, $segmentRow->fields);
        return new Segment($this->context, $query);
    }
}
