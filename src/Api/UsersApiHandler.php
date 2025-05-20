<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApiModule\Models\Api\StreamedJsonApiResponse;
use Crm\ApplicationModule\Models\Criteria\CriteriaStorage;
use Crm\SegmentModule\Models\Criteria\InvalidCriteriaException;
use Crm\SegmentModule\Models\SegmentFactoryInterface;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Utils\Json;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class UsersApiHandler extends ApiHandler
{
    private $segmentFactory;

    private $criteriaStorage;

    private $segmentsRepository;

    public function __construct(
        SegmentFactoryInterface $segmentFactory,
        CriteriaStorage $criteriaStorage,
        SegmentsRepository $segmentsRepository,
    ) {
        $this->segmentFactory = $segmentFactory;
        $this->criteriaStorage = $criteriaStorage;
        $this->segmentsRepository = $segmentsRepository;
    }

    /**
     * @inheritdoc
     */
    public function params(): array
    {
        return [
            (new GetInputParam('code'))->setRequired(),
        ];
    }


    public function handle(array $params): ResponseInterface
    {
        $segmentRow = $this->segmentsRepository->findByCode($params['code']);
        if (!$segmentRow) {
            $response = new JsonApiResponse(Response::S404_NOT_FOUND, [
                'status' => 'error',
                'code' => 'segment_not_found',
                'message' => 'Segment does not exist: ' . $params['code'],
            ]);
            return $response;
        }

        $segment = $this->segmentFactory->buildSegment($params['code']);
        $primaryField = $this->criteriaStorage->getPrimaryField($segmentRow['table_name']);

        $response = new StreamedJsonApiResponse(Response::S200_OK, function (Request $request, Response $response) use ($segment, $segmentRow, $primaryField) {
            echo '{"status":"ok","users":[';

            $isFirst = true;
            $segment->process(function ($row) use (&$isFirst, $segmentRow, $primaryField) {
                if (!isset($row[$primaryField])) {
                    throw new InvalidCriteriaException(
                        "Selected segment '{$segmentRow->code}' does not select the primary field '$primaryField' defined for table '{$segmentRow['table_name']}",
                    );
                }

                if ($isFirst) {
                    $isFirst = false;
                } else {
                    echo ",";
                }

                echo Json::encode([
                     'id' => (string) $row[$primaryField],
                     'email' => $row['email'],
                ]);
            }, 0);

            echo '],"memory":' . memory_get_usage(true) . '}';
        });

        return $response;
    }
}
