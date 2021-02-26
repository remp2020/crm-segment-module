<?php

use Phinx\Migration\AbstractMigration;

class AddLockedToSegments extends AbstractMigration
{
    public function change()
    {
        $this->table('segments')
            ->addColumn('locked', 'boolean', ['null' => false, 'default' => false, 'after' => 'code'])
            ->update();
    }
}
