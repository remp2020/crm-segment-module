<?php

namespace Crm\SegmentModule\Params;

class BooleanParam extends BaseParam
{
    protected $type = 'boolean';

    public function isTrue(): bool
    {
        return $this->data === true;
    }

    public function isFalse(): bool
    {
        return $this->data === false;
    }

    public function number(): string
    {
        return $this->data === true ? '1' : '0';
    }

    public function isValid($data): Validation
    {
        $result = $data === true || $data === false;
        if (!$result) {
            return new Validation("Data '{$data}' must be true or false");
        }
        return new Validation();
    }

    public function equals(BaseParam $param): bool
    {
        if (!($param instanceof static)) {
            throw new \Exception("Cannot compare " . get_class($param) . ' with BooleanParam');
        }
        return $param->isTrue() == $this->isTrue();
    }
}
