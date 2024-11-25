<?php

namespace Crm\SegmentModule\Models;

use Crm\SegmentModule\Exceptions\SegmentQueryValidationException;

class SegmentQueryValidator
{
    /** See SegmentQueryValidatorTest for allowed and disallowed expressions */
    public const string QUERY_STRING_VALIDATION_PATTERN = '(?s)(?!.*[%\w]+\s*\.\s*\*)(?!.*(?:,|\bSELECT\b(?:\s+DISTINCT)?)\s*\*\s*(?:,|\bFROM\b)).+';

    /** See SegmentQueryValidatorTest for allowed and disallowed expressions */
    public const string QUERY_FIELDS_VALIDATION_PATTERN = '(?s)(?!.*\w+\s*\.\s*\*)(?!.*(?:^|,)\s*\*\s*(?:,|$)).+';

    private array $forbiddenTables = [];

    public function addForbiddenTables(string ...$tables): void
    {
        foreach ($tables as $table) {
            $this->forbiddenTables[] = $table;
        }
    }

    /**
     * @throws SegmentQueryValidationException
     */
    public function validate(string $sql): void
    {
        $this->checkForbiddenOperations($sql);
        $this->checkForbiddenTables($sql);
        $this->checkForbiddenSelection($sql);
    }

    /**
     * @throws SegmentQueryValidationException
     */
    private function checkForbiddenTables(string $sql): void
    {
        foreach ($this->forbiddenTables as $table) {
            $tableName = trim($table);

            if (empty($tableName)) {
                continue;
            }

            $pattern = '/\b(?:FROM|JOIN|UPDATE|INTO|DELETE\s+FROM)\s+[`"]?' . preg_quote($tableName, '/') . '[`"]?\b/mi';

            if (preg_match($pattern, $sql)) {
                throw new SegmentQueryValidationException("Query contains forbidden table: $tableName.");
            }
        }
    }

    /**
     * @throws SegmentQueryValidationException
     */
    private function checkForbiddenOperations(string $sql): void
    {
        $patterns = [
            'INSERT' => '/INSERT\s+INTO\s+[\w`"]+/mi',
            'UPDATE' => '/UPDATE\s+([\w`"]+)\s+SET/mi',
            'DELETE' => '/DELETE\s+FROM\s+([\w`"]+)/mi',
        ];

        foreach ($patterns as $operation => $pattern) {
            if (preg_match($pattern, $sql)) {
                throw new SegmentQueryValidationException("Query contains forbidden operation: $operation.");
            }
        }
    }

    /**
     * @throws SegmentQueryValidationException
     */
    public function checkForbiddenSelection(string $sql): void
    {
        $pattern = self::QUERY_STRING_VALIDATION_PATTERN;
        if (!preg_match("\x01^(?:$pattern)$\x01Dui", $sql)) {
            throw new SegmentQueryValidationException('Query contains forbidden select all columns pattern with asterisk wildcard (`*`)');
        }
    }
}
