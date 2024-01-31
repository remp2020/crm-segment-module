<?php

namespace Crm\SegmentModule\Segment;

use Crm\ApplicationModule\Models\Criteria\CriteriaInterface;
use Crm\SegmentModule\Models\Params\ParamsBag;
use Crm\SegmentModule\Models\Params\StringArrayParam;
use Crm\SegmentModule\Repositories\SegmentsRepository;

class SegmentCriteria implements CriteriaInterface
{
    public const KEY = 'segment';

    public function __construct(
        private SegmentsRepository $segmentsRepository
    ) {
    }

    public function label(): string
    {
        return "Segments";
    }

    public function category(): string
    {
        return "Users";
    }

    public function params(): array
    {
        return [
            new StringArrayParam(
                key: self::KEY,
                label: "Segments",
                help: "Relation to other segments",
                required: true,
                options: $this->availableSegments()
            ),
        ];
    }

    public function join(ParamsBag $params): string
    {
        $segmentCodes = $params->stringArray(self::KEY)->sortedStrings();

        $subQueries = [];
        foreach ($segmentCodes as $segmentCode) {
            $code = addslashes($segmentCode);
            // segment.SEGMENT_CODE will be later replaced by actual segment query
            $subQueries[] = "SELECT a.id FROM ( %segment.{$code}% ) AS a";
        }

        return "( " . implode("\nUNION\n", $subQueries) . " )";
    }

    public function title(ParamsBag $params): string
    {
        return "with segment {$params->stringArray('segment')->escapedString()}";
    }

    public function fields(): array
    {
        return [];
    }

    private function availableSegments(): array
    {
        return $this->segmentsRepository
            ->all()
            ->where('table_name', 'users')
            ->fetchPairs('code', 'name');
    }
}
