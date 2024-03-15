<?php

namespace Crm\SegmentModule\Models;

use Closure;

interface SegmentInterface
{
    /**
     * @throws SegmentException
     */
    public function totalCount();

    /**
     * @throws SegmentException
     */
    public function getIds();

    /**
     * @throws SegmentException
     */
    public function process(Closure $rowCallback);

    /**
     * @throws SegmentException
     */
    public function isIn($field, $value);
}
