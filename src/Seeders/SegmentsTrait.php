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
        string $fields = 'users.id,users.email'
    ): ActiveRow {
        // try to load segment before adding it
        $segment = $this->segmentsRepository->findByCode($code);
        if ($segment) {
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

    /**
     * Seed segment seeds segment if it doesn't exists, updates it if it exists.
     *
     * Segment is searched by `code` and therefore `code` is only property which cannot be updated.
     */
    public function seedOrUpdateSegment(
        OutputInterface $output,
        string $name,
        string $code,
        string $queryString,
        ?ActiveRow $group = null,
        string $tableName = 'users',
        string $fields = 'users.id,users.email'
    ): ActiveRow {
        // if no group was specified, default will be used
        if ($group === null) {
            $group = $this->loadDefaultSegmentGroup($output);
        }

        $segment = $this->segmentsRepository->findByCode($code);
        if (!$segment) {
            $segment = $this->segmentsRepository->add($name, 1, $code, $tableName, $fields, $queryString, $group);
            $output->writeln("  <comment>* segment <info>{$code}</info> created</comment>");
            return $segment;
        }

        $dataToUpdate = [];
        if ($segment->name !== $name) {
            $dataToUpdate['name'] = $name;
        }
        if ($segment->query_string !== $queryString) {
            $dataToUpdate['query_string'] = $queryString;
        }
        if ($segment->segment_group_id !== $group->id) {
            $dataToUpdate['segment_group_id'] = $group->id;
        }
        if ($segment->table_name !== $tableName) {
            $dataToUpdate['table_name'] = $tableName;
        }
        if ($segment->fields !== $fields) {
            $dataToUpdate['fields'] = $fields;
        }

        if (empty($dataToUpdate)) {
            $output->writeln("  * segment <info>{$code}</info> exists (no change)");
        } else {
            $this->segmentsRepository->update($segment, $dataToUpdate);
            $changed = implode(',', array_keys($dataToUpdate));
            $output->writeln("  * segment <info>{$code}</info> updated (changed properties: [{$changed}])");
        }

        return $segment;
    }
}
