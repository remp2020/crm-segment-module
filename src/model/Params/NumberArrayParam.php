<?php

namespace Crm\SegmentModule\Params;

class NumberArrayParam extends BaseParam
{
    protected $type = 'number_array';

    private $options;

    public function __construct(string $key, string $label, string $help, bool $required = false, $default = null, $group = null, ?array $options = null)
    {
        parent::__construct($key, $label, $help, $required, $default, $group);

        if (is_array($options)) {
            $newOptions = [];
            foreach ($options as $optionKey => $value) {
                $newOptions[(int) $optionKey] = $value;
            }
            $this->options = $newOptions;
        }
    }

    public function options(): ?array
    {
        return $this->options;
    }

    public function blueprint(): array
    {
        $blueprint = parent::blueprint();
        if ($this->options) {
            $blueprint['available'] = $this->options;
        }
        return $blueprint;
    }

    public function sortedNumbers(): array
    {
        $array = unserialize(serialize($this->data));
        sort($array);
        return $array;
    }

    public function escapedString($glue = ','): string
    {
        $values = [];
        foreach ($this->data as $value) {
            if ($this->options === null) {
                $values[] = (int) $value;
            } else {
                if (array_key_exists($value, $this->options)) {
                    $values[] = (int) $value;
                }
            }
        }
        return implode($glue, $values);
    }

    public function title($glue = ','): string
    {
        $values = [];
        foreach ($this->data as $value) {
            if ($this->options == null) {
                $values[] = (int) $value;
            } else {
                if (array_key_exists($value, $this->options)) {
                    $values[] = $this->options[$value];
                }
            }
        }
        return implode($glue, $values);
    }

    public function isValid($data): Validation
    {
        if (!is_array($data) || empty($data)) {
            return new Validation("Missing data for NumberArray");
        }
        foreach ($data as $value) {
            if (!is_int($value)) {
                return new Validation("Invalid number format - '{$value}'");
            }
        }
        if ($this->options()) {
            foreach ($data as $value) {
                if (!array_key_exists($value, $this->options())) {
                    return new Validation("Out of options value - '{$value}'");
                }
            }
        }
        return new Validation();
    }

    public function equals(BaseParam $param): bool
    {
        if (!($param instanceof static)) {
            throw new \Exception("Cannot compare " . get_class($param) . ' with NumberArrayParam');
        }

        return $this->sortedNumbers() == $param->sortedNumbers();
    }
}
