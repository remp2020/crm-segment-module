<?php

namespace Crm\SegmentModule;

use Closure;

interface SegmentInterface
{
    public function totalCount();

    public function process(Closure $rowCallback);

    public function isIn($field, $value);
}
