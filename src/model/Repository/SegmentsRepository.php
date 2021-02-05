<?php

namespace Crm\SegmentModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use DateTime;
use Nette\Database\Context;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;

class SegmentsRepository extends Repository
{
    protected $tableName = 'segments';

    protected $auditLogExcluded = [
        'cache_count'
    ];

    public function __construct(
        Context $database,
        AuditLogRepository $auditLogRepository
    ) {
        parent::__construct($database);
        $this->auditLogRepository = $auditLogRepository;
    }

    final public function all()
    {
        return $this->getTable()->where('deleted_at IS NULL')->order('name ASC');
    }

    final public function deleted()
    {
        return $this->getTable()->where('deleted_at IS NOT NULL')->order('name ASC');
    }

    final public function add($name, $version, $code, $tableName, $fields, $queryString, IRow $group, $criteria = null)
    {
        $id = $this->insert([
            'name' => $name,
            'code' => $code,
            'version' => $version,
            'fields' => $fields,
            'query_string' => $queryString,
            'table_name' => $tableName,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
            'cache_count' => 0,
            'segment_group_id' => $group->id,
            'criteria' => $criteria,
        ]);
        return $this->find($id);
    }

    final public function update(IRow &$row, $data)
    {
        $data['updated_at'] = new DateTime();
        return parent::update($row, $data);
    }

    final public function upsert(
        string $code,
        string $name,
        string $queryString,
        string $tableName,
        string $fields,
        IRow $group
    ): ActiveRow {
        $segment = $this->findByCode($code);
        if ($segment === false) {
            return $this->add($name, 1, $code, $tableName, $fields, $queryString, $group);
        }

        $data = [];
        if ($segment->name !== $name) {
            $data['name'] = $name;
        }
        if ($segment->table_name !== $tableName) {
            $data['table_name'] = $tableName;
        }
        if ($segment->fields !== $fields) {
            $data['fields'] = $fields;
        }
        if ($segment->query_string !== $queryString) {
            $data['query_string'] = $queryString;
        }
        if ($segment->segment_group_id !== $group['id']) {
            $data['segment_group_id'] = $group['id'];
        }
        $this->update($segment, $data);

        return $segment;
    }

    final public function exists($code)
    {
        return $this->all()->where('code', $code)->count('*') > 0;
    }

    final public function findById($id)
    {
        return $this->all()->where('id', $id)->limit(1)->fetch();
    }

    final public function findByCode($code)
    {
        return $this->all()->where('code', $code)->limit(1)->fetch();
    }

    final public function softDelete(IRow $segment)
    {
        $this->update($segment, [
            'deleted_at' => new \DateTime(),
            'updated_at' => new \DateTime(),
        ]);
    }
}
