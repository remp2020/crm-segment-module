<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\SegmentModule\Models\Criteria\EmptyCriteriaException;
use Crm\SegmentModule\Models\Criteria\Generator;
use Crm\SegmentModule\Models\Criteria\InvalidCriteriaException;
use Crm\SegmentModule\Models\Segment;
use Crm\SegmentModule\Models\SegmentConfig;
use Crm\SegmentModule\Models\SegmentFactoryInterface;
use Nette\Http\Response;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class CountsHandler extends ApiHandler
{
    public function __construct(
        private Generator $generator,
        private SegmentFactoryInterface $segmentFactory,
    ) {
        parent::__construct();
    }

    public function params(): array
    {
        return [];
    }

    public function handle(array $params): ResponseInterface
    {
        $request = $this->rawPayload();
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

        $segment = $this->segmentFactory
            ->buildSegment(new SegmentConfig(
                tableName: $params['table_name'],
                queryString: $queryString,
                fields: $params['table_name'] . '.id',
            ));

        $finalQuery = null;
        if ($segment instanceof Segment) {
            $finalQuery = $segment->query();
        }
        $count = $segment->totalCount();

        $toReturn = [
            'status' => 'ok',
            'count' => $count,
        ];
        if ($finalQuery) {
            $toReturn['query'] = $finalQuery;
        }
        return new JsonApiResponse(Response::S200_OK, $toReturn);
    }
}
