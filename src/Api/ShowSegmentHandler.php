<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Nette\Http\Response;
use Nette\Utils\Json;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class ShowSegmentHandler extends ApiHandler
{
    private $segmentsRepository;

    public function __construct(
        SegmentsRepository $segmentsRepository,
    ) {
        $this->segmentsRepository = $segmentsRepository;
    }

    public function params(): array
    {
        return [
            (new GetInputParam('id'))->setRequired(),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
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
            'group_code' => $segment->segment_group->code,
        ]]);
        return $response;
    }
}
