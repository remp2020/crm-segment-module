<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\SegmentModule\Criteria\EmptyCriteriaException;
use Crm\SegmentModule\Criteria\Generator;
use Crm\SegmentModule\Criteria\InvalidCriteriaException;
use Crm\SegmentModule\Segment;
use Crm\SegmentModule\SegmentQuery;
use Nette\Database\Explorer;
use Nette\Http\Response;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

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

    public function handle(array $params): ResponseInterface
    {
        $request = file_get_contents("php://input");
        if (empty($request)) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => 'Empty request body, JSON expected']);
            return $response;
        }

        try {
            $params = Json::decode($request, Json::FORCE_ARRAY);
        } catch (JsonException $e) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => "Malformed JSON: " . $e->getMessage()]);
            return $response;
        }

        if (!isset($params['table_name'])) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => "param missing: table_name"]);
            return $response;
        }
        if (!isset($params['criteria'])) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => "param missing: criteria"]);
            return $response;
        }

        try {
            $queryString = $this->generator->process($params['table_name'], $params['criteria']);
        } catch (EmptyCriteriaException $emptyCriteriaException) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => $emptyCriteriaException->getMessage()]);
            return $response;
        } catch (InvalidCriteriaException $invalidCriteriaException) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => $invalidCriteriaException->getMessage()]);
            return $response;
        }

        $query = new SegmentQuery($queryString, $params['table_name'], $params['table_name'] . '.id');
        $segment = new Segment($this->database, $query);
        $count = $segment->totalCount();

        $response = new JsonApiResponse(Response::S200_OK, ['status' => 'ok', 'count' => $count]);

        return $response;
    }
}
