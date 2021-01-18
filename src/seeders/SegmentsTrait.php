<?php

namespace Crm\SegmentModule\Seeders;

use Crm\SegmentModule\Repository\SegmentGroupsRepository;
use Nette\Database\Table\ActiveRow;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @property SegmentGroupsRepository $segmentGroupsRepository
 */
trait SegmentsTrait
{
    public function loadDefaultSegmentGroup(OutputInterface $output): ActiveRow
    {
        return $this->seedSegmentGroup($output, 'Default group', 'default-group', 1000);
    }

    public function seedSegmentGroup(OutputInterface $output, string $groupName, string $groupCode, int $sorting = 5000): ActiveRow
    {
        $group = $this->segmentGroupsRepository->findByCode($groupCode);
        if ($group !== null) {
            $output->writeln("  * segment group <info>{$group->name}</info> exists");
        } else {
            $group = $this->segmentGroupsRepository->add(
                $groupName,
                $groupCode,
                $sorting
            );
            $output->writeln("  <comment>* segment group <info>{$group->name}</info> created</comment>");
        }

        return $group;
    }
}
