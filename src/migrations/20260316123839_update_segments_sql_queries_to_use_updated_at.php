<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UpdateSegmentsSqlQueriesToUseUpdatedAt extends AbstractMigration
{
    public function up(): void
    {
        if ($this->hasTable('segments')) {
            $this->execute("
                UPDATE segments 
                SET 
                    query_string = REPLACE(query_string, 'modified_at', 'updated_at'),
                    fields = REPLACE(fields, 'modified_at', 'updated_at')
                WHERE query_string LIKE '%modified_at%' OR fields LIKE '%modified_at%'
            ");
        }
    }

    public function down(): void
    {
        $this->output->writeln('This is data migration. Down migration is not available.');
    }
}
