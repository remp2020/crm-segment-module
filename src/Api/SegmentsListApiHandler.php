<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApiModule\Models\Params\InputParam;
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
            new InputParam(InputParam::TYPE_GET, 'group_code', InputParam::OPTIONAL),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $groupSelection = $this->segmentsRepository->all();
        if (isset($params['group_code'])) {
            $groupSelection->where(['segment_group.code' => $params['group_code']]);
        }

        $segments = [];
        foreach ($groupSelection->fetchAll() as $segment) {
            $segments[$segment->table_name][] = [
                'code' => $segment->code,
                'name' => $segment->name,
                'group' => [
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
