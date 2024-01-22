<?php

namespace Crm\SegmentModule;

use Nette\Database\Table\ActiveRow;

interface SegmentFactoryInterface
{
    public function buildSegment(string|ActiveRow $segment): SegmentInterface;
}
