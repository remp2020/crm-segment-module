<?php

namespace Crm\SegmentModule;

class SegmentQuery implements QueryInterface
{
    private $query;

    private $tableName;

    private $pagerKey;

    private $fields;

    public function __construct($query, $tableName, $fields, $pagerKey = 'id')
    {
        $this->query = $query;
        $this->tableName = $tableName;
        $this->fields = $fields;
        $this->pagerKey = $this->tableName . '.' . $pagerKey;
    }

    public function getCountQuery()
    {
        return 'SELECT count(*) FROM (' . $this->buildQuery($this->pagerKey . ' AS _crm_pager_key') . ') AS a';
    }

    public function getNextPageQuery($lastPagerId, $count)
    {
        $query = $this->buildQuery('', $this->pagerKey . ' > ' . $lastPagerId) . ' ORDER BY ' . $this->pagerKey;
        if ($count > 0) {
            $query .= ' LIMIT ' . $count;
        }
        return $query;
    }

    public function getIsInQuery($field, $value)
    {
        if (!is_numeric($value)) {
            $value = "'{$value}'";
        }
        $query = 'SELECT count(*) FROM (' . $this->buildQuery($this->pagerKey) . ") AS a WHERE a.{$field} = {$value}";
        return $query;
    }

    public function getQuery()
    {
        return $this->buildQuery();
    }

    private function buildQuery($select = '', $where = '')
    {
        $query = $this->query;

        $fieldsArr = [];
        $selectArr = [];
        $groupByArr = [];

        if ($this->fields) {
            foreach (explode(",", $this->fields) as $f) {
                $fieldsArr[] = trim($f);
                $groupByArr[] = trim(explode(' as ', mb_strtolower($f))[0]);
            }
        }
        if ($select) {
            foreach (explode(",", $select) as $f) {
                $selectArr[] = trim($f);
                $groupByArr[] = trim(explode(' as ', mb_strtolower($f))[0]);
            }
        }

        $fields = implode(", ", array_unique(array_merge(
            $fieldsArr,
            $selectArr
        )));
        $groupBy = implode(', ', array_unique($groupByArr));

        $query = str_replace('%table%', $this->tableName, $query);
        $query = str_replace('%fields%', $fields, $query);
        $query = str_replace('%group_by%', $groupBy, $query);
        if (!$where) {
            $where = ' 1=1 ';
        }
        $query = str_replace('%where%', $where, $query);
        return $query;
    }
}
