<?php

use Phinx\Migration\AbstractMigration;

class AddCacheCountPeriodicityColumnToSegmentsTable extends AbstractMigration
{
    public function change()
    {
        $this->table('segments')
            ->addColumn('cache_count_periodicity', 'json', [
                'null' => true,
                'after' => 'cache_count',
            ])
            ->addColumn('cache_count_updated_at', 'datetime', [
                'null' => true,
                'after' => 'cache_count_periodicity',
            ])
            ->update();
    }
}
