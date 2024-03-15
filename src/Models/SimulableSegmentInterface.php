<?php

namespace Crm\SegmentModule\Models;

interface SimulableSegmentInterface
{
    /**
     * @throws SegmentException
     */
    public function simulate(): void;
}
