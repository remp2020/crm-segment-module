<?php

namespace Crm\SegmentModule\Models;

use Crm\SegmentModule\Repositories\SegmentsRepository;
use Nette\Database\Table\ActiveRow;
use RuntimeException;

class SegmentQuery implements QueryInterface, SimulableQueryInterface
{
    /**
     * @param string     $query
     * @param string     $tableName
     * @param string     $fields
     * @param string     $pagerKey
     * @param array|null $nestedSegments Array of ActiveRows of all required nested segments, keyed by segment code.
     *                                   Required if segment query contains nested segments.
     *                                   Use helper function nestedSegments to retrieve this value.
     *
     * @internal use SegmentFactory instead of directly initializing SegmentQuery
     */
    public function __construct(
        private string $query,
        private string $tableName,
        private string $fields,
        private string $pagerKey = 'id',
        private ?array $nestedSegments = [],
    ) {
    }

    public function getCountQuery()
    {
        $key = $this->tableName . '.' . $this->pagerKey;
        return 'SELECT count(*) FROM (' . $this->buildQuery($key . ' AS _crm_pager_key') . ') AS a';
    }

    public function getIdsQuery()
    {
        return 'SELECT a._crm_id as id FROM (' . $this->buildQuery("{$this->tableName}.id AS _crm_id") . ') AS a';
    }

    public function getNextPageQuery($lastPagerId, $count)
    {
        $key = $this->tableName . '.' . $this->pagerKey;
        $query = $this->buildQuery('', $key . ' > ' . $lastPagerId) . ' ORDER BY ' . $key;
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

        $key = $this->tableName . '.' . $this->pagerKey;
        return 'SELECT count(*) FROM (' . $this->buildQuery($key) . ") AS a WHERE a.{$field} = {$value}";
    }

    public function getSimulationQuery(): string
    {
        return sprintf("EXPLAIN %s", $this->getQuery());
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
                $fieldsArr[] = $this->prefix($f);
                $groupByArr[] = trim(explode(' as ', mb_strtolower($f))[0]);
            }
        }
        if ($select) {
            foreach (explode(",", $select) as $f) {
                $selectArr[] = $this->prefix($f);
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
        $query = $this->replaceSegmentQueries($query);

        if (!$where) {
            $where = ' 1=1 ';
        }

        return str_replace('%where%', $where, $query);
    }

    public static function nestedSegmentReferences(
        SegmentsRepository $segmentsRepository,
        ActiveRow $segmentRow
    ): array {
        $codeToSearch = "%segment.{$segmentRow->code}%";
        return $segmentsRepository->getTable()
            ->where('query_string LIKE ?', $codeToSearch)
            ->fetchAll();
    }

    public static function nestedSegments(
        SegmentsRepository $segmentsRepository,
        SegmentConfig $segment
    ): array {
        preg_match_all('/%segment\.(.+?)%/', $segment->queryString, $matches);

        if (count($matches[0]) === 0) {
            return [];
        }

        $directlyNestedSegments = $segmentsRepository->all()
            ->where('code IN (?)', $matches[1])
            ->fetchAll();

        if (count($directlyNestedSegments) !== count($matches[1])) {
            $missingCodes = implode(',', array_diff(
                $matches[1],
                array_column($directlyNestedSegments, 'code')
            ));
            throw new RuntimeException("Unable to load segments with codes: [$missingCodes]");
        }

        $nestedSegments = [];

        foreach ($directlyNestedSegments as $row) {
            // add directly nested segments
            $nestedSegments[$row->code] = $row;

            // add recurrently nested segments
            $recurrentSegments = self::nestedSegments(
                $segmentsRepository,
                SegmentConfig::fromSegmentActiveRow($row),
            );

            foreach ($recurrentSegments as $recurrentSegmentRow) {
                $nestedSegments[$recurrentSegmentRow->code] = $recurrentSegmentRow;
            }
        }
        return $nestedSegments;
    }

    private function replaceSegmentQueries($query): string
    {
        $matches = [];
        preg_match_all('/%segment\.(.+?)%/', $query, $matches);

        if (!$this->nestedSegments && count($matches[0]) > 0) {
            throw new \RuntimeException("No nested segments are provided in constructor although they are required, since segment query references other segment");
        }

        foreach ($matches[0] as $i => $pattern) {
            $segmentCode = $matches[1][$i];
            if (!isset($this->nestedSegments[$segmentCode])) {
                throw new \RuntimeException("Missing segment code [$segmentCode] in provided nested segments. Have you called static method nestedSegments()?");
            }

            // Recursively replace pattern with nested segment query
            $nestedSegmentRow = $this->nestedSegments[$segmentCode];
            $nestedSegmentQuery = new SegmentQuery(
                query: $nestedSegmentRow->query_string,
                tableName: $nestedSegmentRow->table_name,
                fields: $nestedSegmentRow->fields,
                pagerKey: $this->pagerKey,
                nestedSegments: $this->nestedSegments
            );

            $query = str_replace($pattern, $nestedSegmentQuery->getQuery(), $query);
        }

        return $query;
    }

    private function prefix(string $column): string
    {
        if (!str_contains($column, '.')) {
            return $this->tableName . '.' . trim($column);
        }

        return trim($column);
    }
}
