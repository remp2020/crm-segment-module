<?php

namespace Crm\SegmentModule\Params;

use Nette\Utils\DateTime;

class DateTimeParam extends BaseParam
{
    protected $type = 'datetime';

    const TYPE_ABSOLUTE = 'absolute';
    const TYPE_INTERVAL = 'interval';

    public function escapedConditions(string $key1, string $key2 = null): array
    {
        $key1 = $this->escapeKey($key1);
        if ($key2) {
            $key2 = $this->escapeKey($key2);
        }

        if ($this->data['type'] == self::TYPE_ABSOLUTE) {
            return $this->absoluteWhere($key1, $key2);
        }

        if ($this->data['type'] == self::TYPE_INTERVAL) {
            return $this->intervalWhere($key1, $key2);
        }

        return [];
    }

    private function absoluteWhere(string $key1, string $key2 = null): array
    {
        $where = [];
        foreach ($this->data[self::TYPE_ABSOLUTE] as $operator => $datetime) {
            $date = DateTime::from(strtotime($datetime));
            $formatted = $date->format('Y-m-d H:i:s');
            if ($key2) {
                $where[] = $this->formatDoubleOperator($operator, "'{$formatted}'", $key1, $key2);
            } else {
                $where[] = $this->formatOperator($operator, "'{$formatted}'", $key1);
            }
        }
        return $where;
    }

    private function formatOperator(string $operator, string $value, string $key1, $key2 = null): string
    {
        if ($operator == 'gt') {
            return " " . ($key2 ?? $key1) . " > {$value} ";
        } elseif ($operator == 'gte') {
            return " " . ($key2 ?? $key1) . " >= {$value} ";
        } elseif ($operator == 'lt') {
            return " {$key1} < {$value} ";
        } elseif ($operator == 'lte') {
            return " {$key1} <= {$value} ";
        } elseif ($operator == 'eq') {
            if ($key2) {
                return " {$key1} < {$value} AND {$key2} > {$value} ";
            } else {
                return " {$key1} > $value ";
            }
        }
        return '';
    }

    private function formatDoubleOperator(string $operator, string $value, string $key1, string $key2): string
    {
        if ($operator == 'gt') {
            return " {$key2} > {$value}";
        } elseif ($operator == 'gte') {
            return " {$key2} >= {$value}";
        } elseif ($operator == 'lt') {
            return " {$key1} < {$value}";
        } elseif ($operator == 'lte') {
            return " {$key1} <= {$value}";
        } elseif ($operator == 'eq') {
            return " {$key1} <= {$value} AND {$key2} >= {$value} ";
        }
        return '';
    }

    public function intervalWhere(string $key1, string $key2 = null): array
    {
        $where = [];
        foreach ($this->data[self::TYPE_INTERVAL] as $operator => $interval) {
            $unit = strtoupper($interval['unit']);

            if ($unit == 'NOW') {
                $expression = 'NOW()';
            } else {
                $value = intval($interval['value']);
                if ($value >= 0) {
                    $intervalOperator = "+";
                } else {
                    $intervalOperator = "-";
                    $value = -$value;
                }
                $expression = " NOW() {$intervalOperator} INTERVAL {$value} {$unit}";
            }

            $where[] = $this->formatOperator($operator, $expression, $key1, $key2);
        }

        return $where;
    }

    public function isValid($data): Validation
    {
        if (!isset($data['type'])) {
            return new Validation("You have to set 'type' for datetime param");
        }
        if ($data['type'] == self::TYPE_ABSOLUTE) {
            if (!isset($data[self::TYPE_ABSOLUTE])) {
                return new Validation("You have to set 'absolute' values for absolute type in datetime param");
            }
            return $this->validAbsolute($data[self::TYPE_ABSOLUTE]);
        }
        if ($data['type'] == self::TYPE_INTERVAL) {
            if (!isset($data[self::TYPE_INTERVAL])) {
                return new Validation("You have to set 'interval' values for interval type in datetime param");
            }
            return $this->validInterval($data[self::TYPE_INTERVAL]);
        }

        return new Validation();
    }

