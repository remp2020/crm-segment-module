<?php

namespace Crm\SegmentModule\Params;

class Validation
{
    private $error = false;

    public function __construct(string $error = null)
    {
        if ($error !== null) {
            $this->error = $error;
        }
    }

    public function ok(): bool
    {
        return $this->error === false;
    }

    public function error(): ?string
    {
        return $this->error === false ? null : $this->error;
    }
}
