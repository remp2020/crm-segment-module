<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\SegmentModule\Criteria\EmptyCriteriaException;
use Crm\SegmentModule\Criteria\Generator;
use Crm\SegmentModule\Criteria\InvalidCriteriaException;
use Crm\SegmentModule\Segment;
use Crm\SegmentModule\SegmentQuery;
use Nette\Database\Explorer;
use Nette\Http\Response;
use Nette\Utils\Json;
use Nette\Utils\JsonException;

class CountsHandler extends ApiHandler
{
    private $generator;

    private $database;

    public function __construct(
        Explorer  $database,
        Generator $generator
    ) {
        $this->database = $database;
        $this->generator = $generator;
    }

    public function params(): array
    {
        return [];
    }

    public function handle(ApiAuthorizationInterface $authorization)
    {
        $request = file_get_contents("php://input");
        if (empty($request)) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Empty request body, JSON expected']);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        try {
            $params = Json::decode($request, Json::FORCE_ARRAY);
        } catch (JsonException $e) {
            $response = new JsonResponse(['status' => 'error', 'message' => "Malformed JSON: " . $e->getMessage()]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        if (!isset($params['table_name'])) {
            $response = new JsonResponse(['status' => 'error', 'message' => "param missing: table_name"]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }
        if (!isset($params['criteria'])) {
            $response = new JsonResponse(['status' => 'error', 'message' => "param missing: criteria"]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        try {
            $queryString = $this->generator->process($params['table_name'], $params['criteria']);
        } catch (EmptyCriteriaException $emptyCriteriaException) {
            $response = new JsonResponse(['status' => 'error', 'message' => $emptyCriteriaException->getMessage()]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        } catch (InvalidCriteriaException $invalidCriteriaException) {
            $response = new JsonResponse(['status' => 'error', 'message' => $invalidCriteriaException->getMessage()]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        $query = new SegmentQuery($queryString, $params['table_name'], $params['table_name'] . '.id');
        $segment = new Segment($this->database, $query);
        $count = $segment->totalCount();

        $response = new JsonResponse(['status' => 'ok', 'count' => $count]);
        $response->setHttpCode(Response::S200_OK);

        return $response;
    }
}
