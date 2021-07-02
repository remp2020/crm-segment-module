<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\ApplicationModule\Criteria\CriteriaStorage;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Crm\SegmentModule\SegmentFactory;
use Crm\SegmentModule\SegmentFactoryInterface;
use Nette\Http\Response;
use Nette\UnexpectedValueException;

class UsersApiHandler extends ApiHandler
{
    private $segmentFactory;

    private $criteriaStorage;

    private $segmentsRepository;

    public function __construct(
        SegmentFactoryInterface $segmentFactory,
        CriteriaStorage $criteriaStorage,
        SegmentsRepository $segmentsRepository
    ) {
        $this->segmentFactory = $segmentFactory;
        $this->criteriaStorage = $criteriaStorage;
        $this->segmentsRepository = $segmentsRepository;
    }

    /**
     * @inheritdoc
     */
    public function params()
    {
        return [
            new InputParam(InputParam::TYPE_GET, 'code', InputParam::REQUIRED),
        ];
    }

    /**
     * @param ApiAuthorizationInterface $authorization
     * @return \Nette\Application\IResponse
     */
    public function handle(ApiAuthorizationInterface $authorization)
    {
        $paramsProcessor = new ParamsProcessor($this->params());
        $error = $paramsProcessor->isError();
        if ($error) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Invalid params']);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }
        $params = $paramsProcessor->getValues();

        try {
            $segment = $this->segmentFactory->buildSegment($params['code']);
            $segmentRow = $this->segmentsRepository->findByCode($params['code']);
            if (!$segmentRow) {
                throw new UnexpectedValueException("segment does not exist: {$params['code']}");
            }
        } catch (UnexpectedValueException $e) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Segment does not exist']);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        $users = [];
        $segment->process(function ($row) use (&$users, $segmentRow) {
            $users[] = [
                 'id' => strval($row[$this->criteriaStorage->getPrimaryField($segmentRow['table_name'])]),
                 'email' => $row['email'],
             ];
        }, 0);

        $response = new JsonResponse(['status' => 'ok', 'users' => $users, 'memory' => memory_get_usage(true)]);
        $response->setHttpCode(Response::S200_OK);

        return $response;
    }
}
