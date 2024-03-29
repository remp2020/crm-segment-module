<?php

namespace Crm\SegmentModule\Models;

use Closure;
use Nette\Database\Explorer;

class Segment implements SegmentInterface
{
    public function __construct(
        private Explorer $database,
        private QueryInterface $query
    ) {
    }

    public function totalCount()
    {
        $countQuery = $this->query->getCountQuery();
        $result = $this->database->query($countQuery);
        $count = 0;
        foreach ($result as $row) {
            $count = intval($row['count(*)']);
            break;
        }
        return $count;
    }

    public function getIds()
    {
        $idsQuery = $this->query->getIdsQuery();
        $ids = [];
        foreach ($this->database->query($idsQuery) as $row) {
            $ids[] = $row->id;
        };
        return $ids;
    }

    public function isIn($field, $value)
    {
        $isInQuery = $this->query->getIsInQuery($field, $value);
        $result = $this->database->query($isInQuery);
        $isIn = false;
        foreach ($result as $row) {
            if (intval($row['count(*)']) > 0) {
                $isIn = true;
            }
            break;
        }
        return $isIn;
    }

    public function query()
    {
        return $this->query->getQuery();
    }

    public function process(Closure $rowCallback, int $step = null)
    {
        if ($step === null) {
            $step = 1000;
        }
        $lastId = 0;
        while (true) {
            $fetchQuery = $this->query->getNextPageQuery($lastId, $step);
            $rows = $this->database->query($fetchQuery);
            $fetchedRows = 0;
            foreach ($rows as $row) {
                $rowCallback($row);
                $fetchedRows++;
                $lastId = $row->id;
            }
            if ($step === 0) {
                break;
            }
            if ($fetchedRows < $step) {
                break;
            }
        }
    }
}
