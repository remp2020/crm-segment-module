<?php

namespace Crm\SegmentModule\Events;

use Crm\SegmentModule\Models\SegmentQuery;
use Crm\SegmentModule\Repositories\SegmentCodeInUseException;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class BeforeSegmentCodeUpdateHandler extends AbstractListener
{
    public function __construct(private SegmentsRepository $segmentsRepository)
    {
    }

    public function handle(EventInterface $event)
    {
        if (!$event instanceof BeforeSegmentCodeUpdateEvent) {
            throw new \Exception("Invalid type of event received, 'BeforeSegmentCodeUpdateEvent' expected: " . get_class($event));
        }

        $segmentRow = $event->getSegment();
        $segments = SegmentQuery::nestedSegmentReferences($this->segmentsRepository, $segmentRow);
        if ($segments) {
            $referencingSegment = reset($segments);
            throw new SegmentCodeInUseException(
                // indicate at least first referencing segment
                referencingSegmentCode: $referencingSegment->code,
                message: "Error updating segment code, other segment '{$referencingSegment->code}' references it",
            );
        }
    }
}
