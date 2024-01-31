<?php

namespace Crm\SegmentModule\Repositories;

use Throwable;

class SegmentCodeInUseException extends \Exception
{
    public function __construct(
        private readonly string $referencingSegmentCode,
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getReferencingSegmentCode(): string
    {
        return $this->referencingSegmentCode;
    }
}
