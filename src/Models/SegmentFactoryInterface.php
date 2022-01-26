<?php

namespace Crm\SegmentModule;

interface SegmentFactoryInterface
{
    public function buildSegment(string $segmentIdentifier): SegmentInterface;
}
