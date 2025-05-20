<?php

namespace Crm\SegmentModule\Models;

use Crm\SegmentModule\Repositories\SegmentsRepository;
use Nette\Database\Explorer;
use Nette\UnexpectedValueException;

class SegmentFactory implements SegmentFactoryInterface
{
    public function __construct(
        private Explorer $database,
        private SegmentsRepository $segmentsRepository,
    ) {
    }

    public function buildSegment(string|SegmentConfig $segment): SegmentInterface
    {
        if (is_string($segment)) {
            $segmentCode = $segment;
            $segmentRow = $this->segmentsRepository->findByCode($segmentCode);
            if (!$segmentRow) {
                throw new UnexpectedValueException("segment code [{$segmentCode}] does not exist");
            }
            $segment = SegmentConfig::fromSegmentActiveRow($segmentRow);
        }

        $query = new SegmentQuery(
            query: $segment->queryString,
            tableName: $segment->tableName,
            fields: $segment->fields,
            nestedSegments: SegmentQuery::nestedSegments($this->segmentsRepository, $segment),
        );
        return new Segment($this->database, $query);
    }
}
