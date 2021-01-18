<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
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

    public function params()
    {
        return [
            new InputParam(InputParam::TYPE_GET, 'id', InputParam::OPTIONAL),
        ];
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
            $json = Json::decode($request, Json::FORCE_ARRAY);
        } catch (JsonException $e) {
            $response = new JsonResponse(['status' => 'error', 'message' => "Malformed JSON: " . $e->getMessage()]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        $paramsProcessor = new ParamsProcessor($this->params());
        if ($paramsProcessor->isError()) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Invalid params']);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }
        if ($err = $this->hasError($json)) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Invalid params: ' . $err]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
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
            $response = new JsonResponse(['status' => 'error', 'message' => 'Segment group not found']);
            $response->setHttpCode(Response::S404_NOT_FOUND);
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
            $response = new JsonResponse(['status' => 'error', 'message' => $emptyCriteriaException->getMessage()]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        } catch (InvalidCriteriaException $invalidCriteriaException) {
            $response = new JsonResponse(['status' => 'error', 'message' => $invalidCriteriaException->getMessage()]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        $params['fields'] = implode(',', $fields);
        $params['criteria'] = JSON::encode($params['criteria']);

        if (isset($params['id'])) {
            $segment = $this->segmentsRepository->findById($params['id']);
            if (!$segment) {
                $response = new JsonResponse(['status' => 'error', 'message' => 'Segment not found']);
                $response->setHttpCode(Response::S404_NOT_FOUND);
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
                $response = new JsonResponse(['status' => 'error', 'message' => "Segment with code '{$code}' already exists"]);
                $response->setHttpCode(Response::S409_CONFLICT);
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

        $response = new JsonResponse([
            'status' => 'ok',
            'id' => $segment->id,
            'code' => $segment->code,
        ]);
        $response->setHttpCode(Response::S200_OK);
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
