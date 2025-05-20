<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\SegmentModule\Exceptions\SegmentQueryValidationException;
use Crm\SegmentModule\Models\Criteria\EmptyCriteriaException;
use Crm\SegmentModule\Models\Criteria\Generator;
use Crm\SegmentModule\Models\Criteria\InvalidCriteriaException;
use Crm\SegmentModule\Models\Segment;
use Crm\SegmentModule\Models\SegmentConfig;
use Crm\SegmentModule\Models\SegmentFactory;
use Crm\SegmentModule\Models\SegmentQueryValidator;
use Crm\SegmentModule\Repositories\SegmentGroupsRepository;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Nette\Http\IResponse;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Nette\Utils\Strings;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class CreateOrUpdateSegmentHandler extends ApiHandler
{
    public function __construct(
        private SegmentsRepository $segmentsRepository,
        private SegmentGroupsRepository $segmentGroupsRepository,
        private Generator $generator,
        private SegmentQueryValidator $segmentQueryValidator,
        private SegmentFactory $segmentFactory,
    ) {
        parent::__construct();
    }

    public function params(): array
    {
        return [
            (new GetInputParam('id')),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $request = $this->rawPayload();
        if (empty($request)) {
            $response = new JsonApiResponse(IResponse::S400_BadRequest, ['status' => 'error', 'message' => 'Empty request body, JSON expected']);
            return $response;
        }
        try {
            $json = Json::decode($request, forceArrays: true);
        } catch (JsonException $e) {
            $response = new JsonApiResponse(IResponse::S400_BadRequest, ['status' => 'error', 'message' => "Malformed JSON: " . $e->getMessage()]);
            return $response;
        }
        if ($err = $this->hasError($json)) {
            $response = new JsonApiResponse(IResponse::S400_BadRequest, ['status' => 'error', 'message' => 'Invalid params: ' . $err]);
            return $response;
        }
        $params = $json + $params;

        $group = $this->segmentGroupsRepository->findByCode($params['group_code']);
        unset($params['group_code']);

        if (!$group) {
            $response = new JsonApiResponse(IResponse::S404_NotFound, ['status' => 'error', 'message' => 'Segment group not found']);
            return $response;
        }
        $params['segment_group_id'] = $group->id;

        try {
            $oldCriteria = $params['criteria'];
            $params['query_string'] = $this->generator->process($params['table_name'], $params['criteria']);
            $params['criteria'] = $oldCriteria;
            if (!isset($params['name'])) {
                $params['name'] = $this->generator->generateName($params['table_name'], $params['criteria']);
            }
            $fields = $this->generator->getFields($params['table_name'], $params['fields'], $params['criteria']['nodes']);
        } catch (EmptyCriteriaException $emptyCriteriaException) {
            return new JsonApiResponse(IResponse::S400_BadRequest, ['status' => 'error', 'message' => $emptyCriteriaException->getMessage()]);
        } catch (InvalidCriteriaException $invalidCriteriaException) {
            return new JsonApiResponse(IResponse::S400_BadRequest, ['status' => 'error', 'message' => $invalidCriteriaException->getMessage()]);
        }

        $params['fields'] = implode(',', $fields);
        $params['criteria'] = Json::encode($params['criteria']);

        try {
            $segmentConfig = new SegmentConfig(
                $params['table_name'],
                $params['query_string'],
                $params['fields'],
            );

            $segment = $this->segmentFactory->buildSegment($segmentConfig);
            $query = $segment instanceof Segment ? $segment->query() : $params['query_string'];
            $this->segmentQueryValidator->validate($query);
        } catch (SegmentQueryValidationException $exception) {
            return new JsonApiResponse(IResponse::S400_BadRequest, ['status' => 'error', 'message' => $exception->getMessage()]);
        }

        if (isset($params['id'])) {
            $segment = $this->segmentsRepository->findById($params['id']);
            if (!$segment) {
                $response = new JsonApiResponse(IResponse::S404_NotFound, ['status' => 'error', 'message' => 'Segment not found']);
                return $response;
            }
            $this->segmentsRepository->update($segment, $params);
        } else {
            $totalCount = $this->segmentsRepository->totalCount();
            if (isset($params['code'])) {
                $code = $params['code'];
            } else {
                $code = Strings::webalize($params['name']) . "_{$totalCount}";
            }

            if ($this->segmentsRepository->exists($code)) {
                $response = new JsonApiResponse(IResponse::S409_Conflict, ['status' => 'error', 'message' => "Segment with code '{$code}' already exists"]);
                return $response;
            }

            $segment = $this->segmentsRepository->add(
                $params['name'],
                2,
                $code,
                $params['table_name'],
                $params['fields'],
                $params['query_string'],
                $group,
                $params['criteria'],
            );
        }

        $response = new JsonApiResponse(IResponse::S200_OK, [
            'status' => 'ok',
            'id' => $segment->id,
            'code' => $segment->code,
        ]);
        return $response;
    }

    /**
     * hasError returns string error message if json is not valid or null otherwise.
     *
     * @param $json
     * @return null|string
     */
    private function hasError($json): ?string
    {
        if (!isset($json['table_name'])) {
            return "missing [table_name] property in JSON payload";
        }
        if (!isset($json['group_code'])) {
            return "missing [group_code] property in JSON payload";
        }
        if (!isset($json['criteria'])) {
            return "missing [criteria] property in JSON payload";
        }
        if (!isset($json['criteria']['version']) || $json['criteria']['version'] !== '1') {
            return "missing or invalid [criteria.version] property in JSON payload";
        }
        return null;
    }
}
