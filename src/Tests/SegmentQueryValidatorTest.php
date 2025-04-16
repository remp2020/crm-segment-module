<?php

namespace Crm\SegmentModule\Tests;

use Crm\ApplicationModule\Tests\CrmTestCase;
use Crm\SegmentModule\Exceptions\SegmentQueryValidationException;
use Crm\SegmentModule\Models\SegmentQueryValidator;

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
        $sql = 'SELECT * FROM users';
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
}
