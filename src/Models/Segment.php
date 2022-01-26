<?php

namespace Crm\SegmentModule;

use Closure;
use Nette\Database\Context;

class Segment implements SegmentInterface
{
    private $context;

    private $query;

    public function __construct(Context $context, QueryInterface $query)
    {
        $this->context = $context;
        $this->query = $query;
    }

    public function totalCount()
    {
        $countQuery = $this->query->getCountQuery();
        $result = $this->context->query($countQuery);
        $count = 0;
        foreach ($result as $row) {
            $count = intval($row['count(*)']);
            break;
        }
        return $count;
    }

    public function isIn($field, $value)
    {
        $isInQuery = $this->query->getIsInQuery($field, $value);
        $result = $this->context->query($isInQuery);
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
            $rows = $this->context->query($fetchQuery);
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
