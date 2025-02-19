<?php

namespace Crm\SegmentModule\Models;

use Crm\SegmentModule\Repositories\SegmentsRepository;
use Nette\Database\Table\Selection;

class AdminFilterFormData
{
    private array $formData;

    public function __construct(
        private readonly SegmentsRepository $segmentsRepository,
    ) {
    }

    public function parse(array $formData): void
    {
        $this->formData = $formData;
    }

    public function getFilteredSegments(bool $deleted = false): Selection
    {
        if ($deleted) {
            $segmentsQuery = $this->segmentsRepository->deleted();
        } else {
            $segmentsQuery = $this->segmentsRepository->all();
        }

        $segmentsQuery = $segmentsQuery
            ->select('segments.*')
            ->group('segments.id');

        if ($this->getName()) {
            $segmentsQuery->where('segments.name LIKE ?', "%{$this->getName()}%");
        }
        if ($this->getCode()) {
            $segmentsQuery->where('segments.code LIKE ?', "%{$this->getCode()}%");
        }
        if ($this->getTableNames()) {
            $segmentsQuery->where('table_name IN (?)', $this->getTableNames());
        }
        if ($this->getGroups()) {
            $segmentsQuery->where('segment_group.code IN (?)', $this->getGroups());
        }

        return $segmentsQuery;
    }

    public function getFormValues()
    {
        return [
            'name' => $this->getName(),
            'code' => $this->getCode(),
            'table_name' => $this->getTableNames(),
            'group' => $this->getGroups(),
        ];
    }

    private function getName(): ?string
    {
        return $this->formData['name'] ?? null;
    }

    private function getCode(): ?string
    {
        return $this->formData['code'] ?? null;
    }

    private function getTableNames(): ?array
    {
        return $this->formData['table_name'] ?? null;
    }

    private function getGroups(): ?array
    {
        return $this->formData['group'] ?? null;
    }
}
