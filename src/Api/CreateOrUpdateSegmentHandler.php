<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\SegmentModule\Criteria\EmptyCriteriaException;
use Crm\SegmentModule\Criteria\Generator;
use Crm\SegmentModule\Criteria\InvalidCriteriaException;
use Crm\SegmentModule\Repository\SegmentGroupsRepository;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Nette\Http\Response;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Nette\Utils\Strings;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class CreateOrUpdateSegmentHandler extends ApiHandler
{
    private $segmentsRepository;

    private $segmentGroupsRepository;

    private $generator;

    public function __construct(
        SegmentsRepository $segmentsRepository,
        SegmentGroupsRepository $segmentGroupsRepository,
        Generator $generator
    ) {
        $this->segmentsRepository = $segmentsRepository;
        $this->segmentGroupsRepository = $segmentGroupsRepository;
        $this->generator = $generator;
    }

    public function params(): array
    {
        return [
            new InputParam(InputParam::TYPE_GET, 'id', InputParam::OPTIONAL),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $request = file_get_contents("php://input");
        if (empty($request)) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => 'Empty request body, JSON expected']);
            return $response;
        }
        try {
            $json = Json::decode($request, Json::FORCE_ARRAY);
        } catch (JsonException $e) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => "Malformed JSON: " . $e->getMessage()]);
            return $response;
        }

        $paramsProcessor = new ParamsProcessor($this->params());
        if ($paramsProcessor->hasError()) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => 'Invalid params']);
            return $response;
        }
        if ($err = $this->hasError($json)) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => 'Invalid params: ' . $err]);
            return $response;
        }
        $params = $json + $paramsProcessor->getValues();

        if (isset($params['group_code'])) {
            $group = $this->segmentGroupsRepository->findByCode($params['group_code']);
            unset($params['group_code']);
        } else {
            // deprecated
            $group = $this->segmentGroupsRepository->find($params['group_id']);
            unset($params['group_id']);
        }
        if (!$group) {
            $response = new JsonApiResponse(Response::S404_NOT_FOUND, ['status' => 'error', 'message' => 'Segment group not found']);
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
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => $emptyCriteriaException->getMessage()]);
            return $response;
        } catch (InvalidCriteriaException $invalidCriteriaException) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => $invalidCriteriaException->getMessage()]);
            return $response;
        }

        $params['fields'] = implode(',', $fields);
        $params['criteria'] = Json::encode($params['criteria']);

        if (isset($params['id'])) {
            $segment = $this->segmentsRepository->findById($params['id']);
            if (!$segment) {
                $response = new JsonApiResponse(Response::S404_NOT_FOUND, ['status' => 'error', 'message' => 'Segment not found']);
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
                $response = new JsonApiResponse(Response::S409_CONFLICT, ['status' => 'error', 'message' => "Segment with code '{$code}' already exists"]);
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
                $params['criteria']
            );
        }

        $response = new JsonApiResponse(Response::S200_OK, [
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
        if (!isset($json['group_id']) && !isset($json['group_code'])) {
            return "missing [group_id] (deprecated) and [group_code] property in JSON payload (use one)";
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
