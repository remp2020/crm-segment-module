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
        return 'SELECT count(*) FROM (' . $this->buildQuery($this->pagerKey) . ') AS a';
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
        $fields = $this->fields;

        if ($select) {
            $fieldsArr = [];
            foreach (explode(",", $this->fields) as $f) {
                $fieldsArr[] = trim($f);
            }
            $selectArr = [];
            foreach (explode(",", $select) as $f) {
                $selectArr[] = trim($f);
            }
            $fields = implode(",", array_unique(array_merge(
                $fieldsArr,
                $selectArr
            )));
        }

        $query = str_replace('%table%', $this->tableName, $query);
        $query = str_replace('%fields%', $fields, $query);
        if (!$where) {
            $where = ' 1=1 ';
        }
        $query = str_replace('%where%', $where, $query);
        return $query;
    }
}
