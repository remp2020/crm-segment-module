<?php

namespace Crm\SegmentModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class BeforeSegmentCodeUpdateEvent extends AbstractEvent
{
    /**
     * Emitting this event may throw SegmentCodeInUseException.
     * In such case, do not continue with segment code update.
     * @param ActiveRow $segment
     */
    public function __construct(private ActiveRow $segment)
    {
    }

    public function getSegment(): ActiveRow
    {
        return $this->segment;
    }
}
