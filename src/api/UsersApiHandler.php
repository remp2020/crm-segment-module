<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\ApplicationModule\Criteria\CriteriaStorage;
use Crm\SegmentModule\Criteria\InvalidCriteriaException;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Crm\SegmentModule\SegmentFactoryInterface;
use Nette\Http\Response;

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
     * @return \Nette\Application\Response
     */
    public function handle(ApiAuthorizationInterface $authorization)
    {
        $paramsProcessor = new ParamsProcessor($this->params());
        $error = $paramsProcessor->isError();
        if ($error) {
            $response = new JsonResponse([
                'status' => 'error',
                'code' => 'invalid_params',
                'message' => 'Invalid params: ' . $error,
            ]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }
        $params = $paramsProcessor->getValues();

        $segmentRow = $this->segmentsRepository->findByCode($params['code']);
        if (!$segmentRow) {
            $response = new JsonResponse([
                'status' => 'error',
                'code' => 'segment_not_found',
                'message' => 'Segment does not exist: ' . $params['code'],
            ]);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        $segment = $this->segmentFactory->buildSegment($params['code']);

        $users = [];
        $segment->process(function ($row) use (&$users, $segmentRow) {
            $primaryField = $this->criteriaStorage->getPrimaryField($segmentRow['table_name']);
            if (!isset($row[$primaryField])) {
                throw new InvalidCriteriaException(
                    "Selected segment '{$segmentRow->code}' does not select the primary field '$primaryField' defined for table '{$segmentRow['table_name']}"
                );
            }
            $users[] = [
                 'id' => (string) $row[$primaryField],
                 'email' => $row['email'],
             ];
        }, 0);

        $response = new JsonResponse(['status' => 'ok', 'users' => $users, 'memory' => memory_get_usage(true)]);
        $response->setHttpCode(Response::S200_OK);

        return $response;
    }
}
