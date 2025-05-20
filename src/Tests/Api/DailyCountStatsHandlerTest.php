<?php

namespace Crm\SegmentModule\Tests\Api;

use Crm\ApiModule\Tests\ApiTestTrait;
use Crm\ApplicationModule\Models\Database\ActiveRow;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SegmentModule\Api\DailyCountStatsHandler;
use Crm\SegmentModule\Repositories\SegmentGroupsRepository;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Crm\SegmentModule\Repositories\SegmentsValuesRepository;
use Nette\Http\IResponse;
use Nette\Utils\DateTime;

class DailyCountStatsHandlerTest extends DatabaseTestCase
{
    use ApiTestTrait;

    protected function tearDown(): void
    {
        $_GET = [];

        parent::tearDown();
    }

    protected function requiredSeeders(): array
    {
        return [];
    }

    protected function requiredRepositories(): array
    {
        return [
            SegmentGroupsRepository::class,
            SegmentsRepository::class,
            SegmentsValuesRepository::class,
        ];
    }

    public function testDailyCountStats(): void
    {
        $segment = $this->createSegment('tests__all_users');

        /** @var SegmentsValuesRepository $segmentsValuesRepository */
        $segmentsValuesRepository = $this->getRepository(SegmentsValuesRepository::class);
        $segmentsValuesRepository->add($segment, new DateTime('2024-01-10'), 10);
        $segmentsValuesRepository->add($segment, new DateTime('2024-01-20 12:00:00'), 20);
        $segmentsValuesRepository->add($segment, new DateTime('2024-01-20 16:00:00'), 25);
        $segmentsValuesRepository->add($segment, new DateTime('2024-01-30'), 30);

        $_GET = [
            'segment_code' => 'tests__all_users',
        ];

        $response = $this->runJsonApi($this->inject(DailyCountStatsHandler::class));
        $this->assertSame(IResponse::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $this->assertCount(3, $payload);
        $this->assertSame([
            [
                'date' => '2024-01-10',
                'count' => 10,
            ],
            [
                'date' => '2024-01-20',
                'count' => 25,
            ],
            [
                'date' => '2024-01-30',
                'count' => 30,
            ],
        ], $payload);
    }

    public function testDailyCountStatsWithDateFromFilter(): void
    {
        $segment = $this->createSegment('tests__all_users');

        /** @var SegmentsValuesRepository $segmentsValuesRepository */
        $segmentsValuesRepository = $this->getRepository(SegmentsValuesRepository::class);
        $segmentsValuesRepository->add($segment, new DateTime('2024-01-10'), 10);
        $segmentsValuesRepository->add($segment, new DateTime('2024-01-20'), 20);
        $segmentsValuesRepository->add($segment, new DateTime('2024-01-30'), 30);

        $_GET = [
            'segment_code' => 'tests__all_users',
            'date_from' => '2024-01-20',
        ];

        $response = $this->runJsonApi($this->inject(DailyCountStatsHandler::class));
        $this->assertSame(IResponse::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $this->assertSame([
            [
                'date' => '2024-01-20',
                'count' => 20,
            ],
            [
                'date' => '2024-01-30',
                'count' => 30,
            ],
        ], $payload);
    }

    public function testDailyCountStatsWithDateToFilter(): void
    {
        $segment = $this->createSegment('tests__all_users');

        /** @var SegmentsValuesRepository $segmentsValuesRepository */
        $segmentsValuesRepository = $this->getRepository(SegmentsValuesRepository::class);
        $segmentsValuesRepository->add($segment, new DateTime('2024-01-10'), 10);
        $segmentsValuesRepository->add($segment, new DateTime('2024-01-20'), 20);
        $segmentsValuesRepository->add($segment, new DateTime('2024-01-30'), 30);

        $_GET = [
            'segment_code' => 'tests__all_users',
            'date_to' => '2024-01-20',
        ];

        $response = $this->runJsonApi($this->inject(DailyCountStatsHandler::class));
        $this->assertSame(IResponse::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $this->assertSame([
            [
                'date' => '2024-01-10',
                'count' => 10,
            ],
            [
                'date' => '2024-01-20',
                'count' => 20,
            ],
        ], $payload);
    }

    public function testDailyCountStatsWithBothDateFilters(): void
    {
        $segment = $this->createSegment('tests__all_users');

        /** @var SegmentsValuesRepository $segmentsValuesRepository */
        $segmentsValuesRepository = $this->getRepository(SegmentsValuesRepository::class);
        $segmentsValuesRepository->add($segment, new DateTime('2024-01-10'), 10);
        $segmentsValuesRepository->add($segment, new DateTime('2024-01-20'), 20);
        $segmentsValuesRepository->add($segment, new DateTime('2024-01-30'), 30);

        $_GET = [
            'segment_code' => 'tests__all_users',
            'date_from' => '2024-01-15',
            'date_to' => '2024-01-25',
        ];

        $response = $this->runJsonApi($this->inject(DailyCountStatsHandler::class));
        $this->assertSame(IResponse::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $this->assertSame([
            [
                'date' => '2024-01-20',
                'count' => 20,
            ],
        ], $payload);
    }

    public function testDailyCountStatsWithNoData(): void
    {
        $segment = $this->createSegment('tests__all_users');

        $_GET = [
            'segment_code' => 'tests__all_users',
        ];

        $response = $this->runJsonApi($this->inject(DailyCountStatsHandler::class));
        $this->assertSame(IResponse::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEmpty($payload);
    }

    public function testDailyCountStatsWithDateFromAfterDateTo(): void
    {
        $this->createSegment('tests__all_users');

        $_GET = [
            'segment_code' => 'tests__all_users',
            'date_from' => '2024-01-25',
            'date_to' => '2024-01-15',
        ];

        $response = $this->runJsonApi($this->inject(DailyCountStatsHandler::class));
        $this->assertSame(IResponse::S400_BadRequest, $response->getCode());

        $payload = $response->getPayload();
        $this->assertArrayHasKey('status', $payload);
        $this->assertSame('error', $payload['status']);

        $this->assertArrayHasKey('code', $payload);
        $this->assertSame('invalid_input', $payload['code']);

        $this->assertArrayHasKey('errors', $payload);
        $this->assertSame([
            'date_from' => [
                "Date 'from' must be earlier than date 'to'.",
            ],
        ], $payload['errors']);
    }

    public function testDailyCountStatsWithInvalidDateFrom(): void
    {
        $this->createSegment('tests__all_users');

        $_GET = [
            'segment_code' => 'tests__all_users',
            'date_from' => '2024-01-25-',
        ];

        $response = $this->runJsonApi($this->inject(DailyCountStatsHandler::class));
        $this->assertSame(IResponse::S400_BadRequest, $response->getCode());

        $payload = $response->getPayload();
        $this->assertArrayHasKey('status', $payload);
        $this->assertSame('error', $payload['status']);

        $this->assertArrayHasKey('code', $payload);
        $this->assertSame('invalid_input', $payload['code']);

        $this->assertArrayHasKey('errors', $payload);
        $this->assertSame([
            'date_from' => [
                'Field contains an invalid date format (YYYY-MM-DD).',
            ],
        ], $payload['errors']);
    }

    public function testDailyCountStatsWithInvalidDateTo(): void
    {
        $this->createSegment('tests__all_users');

        $_GET = [
            'segment_code' => 'tests__all_users',
            'date_to' => '2024-01-25-',
        ];

        $response = $this->runJsonApi($this->inject(DailyCountStatsHandler::class));
        $this->assertSame(IResponse::S400_BadRequest, $response->getCode());

        $payload = $response->getPayload();
        $this->assertArrayHasKey('status', $payload);
        $this->assertSame('error', $payload['status']);

        $this->assertArrayHasKey('code', $payload);
        $this->assertSame('invalid_input', $payload['code']);

        $this->assertArrayHasKey('errors', $payload);
        $this->assertSame([
            'date_to' => [
                'Field contains an invalid date format (YYYY-MM-DD).',
            ],
        ], $payload['errors']);
    }

    public function testDailyCountStatsWithNotFoundSegment(): void
    {
        $_GET = [
            'segment_code' => 'unknown_segment',
        ];

        $response = $this->runJsonApi($this->inject(DailyCountStatsHandler::class));
        $this->assertSame(IResponse::S404_NotFound, $response->getCode());

        $payload = $response->getPayload();
        $this->assertArrayHasKey('status', $payload);
        $this->assertSame('error', $payload['status']);

        $this->assertArrayHasKey('code', $payload);
        $this->assertSame('not_found', $payload['code']);

        $this->assertArrayHasKey('message', $payload);
        $this->assertSame('Segment not found', $payload['message']);
    }

    public function testDailyCountStatsWithNoSegmentCode(): void
    {
        $_GET = [];

        $response = $this->runJsonApi($this->inject(DailyCountStatsHandler::class));
        $this->assertSame(IResponse::S400_BadRequest, $response->getCode());

        $payload = $response->getPayload();
        $this->assertArrayHasKey('status', $payload);
        $this->assertSame('error', $payload['status']);

        $this->assertArrayHasKey('code', $payload);
        $this->assertSame('invalid_input', $payload['code']);

        $this->assertArrayHasKey('errors', $payload);
        $this->assertSame([
            'segment_code' => [
                'Field is required',
            ],
        ], $payload['errors']);
    }

    private function createSegment(string $segmentCode): ?ActiveRow
    {
        /** @var SegmentGroupsRepository $segmentGroupRepository */
        $segmentGroupRepository = $this->getRepository(SegmentGroupsRepository::class);
        $segmentGroup = $segmentGroupRepository->add('Test group', 'test_group');

        /** @var SegmentsRepository $segmentsRepository */
        $segmentsRepository = $this->getRepository(SegmentsRepository::class);
        $segment = $segmentsRepository->add(
            'Some segment' . random_int(0, 10000),
            1,
            $segmentCode,
            'users',
            'users.id',
            'SELECT %fields% FROM %table% WHERE %where%',
            $segmentGroup,
        );
        return $segment;
    }
}
