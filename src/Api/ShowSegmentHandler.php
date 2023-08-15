<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Nette\Http\Response;
use Nette\Utils\Json;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

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

    public function handle(array $params): ResponseInterface
    {
        $paramsProcessor = new ParamsProcessor($this->params());
        if ($paramsProcessor->hasError()) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => 'Invalid params']);
            return $response;
        }
        $params = $paramsProcessor->getValues();

        $segment = $this->segmentsRepository->find($params['id']);
        if (!$segment || $segment->deleted_at !== null) {
            $response = new JsonApiResponse(Response::S404_NOT_FOUND, ['status' => 'error', 'message' => 'Segment not found']);
            return $response;
        }

        $response = new JsonApiResponse(Response::S200_OK, ['status' => 'ok', 'segment' => [
            'id' => $segment->id,
            'version' => $segment->version,
            'name' => $segment->name,
            'note' => $segment->note,
            'code' => $segment->code,
            'table_name' => $segment->table_name,
            'fields' => explode(',', $segment->fields),
            'locked' => $segment->locked,
            'criteria' => $segment->criteria ? Json::decode($segment->criteria, Json::PRETTY) : null,
            'group_id' => $segment->segment_group_id, // deprecated
            'group_code' => $segment->segment_group->code,
        ]]);
        return $response;
    }
}
