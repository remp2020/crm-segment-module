<?php

namespace Crm\SegmentModule\Params;

class ParamsBag
{
    private array $params = [];

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
        $param = $this->get($key);
        if (!$param instanceof BooleanParam) {
            throw new \Exception("Requested param '{$key}' is not BooleanParam: " . get_class($param));
        }
        return $param;
    }

    public function stringArray($key): StringArrayParam
    {
        $param = $this->get($key);
        if (!$param instanceof StringArrayParam) {
            throw new \Exception("Requested param '{$key}' is not StringArrayParam: " . get_class($param));
        }
        return $param;
    }

    public function datetime($key): DateTimeParam
    {
        $param = $this->get($key);
        if (!$param instanceof DateTimeParam) {
            throw new \Exception("Requested param '{$key}' is not DateTimeParam: " . get_class($param));
        }
        return $param;
    }

    public function numberArray($key): NumberArrayParam
    {
        $param = $this->get($key);
        if (!$param instanceof NumberArrayParam) {
            throw new \Exception("Requested param '{$key}' is not NumberArrayParam: " . get_class($param));
        }
        return $param;
    }

    public function number($key): NumberParam
    {
        $param = $this->get($key);
        if (!$param instanceof NumberParam) {
            throw new \Exception("Requested param '{$key}' is not NumberParam: " . get_class($param));
        }
        return $param;
    }

    public function decimal($key): DecimalParam
    {
        $param = $this->get($key);
        if (!$param instanceof DecimalParam) {
            throw new \Exception("Requested param '{$key}' is not DecimalParam: " . get_class($param));
        }
        return $param;
    }

    public function string($key): StringParam
    {
        $param = $this->get($key);
        if (!$param instanceof StringParam) {
            throw new \Exception("Requested param '{$key}' is not StringParam: " . get_class($param));
        }
        return $param;
    }
}
