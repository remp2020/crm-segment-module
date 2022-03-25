<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\SegmentModule\Repository\SegmentGroupsRepository;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class ListGroupsHandler extends ApiHandler
{
    private $segmentGroupsRepository;

    public function __construct(SegmentGroupsRepository $segmentGroupsRepository)
    {
        $this->segmentGroupsRepository = $segmentGroupsRepository;
    }

    public function params(): array
    {
        return [];
    }


    public function handle(array $params): ResponseInterface
    {
        $groupsRows = $this->segmentGroupsRepository->all();
        $groups = [];
        foreach ($groupsRows as $row) {
            $groups[] = [
                'id' => $row->id,
                'name' => $row->name,
                'code' => $row->code,
                'sorting' => $row->sorting
            ];
        }

        $response = new JsonApiResponse(Response::S200_OK, ['status' => 'ok', 'groups' => $groups]);

        return $response;
    }
}
