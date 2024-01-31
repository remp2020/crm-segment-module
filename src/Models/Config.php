<?php

namespace Crm\SegmentModule\Models;

class Config
{
    private bool $segmentNestingEnabled = false;

    public function setSegmentNestingEnabled(bool $value = true): void
    {
        $this->segmentNestingEnabled = $value;
    }

    public function isSegmentNestingEnabled(): bool
    {
        return $this->segmentNestingEnabled;
    }
}
