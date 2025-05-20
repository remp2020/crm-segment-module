<?php

namespace Crm\SegmentModule\Models\Criteria;

use Crm\ApplicationModule\Models\Criteria\CriteriaInterface;
use Crm\ApplicationModule\Models\Criteria\CriteriaStorage;
use Crm\SegmentModule\Models\Params\ParamsBag;
use Nette\Utils\Strings;

class Generator
{
    public function __construct(private CriteriaStorage $criteriaStorage)
    {
    }

    private function buildParamBag(CriteriaInterface $criteria, array $values): ParamsBag
    {
        $paramBag = new ParamsBag();
        $params = $criteria->params();

        foreach ($values as $key => $value) {
            foreach ($params as $param) {
                if ($param->key() == $key) {
                    $validation = $param->isValid($value);
                    if ($validation->ok()) {
                        $param->setData($value);
                        $paramBag->addParam($param);
                    } else {
                        throw new InvalidCriteriaException("Invalid configuration for '{$key}'. {$validation->error()}");
                    }
                }
            }
        }

        foreach ($params as $param) {
            if ($param->required() && !$param->hasData()) {
                throw new InvalidCriteriaException("Missing required field '{$param->key()}'");
            }
        }

        return $paramBag;
    }

    private function processNodes(array $tableCriteria, string $table, array $nodes, int $prefix)
    {
        foreach ($nodes as $i => $param) {
            if ($param['type'] === 'criteria') {
                if (!isset($param['key'])) {
                    throw new InvalidCriteriaException("Missing [key] property in one of the criteria");
                }
                if (!isset($tableCriteria[$param['key']])) {
                    throw new InvalidCriteriaException("Table [{$table}] does not recognize field [{$param['key']}]. Please check the criteria definition.");
                }
                /** @var CriteriaInterface $criteria */
                $criteria = $tableCriteria[$param['key']];
                $paramBag = $this->buildParamBag($criteria, $param['values']);
                $join = $criteria->join($paramBag);

                $fields = [];
                if (isset($param['fields'])) {
                    foreach ($param['fields'] as $field) {
                        $fields[] = "t{$prefix}.{$field} AS {$field}_{$prefix}";
                    }
                }

                $whereCondition = "IS NOT NULL";
                if (isset($param['negation']) && $param['negation'] === true) {
                    $whereCondition = "IS NULL";
                }

                $primaryField = $this->criteriaStorage->getPrimaryField($table);

                return [
                    'where' => "t{$prefix}." . $primaryField . " {$whereCondition}",
                    'join' => ["LEFT JOIN ({$join}) AS t{$prefix} ON t{$prefix}." . $primaryField . " = %table%." . $primaryField],
                    'fields' => $fields,
                ];
            } elseif ($param['type'] === 'operator') {
                $wheres = [];
                $joins = [];
                $fields = [];
                foreach ($param['nodes'] as $n) {
                    $output = $this->processNodes($tableCriteria, $table, [$n], ++$prefix);
                    $wheres[] = $output['where'];
                    $joins = array_merge($joins, $output['join']);
                    $fields = array_merge($fields, $output['fields']);
                }
                if (strtoupper($param['operator']) === 'AND') {
                    return [
                        'where' => count($wheres) ? '(' . implode(' AND ', $wheres) . ')' : '',
                        'join' => $joins,
                        'fields' => $fields,
                    ];
                } elseif (strtoupper($param['operator']) === 'OR') {
                    return [
                        'where' => count($wheres) ? '(' . implode(' OR ', $wheres) . ')' : '',
                        'join' => $joins,
                        'fields' => $fields,
                    ];
                }
            }
        }
        return [
            'where' => "",
            'join' => [],
            'fields' => [],
        ];
    }

