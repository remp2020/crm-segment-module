<?php

namespace Crm\SegmentModule\Models;

use Closure;
use Nette\Database\DriverException;
use Nette\Database\Explorer;

class Segment implements SegmentInterface, SimulableSegmentInterface
{
    public function __construct(
        private Explorer $database,
        private QueryInterface $query,
    ) {
    }

    public function totalCount()
    {
        $countQuery = $this->query->getCountQuery();
        try {
            $result = $this->database->query($countQuery);
        } catch (DriverException $exception) {
            throw new SegmentException($exception->getMessage(), previous: $exception);
        }

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
        try {
            $result = $this->database->query($idsQuery);
        } catch (DriverException $exception) {
            throw new SegmentException($exception->getMessage(), previous: $exception);
        }

        $ids = [];
        foreach ($result as $row) {
            $ids[] = $row->id;
        };
        return $ids;
    }

    public function isIn($field, $value)
    {
        $isInQuery = $this->query->getIsInQuery($field, $value);
        try {
            $result = $this->database->query($isInQuery);
        } catch (DriverException $exception) {
            throw new SegmentException($exception->getMessage(), previous: $exception);
        }

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

    public function simulate(): void
    {
        if ($this->query instanceof SimulableQueryInterface) {
            $simulationQuery = $this->query->getSimulationQuery();
        } else {
            // Just a fallback to slower query
            $simulationQuery = $this->query->getCountQuery();
        }

        try {
            $this->database->query($simulationQuery);
        } catch (DriverException $exception) {
            throw new SegmentException($exception->getMessage(), previous: $exception);
        }
    }

    public function process(Closure $rowCallback, int $step = null)
    {
        if ($step === null) {
            $step = 1000;
        }
        $lastId = 0;
        while (true) {
            $fetchQuery = $this->query->getNextPageQuery($lastId, $step);

            try {
                $rows = $this->database->query($fetchQuery);
            } catch (DriverException $exception) {
                throw new SegmentException($exception->getMessage(), previous: $exception);
            }

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
