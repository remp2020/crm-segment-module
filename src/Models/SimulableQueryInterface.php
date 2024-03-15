<?php

namespace Crm\SegmentModule\Models;

interface SimulableQueryInterface
{
    /**
     * Simulate is meant to be used to quickly validate a segment's database query.
     *
     * @throws SegmentException
     */
    public function getSimulationQuery(): string;
}
