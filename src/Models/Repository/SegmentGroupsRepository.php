<?php

namespace Crm\SegmentModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class SegmentGroupsRepository extends Repository
{
    protected $tableName = 'segment_groups';

    protected $slugs = [
        'code',
    ];

    final public function all()
    {
        return $this->getTable()->order('sorting ASC');
    }

    final public function add(string $name, string $code, ?int $sorting = 100)
    {
        $now = new DateTime();

        return $this->insert([
            'name' => $name,
            'code' => $code,
            'sorting' => $sorting,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    final public function update(ActiveRow &$row, $data)
    {
        $data['updated_at'] = new DateTime();
        return parent::update($row, $data);
    }

    final public function findByCode(string $code): ?ActiveRow
    {
        $segmentGroup = $this->getTable()->where(['code' => $code])->fetch();
        if (!$segmentGroup) {
            return null;
        }
        return $segmentGroup;
    }
}
