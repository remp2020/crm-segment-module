<?php
declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class ChangeQueryStringToMediumtext extends AbstractMigration
{
    public function up(): void
    {
        $this->table('segments')
            ->changeColumn('query_string', 'text', [
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'null' => true,
            ])
            ->update();
    }

    public function down(): void
    {
        $this->table('segments')
            ->changeColumn('query_string', 'text', [
                'limit' => MysqlAdapter::TEXT_REGULAR,
                'null' => true,
            ])
            ->update();
    }
}
