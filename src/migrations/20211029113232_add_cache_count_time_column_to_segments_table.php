<?php

use Phinx\Migration\AbstractMigration;

class AddCacheCountTimeColumnToSegmentsTable extends AbstractMigration
{
    public function change()
    {
        $this->table('segments')
            ->addColumn('cache_count_time', 'float', [
                'null' => true,
                'after' => 'cache_count'
            ])
            ->update();
    }
}
