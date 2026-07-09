<?php

declare(strict_types=1);

namespace Crm\SegmentModule\Models;

use Crm\SegmentModule\Exceptions\SegmentQueryValidationException;

/**
 * To be implemented by every query that users can write or modify.
 */
interface ValidatableQueryInterface
{
    /**
     * Validates the query as written, with placeholders still in place (before their replacement).
     * For example, checks that required placeholders are present or that used placeholders
     * are valid (catching typos in placeholder names).
     *
     * @throws SegmentQueryValidationException
     */
    public function validate(): void;
}
