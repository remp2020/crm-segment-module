<?php

namespace Crm\SegmentModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\SegmentModule\Repository\SegmentGroupsRepository;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Symfony\Component\Console\Output\OutputInterface;

class SegmentsSeeder implements ISeeder
{
    use SegmentsTrait;

    private $segmentGroupsRepository;

    private $segmentsRepository;

    public function __construct(
        SegmentGroupsRepository $segmentGroupsRepository,
        SegmentsRepository $segmentsRepository
    ) {
        $this->segmentGroupsRepository = $segmentGroupsRepository;
        $this->segmentsRepository = $segmentsRepository;
    }

    public function seed(OutputInterface $output)
    {
        $this->seedSegment(
            $output,
            'All users',
            'all_users',
            'SELECT %fields% FROM %table% WHERE %where%'
        );

        $this->seedSegment(
            $output,
            'Users with any subscription',
            'users_with_any_subscriptions',
            <<<SQL
SELECT %fields%
FROM %table%
INNER JOIN subscriptions
    ON subscriptions.user_id=%table%.id
WHERE
    %where%
GROUP BY %table%.id
SQL
        );

        $this->seedSegment(
            $output,
            'Users with active subscription',
            'users_with_active_subscriptions',
            <<<SQL
SELECT %fields%
FROM %table%
INNER JOIN subscriptions
    ON subscriptions.user_id=%table%.id
WHERE
    %where%
    AND subscriptions.start_time<=NOW()
    AND subscriptions.end_time>NOW()
GROUP BY %table%.id
SQL
        );

        $this->seedSegment(
            $output,
            'Users without active subscription',
            'users_without_actual_subscriptions',
            <<<SQL
SELECT %fields% FROM %table%
LEFT JOIN subscriptions
    ON subscriptions.user_id=users.id
    AND subscriptions.start_time <= NOW()
    AND subscriptions.end_time >= NOW()
WHERE
    %where%
    AND subscriptions.id IS NULL
GROUP BY %table%.id
SQL
        );

        $this->seedSegment(
            $output,
            'Users without any subscription',
            'users_without_subscription_any_time',
            <<<SQL
SELECT %fields%
FROM %table%
LEFT JOIN subscriptions
    ON subscriptions.user_id=%table%.id
WHERE
    %where%
    AND subscriptions.id IS NULL
GROUP BY %table%.id
SQL
        );

        $this->seedSegment(
            $output,
            'Users with inactive subscription in past',
            'users_with_old_subscriptions',
            <<<SQL
SELECT %fields% FROM %table%
INNER JOIN subscriptions AS old_subscriptions
    ON old_subscriptions.user_id=%table%.id
    AND old_subscriptions.end_time < NOW()
LEFT JOIN subscriptions AS actual_subscriptions
    ON actual_subscriptions.user_id=%table%.id
    AND actual_subscriptions.start_time <= NOW()
    AND actual_subscriptions.end_time > NOW()
WHERE
    %where%
    AND actual_subscriptions.id IS NULL
GROUP BY %table%.id
SQL
        );
    }
}
