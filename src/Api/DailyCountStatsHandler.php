<?php

namespace Crm\SegmentModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApplicationModule\Domain\Date;
use Crm\ApplicationModule\Domain\OptionalDateTimeRange;
use Crm\SegmentModule\Api\DailyCountStats\DailySegmentValuesQuery;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use DomainException;
use League\Fractal\ScopeFactoryInterface;
use Nette\Http\IResponse;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class DailyCountStatsHandler extends ApiHandler
{
    private const QUERY_SEGMENT_CODE = 'segment_code';
    private const QUERY_DATE_FROM = 'date_from';
    private const QUERY_DATE_TO = 'date_to';

    public function __construct(
        private readonly SegmentsRepository $segmentsRepository,
        private readonly DailySegmentValuesQuery $dailySegmentValuesQuery,
        ScopeFactoryInterface $scopeFactory = null,
    ) {
        parent::__construct($scopeFactory);
    }

    /**
     * @inheritdoc
     */
    public function params(): array
    {
        return [
            (new GetInputParam(self::QUERY_SEGMENT_CODE))->setRequired(),
            (new GetInputParam(self::QUERY_DATE_FROM)),
            (new GetInputParam(self::QUERY_DATE_TO)),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $segment = $this->segmentsRepository->findBy('code', $params[self::QUERY_SEGMENT_CODE]);
        if (!$segment) {
            return new JsonApiResponse(IResponse::S404_NotFound, [
                'status' => 'error',
                'code' => 'not_found',
                'message' => 'Segment not found',
            ]);
        }

        try {
            $dateFrom = $params[self::QUERY_DATE_FROM] !== null ? new Date($params[self::QUERY_DATE_FROM]) : null;
        } catch (DomainException $exception) {
            return new JsonApiResponse(IResponse::S400_BadRequest, [
                'status' => 'error',
                'code' => 'invalid_input',
                'errors' => [self::QUERY_DATE_FROM => [$exception->getMessage()]],
            ]);
        }

        try {
            $dateTo = $params[self::QUERY_DATE_TO] !== null ? new Date($params[self::QUERY_DATE_TO]) : null;
        } catch (DomainException $exception) {
            return new JsonApiResponse(IResponse::S400_BadRequest, [
                'status' => 'error',
                'code' => 'invalid_input',
                'errors' => [self::QUERY_DATE_TO => [$exception->getMessage()]],
            ]);
        }

        $dateTimeFrom = $dateFrom?->toNativeDateTime()->modifyClone('00:00:00');
        $dateTimeTo = $dateTo?->toNativeDateTime()->modifyClone('23:59:59');

        try {
            $dateTimeRange = new OptionalDateTimeRange($dateTimeFrom, $dateTimeTo);
        } catch (DomainException $exception) {
            return new JsonApiResponse(IResponse::S400_BadRequest, [
                'status' => 'error',
                'code' => 'invalid_input',
                'errors' => [self::QUERY_DATE_FROM => [$exception->getMessage()]],
            ]);
        }

        $values = $this->dailySegmentValuesQuery->retrieve($segment->id, $dateTimeRange);

        $transformedValues = $this->transformOutput($values);

        return new JsonApiResponse(IResponse::S200_OK, $transformedValues);
    }

    /**
     * @param array<string, int> $values
     * @return array{date: string, count: int}[]
     */
    private function transformOutput(array $values): array
    {
        $transformedValues = [];
        foreach ($values as $date => $count) {
            $transformedValues[] = [
                'date' => $date,
                'count' => $count,
            ];
        }
        return $transformedValues;
    }
}
