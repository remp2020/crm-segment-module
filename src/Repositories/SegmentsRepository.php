<?php

namespace Crm\SegmentModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Repositories\AuditLogRepository;
use Crm\SegmentModule\Events\BeforeSegmentCodeUpdateEvent;
use DateTime;
use League\Event\Emitter;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\UniqueConstraintViolationException;
use Nette\Utils\Random;

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
        'cache_count_updated_at',
    ];

    private const LOCK_WHITELIST = [
        'updated_at',
        'cache_count',
        'cache_count_time',
        'cache_count_periodicity',
        'cache_count_updated_at',
    ];

    public function __construct(
        Explorer $database,
        AuditLogRepository $auditLogRepository,
        private Emitter $emitter
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

    /**
     * @throws SegmentAlreadyExistsException
     */
    final public function add($name, $version, $code, $tableName, $fields, $queryString, ActiveRow $group, $criteria = null, $note = null)
    {
        try {
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
                'note' => $note
            ]);
            return $this->find($id);
        } catch (UniqueConstraintViolationException $uniqueConstraintViolationException) {
            throw new SegmentAlreadyExistsException('Segment already exists: '. $code);
        }
    }

    /**
     * @param ActiveRow $row
     * @param $data
     *
     * @return bool
     * @throws SegmentCodeInUseException|\Exception
     */
    final public function update(ActiveRow &$row, $data)
    {
        //if segment is locked, allow to change only whitelisted fields (eg. field holding cached count)
        if ($row['locked']) {
            foreach ($data as $key => $value) {
                if (!in_array($key, self::LOCK_WHITELIST, true)) {
                    throw new \Exception("Trying to update locked segment [{$row['code']}].");
                }
            }
        }

        if (isset($data['code']) && $row->code !== $data['code']) {
            $this->emitter->emit(new BeforeSegmentCodeUpdateEvent($row));
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
        ActiveRow $group
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

    final public function softDelete(ActiveRow $segment)
    {
        $this->update($segment, [
            'deleted_at' => new \DateTime(),
            'updated_at' => new \DateTime(),
            'code' => $segment->code . '_deleted_' . Random::generate(),
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
