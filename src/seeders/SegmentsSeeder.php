<?php

namespace Crm\SegmentModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\SegmentModule\Repository\SegmentGroupsRepository;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Symfony\Component\Console\Output\OutputInterface;

class SegmentsSeeder implements ISeeder
{
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
        $groupName = 'Default group';
        $groupCode = 'default-group';
        $defaultGroup = $this->segmentGroupsRepository->findByCode($groupCode);
        if ($defaultGroup !== null) {
            $output->writeln("  * segment group <info>{$defaultGroup->name}</info> exists");
        } else {
            $defaultGroup = $this->segmentGroupsRepository->add($groupName, $groupCode, 1000);
            $output->writeln("  <comment>* segment group <info>{$defaultGroup->name}</info> created</comment>");
        }

        $groupName = 'System group';
        $groupCode = 'system-group';
        $systemGroup = $this->segmentGroupsRepository->findByCode($groupCode);
        if ($systemGroup !== null) {
            $output->writeln("  * segment group <info>{$systemGroup->name}</info> exists");
        } else {
            $systemGroup = $this->segmentGroupsRepository->add($groupName, $groupCode, 2000);
            $output->writeln("  <comment>* segment group <info>{$systemGroup->name}</info> created</comment>");
        }

        $userFields = 'users.id,users.email,users.first_name,users.last_name';

        $code = 'all_users';
        if ($this->segmentsRepository->exists($code)) {
            $output->writeln("  * segment <info>$code</info> exists");
        } else {
            $query = 'SELECT %fields% FROM %table% WHERE %where%';
            $this->segmentsRepository->add('Všetci používatelia', 1, $code, 'users', $userFields, $query, $defaultGroup);
            $output->writeln("  <comment>* segment <info>$code</info> created</comment>");
        }

        $code = 'users_with_any_subscriptions';
        if ($this->segmentsRepository->exists($code)) {
            $output->writeln("  * segment <info>$code</info> exists");
        } else {
            $query = 'SELECT %fields% FROM %table% INNER JOIN subscriptions ON subscriptions.user_id=%table%.id WHERE %where% GROUP BY %table%.id';
            $this->segmentsRepository->add('Používatelia s aspoň 1 predplatným', 1, $code, 'users', $userFields, $query, $defaultGroup);
            $output->writeln("  <comment>* segment <info>$code</info> created</comment>");
        }

        $code = 'users_with_active_subscriptions';
        if ($this->segmentsRepository->exists($code)) {
            $output->writeln("  * segment <info>$code</info> exists");
        } else {
            $query = 'SELECT %fields% FROM %table% INNER JOIN subscriptions ON subscriptions.user_id=%table%.id WHERE %where% AND subscriptions.start_time<=NOW() AND subscriptions.end_time>NOW() GROUP BY %table%.id';
            $this->segmentsRepository->add('Používatelia s aktuálne plynúcim predplatným', 1, $code, 'users', $userFields, $query, $defaultGroup);
            $output->writeln("  <comment>* segment <info>$code</info> created</comment>");
        }

        $code = 'users_without_actual_subscriptions';
        if ($this->segmentsRepository->exists($code)) {
            $output->writeln("  * segment <info>$code</info> exists");
        } else {
            $query = "SELECT %fields% FROM %table% \n" .
                "LEFT JOIN subscriptions ON subscriptions.user_id=users.id AND subscriptions.start_time <= NOW() AND subscriptions.end_time >= NOW() \n" .
                "WHERE %where% AND subscriptions.id IS NULL \n".
                'GROUP BY %table%.id';
            $this->segmentsRepository->add('Používatelia bez aktuálneho predplatného', 1, $code, 'users', $userFields, $query, $defaultGroup);
            $output->writeln("  <comment>* segment <info>$code</info> created</comment>");
        }

        $code = 'users_without_subscription_any_time';
        if ($this->segmentsRepository->exists($code)) {
            $output->writeln("  * segment <info>$code</info> exists");
        } else {
            $query = "SELECT %fields% FROM %table% \n" .
                "LEFT JOIN subscriptions ON subscriptions.user_id=%table%.id \n" .
                "WHERE %where% AND subscriptions.id IS NULL \n" .
                'GROUP BY %table%.id';
            $this->segmentsRepository->add('Používatelia bez žiadneho predplatného', 1, $code, 'users', $userFields, $query, $defaultGroup);
            $output->writeln("  <comment>* segment <info>$code</info> created</comment>");
        }

        $code = 'users_with_old_subscriptions';
        if ($this->segmentsRepository->exists($code)) {
            $output->writeln("  * segment <info>$code</info> exists");
        } else {
            $query = "SELECT %fields% FROM %table%\n" .
                "INNER JOIN subscriptions AS old_subscriptions ON old_subscriptions.user_id=%table%.id AND old_subscriptions.end_time < NOW() \n" .
                "LEFT JOIN subscriptions AS actual_subscriptions ON actual_subscriptions.user_id=%table%.id AND actual_subscriptions.start_time <= NOW() AND actual_subscriptions.end_time > NOW() \n" .
                "WHERE %where% AND actual_subscriptions.id IS NULL \n" .
                'GROUP BY %table%.id';
            $this->segmentsRepository->add('Používatelia s predplatným v minulosti a aktualne bez', 1, $code, 'users', $userFields, $query, $defaultGroup);
            $output->writeln("  <comment>* segment <info>$code</info> created</comment>");
        }
    }
}
