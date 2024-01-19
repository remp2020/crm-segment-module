<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApplicationModule\Models\Criteria\CriteriaStorage;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class CriteriaHandler extends ApiHandler
{
    private $criteriaStorage;

    public function __construct(CriteriaStorage $criteriaStorage)
    {
        $this->criteriaStorage = $criteriaStorage;
    }

    public function params(): array
    {
        return [];
    }

    public function handle(array $params): ResponseInterface
    {
        $criteriaArray = $this->criteriaStorage->getCriteria();
        $result = [];
        foreach ($criteriaArray as $table => $tableCriteria) {
            foreach ($tableCriteria as $key => $criteria) {
                $params = $criteria->params();
                $paramsArray = [];
                foreach ($params as $param) {
                    $paramsArray[$param->key()] = $param->blueprint();
                }

                $result[$table][] = [
                    'key' => $key,
                    'label' => $criteria->label(),
                    'params' => $paramsArray,
                    'fields' => array_values($criteria->fields()),
                ];
            }
        }

        $resultData = [];
        foreach ($result as $table => $criteria) {
            $resultData[] = [
                'table' => $table,
                'fields' => $this->criteriaStorage->getTableFields($table),
                'criteria' => $criteria,
            ];
        }

        $response = new JsonApiResponse(Response::S200_OK, ['blueprint' => $resultData]);

        return $response;
    }
}
