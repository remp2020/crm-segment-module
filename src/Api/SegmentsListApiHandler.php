<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\ApiModule\Response\ApiResponseInterface;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Nette\Http\Response;

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

    public function handle(array $params): ApiResponseInterface
    {
        $paramsProcessor = new ParamsProcessor($this->params());
        if ($paramsProcessor->hasError()) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Invalid params']);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
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

        $response = new JsonResponse([
            'status' => 'ok',
            'result' => $result,
        ]);
        $response->setHttpCode(Response::S200_OK);

        return $response;
    }
}
