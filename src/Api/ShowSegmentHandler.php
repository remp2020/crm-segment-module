<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\ApiModule\Response\ApiResponseInterface;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Nette\Http\Response;
use Nette\Utils\Json;

class ShowSegmentHandler extends ApiHandler
{
    private $segmentsRepository;

    public function __construct(
        SegmentsRepository $segmentsRepository
    ) {
        $this->segmentsRepository = $segmentsRepository;
    }

    public function params(): array
    {
        return [
            new InputParam(InputParam::TYPE_GET, 'id', InputParam::REQUIRED),
        ];
    }

    public function handle(array $params): ApiResponseInterface
    {
        $paramsProcessor = new ParamsProcessor($this->params());
        if ($paramsProcessor->hasError()) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Invalid params']);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }
        $params = $paramsProcessor->getValues();

        $segment = $this->segmentsRepository->find($params['id']);
        if (!$segment || $segment->deleted_at !== null) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Segment not found']);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        $response = new JsonResponse(['status' => 'ok', 'segment' => [
            'id' => $segment->id,
            'version' => $segment->version,
            'name' => $segment->name,
            'code' => $segment->code,
            'table_name' => $segment->table_name,
            'fields' => explode(',', $segment->fields),
            'locked' => $segment->locked,
            'criteria' => $segment->criteria ? Json::decode($segment->criteria, Json::PRETTY) : null,
            'group_id' => $segment->segment_group_id, // deprecated
            'group_code' => $segment->segment_group->code,
        ]]);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
