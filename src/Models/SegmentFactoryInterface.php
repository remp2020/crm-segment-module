<?php

namespace Crm\SegmentModule\Models;

interface SegmentFactoryInterface
{
    /**
     * @param string|SegmentConfig $segment either segment code or SegmentConfig object
     *
     * @return SegmentInterface
     */
    public function buildSegment(string|SegmentConfig $segment): SegmentInterface;
}
