<?php

namespace Crm\SegmentModule\Tests;

use Crm\ApplicationModule\Tests\CrmTestCase;
use Crm\SegmentModule\Exceptions\SegmentQueryValidationException;
use Crm\SegmentModule\Models\SegmentQueryValidator;
use PHPUnit\Framework\Attributes\DataProvider;

class SegmentQueryValidatorTest extends CrmTestCase
{
    private SegmentQueryValidator $segmentQueryValidator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->segmentQueryValidator = $this->inject(SegmentQueryValidator::class);
    }

    public function testValidSelectQueryPasses(): void
    {
        $sql = 'SELECT id FROM users';
        $this->segmentQueryValidator->validate($sql);
        $this->assertTrue(true);
    }

    public function testInsertThrowsException(): void
    {
        $this->expectException(SegmentQueryValidationException::class);
        $this->segmentQueryValidator->validate('INSERT INTO users (name) VALUES ("John")');
    }

    public function testUpdateThrowsException(): void
    {
        $this->expectException(SegmentQueryValidationException::class);
        $this->segmentQueryValidator->validate('UPDATE users SET name = "John" WHERE id = 1');
    }

    public function testDeleteThrowsException(): void
    {
        $this->expectException(SegmentQueryValidationException::class);
        $this->segmentQueryValidator->validate('DELETE FROM users WHERE id = 1');
    }

    public function testForbiddenTableThrowsExceptionOnFrom(): void
    {
        $this->segmentQueryValidator->addForbiddenTables('secret_table');
        $this->expectException(SegmentQueryValidationException::class);
        $this->segmentQueryValidator->validate('SELECT * FROM secret_table');
    }

    public function testForbiddenTableThrowsExceptionOnJoin(): void
    {
        $this->segmentQueryValidator->addForbiddenTables('forbidden_table');
        $this->expectException(SegmentQueryValidationException::class);
        $this->segmentQueryValidator->validate('SELECT * FROM users JOIN forbidden_table ON users.id = forbidden_table.user_id');
    }

    public function testMultipleForbiddenTables(): void
    {
        $this->segmentQueryValidator->addForbiddenTables('table1', 'table2');
        $this->expectException(SegmentQueryValidationException::class);
        $this->segmentQueryValidator->validate('SELECT * FROM table2');
    }

    public function testForbiddenTableCaseInsensitive(): void
    {
        $this->segmentQueryValidator->addForbiddenTables('SeCrEt_TaBlE');
        $this->expectException(SegmentQueryValidationException::class);
        $this->segmentQueryValidator->validate('select * from secret_table');
    }

    public function testTableNameQuoted(): void
    {
        $this->segmentQueryValidator->addForbiddenTables('forbidden_table');
        $this->expectException(SegmentQueryValidationException::class);
        $this->segmentQueryValidator->validate('SELECT * FROM `forbidden_table`');
    }

    public function testColumnSameAsForbiddenTableName(): void
    {
        $this->segmentQueryValidator->addForbiddenTables('forbidden_table');
        $this->segmentQueryValidator->validate('SELECT forbidden_table FROM users;');
        $this->assertTrue(true);
    }

    public static function validQueryStringDataProvider(): array
    {
        return [
            'explicit columns' => ['SELECT id, email FROM users'],
            'single column' => ['SELECT id FROM users'],
            'count star is allowed' => ['SELECT COUNT(*) FROM users'],
            'count star with other columns' => ['SELECT id, COUNT(*) FROM users GROUP BY id'],
            'aggregate functions' => ['SELECT MAX(id), MIN(id) FROM users'],
            'columns with where clause' => ['SELECT id FROM users WHERE active = 1'],
            'columns with join' => ['SELECT u.id, p.amount FROM users u JOIN payments p ON p.user_id = u.id'],
            'subquery with explicit columns' => ['SELECT id FROM users WHERE id IN (SELECT user_id FROM payments)'],
            'multiline explicit columns' => ["SELECT\n  id,\n  email\nFROM users"],
            'literal star in string' => ["SELECT id FROM users WHERE name = '*'"],
            'distinct with explicit column' => ['SELECT DISTINCT email FROM users'],
        ];
    }

    #[DataProvider('validQueryStringDataProvider')]
    public function testQueryStringPatternAcceptsValidQueries(string $sql): void
    {
        $this->segmentQueryValidator->checkForbiddenSelection($sql);
        $this->assertTrue(true);
    }

    public static function invalidQueryStringDataProvider(): array
    {
        return [
            'select all' => ['SELECT * FROM users'],
            'select all lowercase' => ['select * from users'],
            'select distinct all' => ['SELECT DISTINCT * FROM users'],
            'table dot star' => ['SELECT users.* FROM users'],
            'alias dot star' => ['SELECT u.* FROM users u'],
            'placeholder alias dot star' => ['SELECT %alias%.* FROM users'],
            'star with leading column' => ['SELECT id, * FROM users'],
            'star with trailing column' => ['SELECT *, id FROM users'],
            'star between columns' => ['SELECT id, *, email FROM users'],
            'select all with spaces' => ['SELECT    *    FROM users'],
            'select all multiline' => ["SELECT *\nFROM users"],
            'table dot star with join' => ['SELECT u.* FROM users u JOIN payments p ON p.user_id = u.id'],
        ];
    }

    #[DataProvider('invalidQueryStringDataProvider')]
    public function testQueryStringPatternRejectsInvalidQueries(string $sql): void
    {
        $this->expectException(SegmentQueryValidationException::class);
        $this->segmentQueryValidator->checkForbiddenSelection($sql);
    }

    public static function validQueryFieldsDataProvider(): array
    {
        return [
            'single field' => ['id'],
            'multiple fields' => ['id, email'],
            'qualified fields' => ['users.id, payments.amount'],
            'aliased field' => ['users.id AS user_id'],
            'count star' => ['COUNT(*)'],
            'count star with other field' => ['id, COUNT(*)'],
            'aggregate functions' => ['MAX(id), MIN(id)'],
            'multiline fields' => ["id,\n  email,\n  name"],
            'literal star in string' => ["id, '*' AS marker"],
            'expression with star inside parens' => ['SUM(amount * quantity)'],
        ];
    }

    #[DataProvider('validQueryFieldsDataProvider')]
    public function testQueryFieldsPatternAcceptsValidFields(string $fields): void
    {
        $pattern = SegmentQueryValidator::QUERY_FIELDS_VALIDATION_PATTERN;
        $result = preg_match("\x01^(?:$pattern)$\x01Dui", $fields);
        $this->assertSame(1, $result);
    }

    public static function invalidQueryFieldsDataProvider(): array
    {
        return [
            'bare star' => ['*'],
            'star with surrounding spaces' => ['  *  '],
            'table dot star' => ['users.*'],
            'alias dot star' => ['u.*'],
            'star with leading field' => ['id, *'],
            'star with trailing field' => ['*, id'],
            'star between fields' => ['id, *, email'],
            'table dot star with other fields' => ['id, users.*'],
            'multiline star' => ["id,\n  *,\n  email"],
        ];
    }

    #[DataProvider('invalidQueryFieldsDataProvider')]
    public function testQueryFieldsPatternRejectsInvalidFields(string $fields): void
    {
        $pattern = SegmentQueryValidator::QUERY_FIELDS_VALIDATION_PATTERN;
        $result = preg_match("\x01^(?:$pattern)$\x01Dui", $fields);
        $this->assertSame(0, $result);
    }
}
