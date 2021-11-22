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

    protected $slugs = [
        'code',
    ];

    protected $auditLogExcluded = [
        'cache_count',
        'updated_at',
        'cache_count_time',
    ];

    private const LOCK_WHITELIST = [
        'updated_at',
        'cache_count',
        'cache_count_time',
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
        //if segment is locked, allow to change only whitelisted fields (eg. field holding cached count)
        if ($row['locked']) {
            foreach ($data as $key => $value) {
                if (!in_array($key, self::LOCK_WHITELIST)) {
                    throw new \Exception("Trying to update locked segment [{$row['code']}].");
                }
            }
        }

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
        if (!$segment) {
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

    final public function setLock(ActiveRow $segment, bool $locked): ActiveRow
    {
        // reload; just to be sure we have current data
        $reloadedSegment = $this->findById($segment->id);

        // update only if segment's lock isn't same value as provided $locked
        // we don't want to spam audit log with useless queries (un/locking already locked segment)
        if ($reloadedSegment->locked !== $locked) {
            $data = [
                'updated_at' => new DateTime(),
                'locked' => $locked,
            ];
            // calling directly parent; repository's update disallows changes to locked segment
            parent::update($reloadedSegment, $data);
        }

        return $reloadedSegment;
    }
}
