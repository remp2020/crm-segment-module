<?php

namespace Crm\SegmentModule\Tests;

use Crm\ApplicationModule\Models\Criteria\CriteriaStorage;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SegmentModule\Api\CreateOrUpdateSegmentHandler;
use Crm\SegmentModule\Models\Config;
use Crm\SegmentModule\Models\SegmentConfig;
use Crm\SegmentModule\Models\SegmentFactory;
use Crm\SegmentModule\Repositories\SegmentGroupsRepository;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Crm\SegmentModule\Repositories\SegmentsValuesRepository;
use Crm\SegmentModule\Segment\SegmentCriteria;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UserMetaRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Crm\UsersModule\Segment\ActiveCriteria;
use Nette\Http\IResponse;
use Nette\Utils\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use Tomaj\NetteApi\Response\JsonApiResponse;

class SegmentsConditionsTest extends DatabaseTestCase
{
    private UserManager $userManager;
    private UsersRepository $usersRepository;
    private SegmentsRepository $segmentsRepository;
    private SegmentGroupsRepository $segmentGroupsRepository;
    private CreateOrUpdateSegmentHandler $createOrUpdateSegmentHandler;
    private SegmentFactory $segmentFactory;

    private const CRITERIA_BLUEPRINT = <<<BLUEPRINT
                {
            "version": "1",
  "nodes": [
    {
        "type": "operator",
      "operator": "AND",
      "nodes": [
        {
            "type": "criteria",
          "key": "active",
          "negation": false,
          "values": {
            "active": true
          }
        },
        __SEGMENTS_CRITERIA_PLACEHOLDER__
      ]
    }
  ]
}
BLUEPRINT;

    private const SEGMENTS_CRITERIA_BLUEPRINT = <<<BLUEPRINT
{
            "type": "criteria",
          "key": "segment",
          "negation": __NEGATION_VALUE__,
          "values": {
            "segment": __JSON_ENCODED_ARRAY_OF_SEGMENT_CODES__
          }
        }
BLUEPRINT;

    public static function segmentsDataProvider(): iterable
    {
        // convert name(s) to email(s) with @example.com domain
        $e = static function (string ...$emailNames) {
            return array_map(fn ($name) => $name . '@example.com', $emailNames);
        };

        // array of [email => active_status, ...]
        $usersAndActiveStatus = [
           ...array_combine($e('a', 'b', 'c', 'd', 'e', 'f'), [true, true, true, true, true, true]),
           ...array_combine($e('x', 'y', 'z'), [false, false, false])
        ];

        yield 'test_three_intersecting_segments' => [
            'userEmails' => $usersAndActiveStatus,
            'segments' => [
                'segment_a' => $e('a', 'b', 'c', 'z'),
                'segment_b' => $e('a', 'b', 'd', 'z'),
                'segment_c' => $e('a', 'c', 'd', 'z'),
            ],
            'criteria' => [
                ['segments' => ['segment_a', 'segment_b'], 'negate' => false],
                ['segments' => ['segment_c'], 'negate' => false]
            ],
            'expectedEmailsInResult' => $e('a', 'c', 'd')
        ];

        yield 'test_three_segments_and_negate_condition' => [
            'userEmails' => $usersAndActiveStatus,
            'segments' => [
                'segment_a' => $e('a', 'b', 'c', 'z'),
                'segment_b' => $e('b'),
                'segment_c' => $e('c'),
            ],
            'criteria' => [
                ['segments' => ['segment_b', 'segment_c'], 'negate' => true],
                ['segments' => ['segment_a'], 'negate' => false]
            ],
            'expectedEmailsInResult' => $e('a')
        ];

        yield 'test_negate_condition' => [
            'userEmails' => $usersAndActiveStatus,
            'segments' => [
                'segment_a' => $e('a', 'b', 'c', 'd', 'z'),
            ],
            'criteria' => [
                ['segments' => ['segment_a'], 'negate' => true],
            ],
            'expectedEmailsInResult' => $e('e', 'f')
        ];

        yield 'test_condition' => [
            'userEmails' => $usersAndActiveStatus,
            'segments' => [
                'segment_a' => $e('a'),
            ],
            'criteria' => [
                ['segments' => ['segment_a'], 'negate' => false],
            ],
            'expectedEmailsInResult' => $e('a')
        ];

        yield 'test_empty_segment' => [
            'userEmails' => $usersAndActiveStatus,
            'segments' => [
                'segment_a' => $e('a_nonexists'),
            ],
            'criteria' => [
                ['segments' => ['segment_a'], 'negate' => false],
            ],
            'expectedEmailsInResult' => [] // result is empty
        ];

        yield 'test_inactive_user_segment' => [
            'userEmails' => $usersAndActiveStatus,
            'segments' => [
                'segment_a' => $e('z'),
            ],
            'criteria' => [
                ['segments' => ['segment_a'], 'negate' => false],
            ],
            'expectedEmailsInResult' => [] // result is empty ('z' is not active)
        ];
    }

