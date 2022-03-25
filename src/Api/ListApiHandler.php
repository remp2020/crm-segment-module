<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class ListApiHandler extends ApiHandler
{
    private $segmentsRepository;

    public function __construct(SegmentsRepository $segmentsRepository)
    {
        $this->segmentsRepository = $segmentsRepository;
    }

    /**
     * @inheritdoc
     */
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

        $query = $this->segmentsRepository->all();
        if (isset($params['group_code'])) {
            $query = $query->where(['segment_group.code' => $params['group_code']]);
        } elseif (isset($params['group_id'])) {
            // deprecated
            $query = $query->where(['segment_group_id' => $params['group_id']]);
        }

        $segments = [];
        /** @var ActiveRow $segment */
        foreach ($query->fetchAll() as $segment) {
            if ($segment->table_name !== 'users') {
                continue;
            }
            $segments[] = [
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

        $response = new JsonApiResponse(Response::S200_OK, ['status' => 'ok', 'segments' => $segments]);

        return $response;
    }
}
