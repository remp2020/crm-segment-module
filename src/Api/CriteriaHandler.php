<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Response\ApiResponseInterface;
use Crm\ApplicationModule\Criteria\CriteriaStorage;
use Nette\Http\Response;

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

    public function handle(array $params): ApiResponseInterface
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

        $response = new JsonResponse(['blueprint' => $resultData]);
        $response->setHttpCode(Response::S200_OK);

        return $response;
    }
}
