<?php

use Nette\Utils\DateTime;
use Nette\Utils\Strings;
use Phinx\Migration\AbstractMigration;

class AddCodeToSegmentGroups extends AbstractMigration
{
    public function up()
    {
        $this->table('segment_groups')
            ->addColumn('code', 'string', ['null' => true, 'after' => 'name'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'after' => 'created_at'])
            ->addIndex(['code'], ['unique' => true])
            ->update();

        // fill new columns with values
        $updateAt = new DateTime();
        $updateSQL = <<<SQL
            UPDATE `segment_groups`
            SET
                `code` = '%s',
                `updated_at` = '{$updateAt}'
            WHERE `id` = %d
SQL;
        foreach ($this->fetchAll('SELECT * FROM `segment_groups`') as $row) {
            $code = Strings::webalize($row['name']);
            $this->execute(sprintf($updateSQL, $code, $row['id']));
        }

        // set both columns to not null
        $this->table('segment_groups')
            ->changeColumn('code', 'string', ['null' => false])
            ->changeColumn('updated_at', 'datetime', ['null' => false])
            ->update();
    }

    public function down()
    {
        $this->table('segment_groups')
            ->removeColumn('code')
            ->removeColumn('updated_at')
            ->update();
    }
}
