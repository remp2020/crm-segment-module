<?php

namespace Crm\SegmentModule\Models;

use Nette\Database\Table\ActiveRow;

class SegmentConfig
{
    public static function fromSegmentActiveRow(ActiveRow $row): self
    {
        return new self(
            tableName: $row->table_name,
            queryString: $row->query_string,
            fields: $row->fields,
        );
    }

    public function __construct(
        public string $tableName,
        public string $queryString,
        public string $fields,
    ) {
    }
}
