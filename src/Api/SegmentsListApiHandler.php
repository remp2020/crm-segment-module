<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApiModule\Models\Params\InputParam;
use Crm\ApiModule\Models\Params\ParamsProcessor;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class SegmentsListApiHandler extends ApiHandler
{
    private $segmentsRepository;

    public function __construct(SegmentsRepository $segmentsRepository)
    {
        $this->segmentsRepository = $segmentsRepository;
    }

    public function params(): array
    {
        return [
            new InputParam(InputParam::TYPE_GET, 'group_id', InputParam::OPTIONAL), // deprecated
            new InputParam(InputParam::TYPE_GET, 'group_code', InputParam::OPTIONAL),
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

        $groupSelection = $this->segmentsRepository->all();
        if (isset($params['group_code'])) {
            $groupSelection->where(['segment_group.code' => $params['group_code']]);
        } elseif (isset($params['group_id'])) {
            // deprecated
            $groupSelection->where(['segment_group_id' => $params['group_id']]);
        }

        $segments = [];
        foreach ($groupSelection->fetchAll() as $segment) {
            $segments[$segment->table_name][] = [
                'code' => $segment->code,
                'name' => $segment->name,
                'group' => [
                    'id' => $segment->segment_group->id, // deprecated
                    'name' => $segment->segment_group->name,
                    'code' => $segment->segment_group->code,
                    'sorting' => $segment->segment_group->sorting,
                ],
            ];
        }

        $result = [];
        foreach ($segments as $table => $segment) {
            $result[] = [
                'table' => $table,
                'segments' => $segment,
            ];
        }

        $response = new JsonApiResponse(Response::S200_OK, [
            'status' => 'ok',
            'result' => $result,
        ]);

        return $response;
    }
}
