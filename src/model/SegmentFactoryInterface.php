<?php

namespace Crm\SegmentModule;

use Crm\SegmentModule\Repository\SegmentsRepository;
use Nette\Database\Context;
use Nette\UnexpectedValueException;

interface SegmentFactoryInterface
{
    public function buildSegment(string $segmentIdentifier): SegmentInterface;
}
