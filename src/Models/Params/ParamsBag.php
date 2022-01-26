<?php

namespace Crm\SegmentModule\Params;

class ParamsBag
{
    private $params = [];

    public function addParam(BaseParam $param)
    {
        $this->params[$param->key()] = $param;
        return $this;
    }

    public function params(): array
    {
        return $this->params;
    }

    public function has($key): bool
    {
        return array_key_exists($key, $this->params);
    }

    public function get($key): BaseParam
    {
        if (!isset($this->params[$key])) {
            throw new InvalidParamException("Param [{$key}] not provided. Did you use has() method before getting optional param?");
        }
        return $this->params[$key];
    }

    public function boolean($key): BooleanParam
    {
        return $this->get($key);
    }

    public function stringArray($key): StringArrayParam
    {
        return $this->get($key);
    }

    public function datetime($key): DateTimeParam
    {
        return $this->get($key);
    }

    public function numberArray($key): NumberArrayParam
    {
        return $this->get($key);
    }

    public function number($key): NumberParam
    {
        return $this->get($key);
    }

    public function decimal($key): DecimalParam
    {
        return $this->get($key);
    }

    public function string($key): StringParam
    {
        return $this->get($key);
    }
}