    #[DataProvider('segmentsDataProvider')]
    public function testSegmentsConditions($userEmails, $segments, $criteria, $expectedEmailsInResult): void
    {
        $segmentGroup = $this->segmentGroupsRepository->add('test', 'test');

        // insert users
        $userIdToEmail = [];
        foreach ($userEmails as $email => $active) {
            $user = $this->userManager->addNewUser(email: $email, sendEmail: false);
            $userIdToEmail[$user->id] = $email;
            if (!$active) {
                $this->usersRepository->update($user, ['active' => 0]);
            }
        }

        // create segments v1, without criteria
        foreach ($segments as $segmentName => $emails) {
            $concatenatedEmails = trim(Json::encode($emails), "[]");
            $queryString = <<<SQL
SELECT %fields% FROM %table%
WHERE %where% AND %table%.email IN ($concatenatedEmails) 
GROUP BY %table%.id
SQL;
            $this->segmentsRepository->add(
                name: $segmentName,
                version: 1,
                code: $segmentName,
                tableName: 'users',
                fields: 'users.id,users.email,users.created_at',
                queryString: $queryString,
                group: $segmentGroup,
            );
        }

        $testSegmentCode = 'test_segment';

        // create segment v2 (as built by Segments builder),
        // with following criteria:
        // - active user criterion
        // - segments criteria
        $this->createOrUpdateSegmentHandler->setRawPayload(Json::encode([
            'table_name' => 'users',
            'code' => $testSegmentCode,
            'name' => $testSegmentCode,
            'group_code' => $segmentGroup->code,
            'criteria' => Json::decode($this->buildSegmentCriteria($criteria)),
            'fields' => ['id', 'email'],
        ]));
        $response = $this->createOrUpdateSegmentHandler->handle([]);
        $this->assertInstanceOf(JsonApiResponse::class, $response);
        $this->assertEquals(IResponse::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $segmentRow = $this->segmentsRepository->find($payload['id']);

        // Test all relevant SegmentInterface methods
        $segment = $this->segmentFactory->buildSegment(SegmentConfig::fromSegmentActiveRow($segmentRow));

        $segmentIds = $segment->getIds();
        $segmentEmails = array_map(fn ($id) => $userIdToEmail[$id], $segmentIds);

        $this->assertEquals(count($expectedEmailsInResult), $segment->totalCount());
        $this->assertEqualsCanonicalizing($segmentEmails, $expectedEmailsInResult);
        if ($expectedEmailsInResult) {
            $this->assertTrue($segment->isIn('email', $expectedEmailsInResult[0]));
            $this->assertFalse($segment->isIn('email', $expectedEmailsInResult[0] . '_DOES_NOT_EXISTS_'));
        }
        $processingResult = [];
        $segment->process(function ($row) use (&$processingResult) {
            $processingResult[] = $row->email;
        });
        $this->assertEqualsCanonicalizing($expectedEmailsInResult, $processingResult);
    }

    private function buildSegmentCriteria($criteria): string
    {
        $segmentCriteria = [];

        foreach ($criteria as $criterion) {
            $x = self::SEGMENTS_CRITERIA_BLUEPRINT;
            $x = str_replace('__NEGATION_VALUE__', Json::encode($criterion['negate']), $x);
            $x = str_replace('__JSON_ENCODED_ARRAY_OF_SEGMENT_CODES__', Json::encode($criterion['segments']), $x);
            $segmentCriteria[] = $x;
        }

        $jsonCriteria = str_replace(
            '__SEGMENTS_CRITERIA_PLACEHOLDER__',
            implode(',', $segmentCriteria),
            self::CRITERIA_BLUEPRINT
        );
        return $jsonCriteria;
    }


    protected function requiredRepositories(): array
    {
        return [
            // users
            UsersRepository::class,
            UserMetaRepository::class,
            // segments
            SegmentsRepository::class,
            SegmentsValuesRepository::class,
            SegmentGroupsRepository::class
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->inject(Config::class)->setSegmentNestingEnabled();

        $this->userManager = $this->inject(UserManager::class);
        $this->usersRepository = $this->inject(UsersRepository::class);
        $this->segmentsRepository = $this->inject(SegmentsRepository::class);
        $this->segmentGroupsRepository = $this->inject(SegmentGroupsRepository::class);
        $this->createOrUpdateSegmentHandler = $this->inject(CreateOrUpdateSegmentHandler::class);
        $this->segmentFactory = $this->inject(SegmentFactory::class);

        $criteriaStorage = $this->inject(CriteriaStorage::class);
        $criteriaStorage->setDefaultFields('users', ['id', 'email']);
        $criteriaStorage->register('users', 'active', $this->inject(ActiveCriteria::class));
        $criteriaStorage->register('users', 'segment', $this->inject(SegmentCriteria::class));
    }

    protected function requiredSeeders(): array
    {
        return [];
    }
}