    public function process(string $table, array $params): string
    {
        $tableCriteria = $this->criteriaStorage->getTableCriteria($table);
        if (empty($tableCriteria)) {
            throw new EmptyCriteriaException("Unknown table or empty criteria list for table '{$table}'");
        }

        $output = $this->processNodes($tableCriteria, $table, $params['nodes'], 0);

        $join = '';
        if (count($output['join'])) {
            $join = implode("\n", $output['join']);
        }

        $where = $output['where'];
        if ($where) {
            $where = ' AND ' . $where;
        }
        $blueprint =
            "SELECT %fields%\n" .
            "FROM %table%\n" . $join . "\n" .
            "WHERE %where%\n" . $where . "\n" .
            "GROUP BY %group_by%";
        return $blueprint;
    }

    public function getFields(string $table, array $userFields, array $nodes): array
    {
        $tableCriteria = $this->criteriaStorage->getTableCriteria($table);
        if (empty($tableCriteria)) {
            throw new EmptyCriteriaException("Unknown table or empty criteria list for table '{$table}'");
        }

        $defaultFields = $this->criteriaStorage->getDefaultTableFields($table);
        $tableFields = $this->criteriaStorage->getTableFields($table);

        // validate userFields against available tableFields; select only available
        $fields = array_unique(array_merge($defaultFields, array_intersect($tableFields, $userFields)));

        // prefix
        $prefixedFields = [];
        foreach ($fields as $field) {
            $prefixedFields[] = "{$table}.{$field}";
        }

        $output = $this->processNodes($tableCriteria, $table, $nodes, 0);

        // Intentionally leaving out remaining $fields as they were unregistered from table fields and shouldn't
        // be returned anymore.

        return array_merge(
            $prefixedFields,
            $output['fields'],
        );
    }

    public function generateName(string $table, array $params)
    {
        $tableCriteria = $this->criteriaStorage->getTableCriteria($table);
        if (empty($tableCriteria)) {
            throw new EmptyCriteriaException("Unknown table or empty criteria list for table '{$table}'");
        }

        $title = $this->extractTitle($tableCriteria, $table, $params['nodes']);
        $title = str_replace('  ', ' ', implode(' and ', $title));
        return Strings::firstUpper($table) . $title;
    }

    protected function extractTitle($tableCriteria, $table, $params)
    {
        $title = [];
        foreach ($params as $param) {
            if ($param['type'] == 'operator') {
                foreach ($param['nodes'] as $node) {
                    $title = array_merge($title, $this->extractTitle($tableCriteria, $table, [$node]));
                }
            } elseif ($param['type'] == 'criteria') {
                $criteria = $tableCriteria[$param['key']];
                $paramBag = $this->buildParamBag($criteria, $param['values']);
                $title[] = $criteria->title($paramBag);
            }
        }
        return $title;
    }


    public function extractCriteria($table, array $params)
    {
        $tableCriteria = $this->criteriaStorage->getTableCriteria($table);
        $allBags = $this->extractInnerCriteria($tableCriteria, $table, $params['nodes']);
        $result = [];
        foreach ($allBags as $bag) {
            foreach ($bag->params() as $key => $param) {
                $result[] = [
                    'key' => $key,
                    'param' => $param,
                ];
            }
        }
        return $result;
    }

    private function extractInnerCriteria($tableCriteria, $table, array $nodes): array
    {
        $output = [];
        foreach ($nodes as $param) {
            if ($param['type'] == 'criteria') {
                if (!isset($param['key'])) {
                    throw new InvalidCriteriaException("Missing [key] property in one of the criteria");
                }
                if (!isset($tableCriteria[$param['key']])) {
                    throw new InvalidCriteriaException("Table [{$table}] does not recognize field [{$param['key']}]. Please check the criteria definition.");
                }
                $criteria = $tableCriteria[$param['key']];
                return [$this->buildParamBag($criteria, $param['values'])];
            } elseif ($param['type'] == 'operator') {
                foreach ($param['nodes'] as $n) {
                    $output = array_merge($output, $this->extractInnerCriteria($tableCriteria, $table, [$n]));
                }
            }
        }
        return $output;
    }
}