    /**
     * Inverse is meant to be used in combination with generated NOT IN () queries.
     * It inverses time conditions in a way that it includes complement of date range
     * requested in params data.
     *
     * @return DateTimeParam
     * @throws \Crm\SegmentModule\Criteria\InvalidCriteriaException
     */
    public function inverse(): DateTimeParam
    {
        $inversed = (clone $this);

        switch ($this->data['type']) {
            case self::TYPE_ABSOLUTE:
            case self::TYPE_INTERVAL:
                $data = [
                    'type' => $this->data['type'],
                    $this->data['type'] => [],
                ];
                foreach ($inversed->data[$this->data['type']] as $key => $_) {
                    if ($key === "gt") {
                        $key = "lte";
                    } elseif ($key === "gte") {
                        $key = "lt";
                    } elseif ($key === "lt") {
                        $key = "gte";
                    } elseif ($key === "lte") {
                        $key = "gt";
                    }
                    $data[$this->data['type']][$key] = $_;
                }
                break;
            default:
                throw new \Exception('unhandled DateTimeParam type when calling inverse() method');
        }

        $inversed->setData($data);
        return $inversed;
    }

    private function validAbsolute($data): Validation
    {
        if (!is_array($data)) {
            return new Validation("Missing array data in absolute key for datetime param");
        }
        foreach ($data as $key => $value) {
            if (!in_array($key, ['eq', 'gt', 'gte', 'lt', 'lte'])) {
                return new Validation("Unknown operator '{$key}'");
            }
            if (!$this->validDateFormat($value)) {
                return new Validation("Invalid date format: '{$value}'");
            }
        }
        return new Validation();
    }

    private function validInterval($data): Validation
    {
        if (!is_array($data) || empty($data)) {
            return new Validation("Missing interval definition for datetime param");
        }
        foreach ($data as $key => $value) {
            if (!in_array($key, ['eq', 'gt', 'gte', 'lt', 'lte'])) {
                return new Validation("Unknown operator '{$key}'");
            }
            if (!is_array($value)) {
                return new Validation("Invalid structure for interval datetime. You have to specify 'unit' and 'value'");
            }
            if (!isset($value['unit'])) {
                return new Validation("Invalid structure for interval datetime. Missing 'unit'");
            }
            if (!in_array($value['unit'], ['now', 'hour', 'day', 'month'])) {
                return new Validation("Invalid structure for interval datetime. Invalid 'unit'");
            }
            if ($value['unit'] != 'now') {
                if (!isset($value['value'])) {
                    return new Validation("Invalid structure for interval datetime. Missing 'value'");
                }
                if (!is_int($value['value'])) {
                    return new Validation("Invalid structure for interval datetime. Wrong type for 'value'");
                }
            }
        }
        return new Validation();
    }

    public function equals(BaseParam $param): bool
    {
        if (!($param instanceof static)) {
            throw new \Exception("Cannot compare " . get_class($param) . ' with DateTimeParam');
        }

        return $this->escapedConditions('test') == $param->escapedConditions('test');
    }

    public function title(string $key1, string $key2 = null): string
    {
        if ($this->data['type'] == self::TYPE_ABSOLUTE) {
            return $this->absoluteTitle($key1, $key2);
        }

        if ($this->data['type'] == self::TYPE_INTERVAL) {
            return $this->intervalTitle($key1, $key2);
        }

        return '';
    }

    private function absoluteTitle(string $key1, string $key2 = null): string
    {
        $title = '';
        foreach ($this->data[self::TYPE_ABSOLUTE] as $operator => $datetime) {
            $date = DateTime::from(strtotime($datetime));
            $formatted = $date->format('d.m.Y');
            $title .= $this->formatOperatorTitle($operator, "'{$formatted}'", $key1, $key2);
        }
        return $title;
    }

    public function intervalTitle(string $key, string $key2 = null): string
    {
        $title = '';
        foreach ($this->data[self::TYPE_INTERVAL] as $operator => $interval) {
            $unit = $interval['unit'];

            if ($unit == 'NOW') {
                $value = 'now';
            } else {
                $value = intval($interval['value']);
                if ($value > 0) {
                    $intervalOperator = "+";
                } else {
                    $intervalOperator = "-";
                    $value = -$value;
                }
                if ($value > 1 || $value < -1) {
                    $unit = $unit . 's';
                }

                $value = "{$intervalOperator}{$value} {$unit}";
            }

            $title .= $this->formatOperatorTitle($operator, $value, $key, $key2);
        }

        return $title;
    }

    private function formatOperatorTitle(string $operator, string $value, string $key1, $key2 = null): string
    {
        if ($operator == 'gt') {
            return " after {$value}";
        } elseif ($operator == 'gte') {
            return " after {$value} (inclusive)";
        } elseif ($operator == 'lt') {
            return " before {$value}";
        } elseif ($operator == 'lte') {
            return " before {$value} (inclusive)";
        } elseif ($operator == 'eq') {
            if ($key2) {
                return " valid at {$value}";
            } else {
                return " equal {$value}";
            }
        }
        return '';
    }
}
