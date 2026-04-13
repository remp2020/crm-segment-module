<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddCopyFromToSegments extends AbstractMigration
{
    public function up(): void
    {
        $this->table('segments')
            ->addColumn('copied_from_segment_id', 'integer', ['null' => true])
            ->addForeignKey('copied_from_segment_id', 'segments')
            ->update();
    }

    public function down(): void
    {
        $this->table('segments')
            ->dropForeignKey('copied_from_segment_id')
            ->removeColumn('copied_from_segment_id')
            ->update();
    }
}
