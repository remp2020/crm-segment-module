<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApplicationModule\Models\Criteria\CriteriaStorage;
use Crm\SegmentModule\Models\Criteria\EmptyCriteriaException;
use Crm\SegmentModule\Models\Criteria\Generator;
use Crm\SegmentModule\Models\Criteria\InvalidCriteriaException;
use Crm\SegmentModule\Models\Segment;
use Crm\SegmentModule\Models\SegmentConfig;
use Crm\SegmentModule\Models\SegmentFactoryInterface;
use Nette\Application\LinkGenerator;
use Nette\Http\IResponse;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class ItemsHandler extends ApiHandler
{
    public function __construct(
        private Generator $generator,
        private SegmentFactoryInterface $segmentFactory,
        private CriteriaStorage $criteriaStorage,
        LinkGenerator $linkGenerator
    ) {
        parent::__construct();
        $this->linkGenerator = $linkGenerator;
    }

    public function handle(array $params): ResponseInterface
    {
        $request = $this->rawPayload();
        if (empty($request)) {
            return new JsonApiResponse(IResponse::S400_BadRequest, ['status' => 'error', 'message' => 'Empty request body, JSON expected']);
        }

        try {
            $params = Json::decode($request, forceArrays: true);
        } catch (JsonException $e) {
            return new JsonApiResponse(IResponse::S400_BadRequest, ['status' => 'error', 'message' => "Malformed JSON: " . $e->getMessage()]);
        }

        if (!isset($params['table_name'])) {
            return new JsonApiResponse(IResponse::S400_BadRequest, ['status' => 'error', 'message' => "param missing: table_name"]);
        }
        if (!isset($params['criteria'])) {
            return new JsonApiResponse(IResponse::S400_BadRequest, ['status' => 'error', 'message' => "param missing: criteria"]);
        }

        try {
            $queryString = $this->generator->process($params['table_name'], $params['criteria']);
        } catch (EmptyCriteriaException $emptyCriteriaException) {
            return new JsonApiResponse(IResponse::S400_BadRequest, ['status' => 'error', 'message' => $emptyCriteriaException->getMessage()]);
        } catch (InvalidCriteriaException $invalidCriteriaException) {
            return new JsonApiResponse(IResponse::S400_BadRequest, ['status' => 'error', 'message' => $invalidCriteriaException->getMessage()]);
        }

        $defaultTableFields = $this->criteriaStorage->getDefaultTableFields($params['table_name']);
        $tableFields = $this->criteriaStorage->getTableFields($params['table_name']);
        $allowedFields = array_merge($defaultTableFields, $tableFields);
        $fields = array_intersect($params['fields'] ?? $defaultTableFields, $allowedFields);

        $segment = $this->segmentFactory
            ->buildSegment(new SegmentConfig(
                tableName: $params['table_name'],
                queryString: $queryString,
                fields: implode(',', array_map(fn ($x) => $params['table_name'] . "." . $x, $fields)),
            ));

        $finalQuery = null;
        if ($segment instanceof Segment) {
            $finalQuery = $segment->query();
        }

        $data = [];
        $processCallback = function ($row) use (&$data, $defaultTableFields) {
            $item = [];
            foreach ($defaultTableFields as $field) {
                $item[$field] = $row[$field];
            }
            $data[] = $item;
        };

        if ($segment instanceof Segment) {
            $segment->process($processCallback, 0);
        } else {
            $segment->process($processCallback);
        }

        $toReturn = [
            'status' => 'ok',
            'data' => $data,
            'memory' => round(memory_get_usage() / 1024 / 1024, 2) . ' MiB',
        ];

        // TODO: this link should be provided by segment itself (or some dataprovider)?
        $itemPath = match ($params['table_name']) {
            'users' => 'Users:UsersAdmin:show',
            'subscriptions' => 'Subscriptions:SubscriptionsAdmin:show',
            'payments' => 'Payments:PaymentsAdmin:show',
            default => null,
        };
        if ($itemPath) {
            $toReturn['itemUrlTemplate'] = $this->linkGenerator->link($itemPath, ['id' => 'ITEM_ID']);
        }

        if ($finalQuery) {
            $toReturn['query'] = $finalQuery;
        }
        return new JsonApiResponse(IResponse::S200_OK, $toReturn);
    }
}
