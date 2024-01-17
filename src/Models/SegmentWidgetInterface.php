<?php

namespace Crm\SegmentModule\Models;

use Nette\Database\Table\ActiveRow;

interface SegmentWidgetInterface
{
    public function recalculate(ActiveRow $segment, array $ids): void;
}
