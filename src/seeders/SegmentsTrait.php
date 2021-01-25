<?php

namespace Crm\SegmentModule\Seeders;

use Crm\SegmentModule\Repository\SegmentGroupsRepository;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Nette\Database\Table\ActiveRow;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @property SegmentGroupsRepository $segmentGroupsRepository
 * @property SegmentsRepository $segmentsRepository
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

    /**
     * Seed segment checks if segment was seeded and adds it if not. Segments are not updated.
     */
    public function seedSegment(
        OutputInterface $output,
        string $name,
        string $code,
        string $query,
        ?ActiveRow $group = null,
        string $table = 'users',
        string $fields = 'users.id,users.email,users.first_name,users.last_name'
    ): ActiveRow {
        // try to load segment before adding it
        $segment = $this->segmentsRepository->findByCode($code);
        if ($segment !== false) {
            $output->writeln("  * segment <info>{$code}</info> exists");
            return $segment;
        }

        // if no group was specified, default will be used
        if ($group === null) {
            $group = $this->loadDefaultSegmentGroup($output);
        }

        $segment = $this->segmentsRepository->add($name, 1, $code, $table, $fields, $query, $group);
        $output->writeln("  <comment>* segment <info>{$code}</info> created</comment>");

        return $segment;
    }
}
