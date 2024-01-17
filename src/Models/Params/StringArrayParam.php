<?php

namespace Crm\SegmentModule\Models\Params;

class StringArrayParam extends BaseParam
{
    protected $type = 'string_array';

    private $options;

    public function __construct(string $key, string $label, string $help, bool $required = false, $default = null, string $group = null, ?array $options = null)
    {
        parent::__construct($key, $label, $help, $required, $default, $group);
        $this->options = $options;
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

    public function sortedStrings(): array
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
                $values[] = "'" . addslashes($value) . "'";
            } else {
                if (in_array($value, $this->options, true)) {
                    $values[] = "'$value'";
                }
            }
        }
        return implode($glue, $values);
    }

    public function isValid($data): Validation
    {
        if (!is_array($data)) {
            return new Validation("Missing data for StringArray");
        }

        if ($this->options()) {
            foreach ($data as $value) {
                if (!in_array($value, $this->options(), true)) {
                    return new Validation("Out of options value - '{$value}'");
                }
            }
        }

        return new Validation();
    }

    public function equals(BaseParam $param): bool
    {
        if (!($param instanceof static)) {
            throw new \Exception("Cannot compare " . get_class($param) . ' with BooleanParam');
        }
        return $param->sortedStrings() == $this->sortedStrings();
    }
}
