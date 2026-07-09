<?php

declare(strict_types=1);

namespace Crm\SegmentModule\Models;

use Crm\SegmentModule\Exceptions\SegmentQueryValidationException;

/**
 * To be implemented by every segment that users can write or modify.
 */
interface ValidatableSegmentInterface
{
    /**
     * Validates the user-entered segment definition. For example, a segment built on top
     * of a query validates the query itself (see Segment, which delegates to
     * ValidatableQueryInterface); a segment backed by another data source validates
     * whatever information the user entered.
     *
     * @throws SegmentQueryValidationException
     */
    public function validate(): void;
}
