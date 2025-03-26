<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Response;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class ListApiHandler extends ApiHandler
{
    private $segmentsRepository;

    public function __construct(SegmentsRepository $segmentsRepository)
    {
        $this->segmentsRepository = $segmentsRepository;
    }

    public function params(): array
    {
        return [
            new GetInputParam('group_code'),
        ];
    }


    public function handle(array $params): ResponseInterface
    {
        $query = $this->segmentsRepository->all();
        if (isset($params['group_code'])) {
            $query = $query->where(['segment_group.code' => $params['group_code']]);
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
