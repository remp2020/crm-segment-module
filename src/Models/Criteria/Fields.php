<?php

namespace Crm\SegmentModule\Criteria;

class Fields
{
    public static function formatSql(array $fields): string
    {
        if (empty($fields)) {
            return '1 AS 1';
        }
        return implode(', ', array_map(function ($key, $value) {
            return "$key AS $value";
        }, array_keys($fields), array_values($fields)));
    }
}
