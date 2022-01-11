<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\SegmentModule\Repository\SegmentGroupsRepository;
use Nette\Http\Response;

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

    /**
     * @param ApiAuthorizationInterface $authorization
     * @return \Nette\Application\Response
     */
    public function handle(ApiAuthorizationInterface $authorization)
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

        $response = new JsonResponse(['status' => 'ok', 'groups' => $groups]);
        $response->setHttpCode(Response::S200_OK);

        return $response;
    }
}
