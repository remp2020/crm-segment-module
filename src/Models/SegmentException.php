<?php

namespace Crm\SegmentModule\Models;

use Nette\Database\DriverException;

/**
 * This exception extends DriverException just to preserve backwards compatibility with
 * the Crm\SegmentModule\Models\SegmentInterface implementations which is still using Nette\Database\DriverException.
 */
class SegmentException extends DriverException
{

}
