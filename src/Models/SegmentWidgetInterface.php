<?php

namespace Crm\SegmentModule;

use Nette\Database\Table\ActiveRow;

interface SegmentWidgetInterface
{
    public function recalculate(ActiveRow $segment, array $ids): void;
}
