<?php

namespace Crm\SegmentsModule\Widget;

use Crm\ApplicationModule\Cache\CacheRepository;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Crm\SegmentModule\Repository\SegmentsValuesRepository;
use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\SegmentModule\SegmentFactory;
use Crm\SegmentModule\SegmentFactoryInterface;
use Nette\Utils\DateTime;
use ReflectionClass;

/**
 * Widget class for rendering single segment value in dashboard
 * when extending, provide segment code value (and optionally segmentCodeToCompare)
 * widget also expects 'dashboard_segment_value_widget.latte' template in widget directory (can be overridden by extending getConfigOptions())
 */
abstract class DashboardSegmentValueBaseWidget extends BaseWidget
{
    private $segmentsRepository;

    private $segmentsValuesRepository;

    private $segmentFactory;

    private $cacheRepository;

    private $onTheFly = 0;

    private $onTheFlyCacheTimeoutMinutes = 0;

    public function __construct(
        SegmentsRepository $segmentsRepository,
        SegmentsValuesRepository $segmentsValuesRepository,
        SegmentFactoryInterface $segmentFactory,
        CacheRepository $cacheRepository,
        WidgetManager $widgetManager
    ) {
        parent::__construct($widgetManager);
        $this->segmentsRepository = $segmentsRepository;
        $this->segmentsValuesRepository = $segmentsValuesRepository;
        $this->segmentFactory = $segmentFactory;
        $this->cacheRepository = $cacheRepository;
    }

    abstract public function segmentCode(): string;

    protected function templateName(): string
    {
        return 'dashboard_segment_value_widget.latte';
    }

    protected function segmentCodeToCompare(): ?string
    {
        return null;
    }

    /**
     * If onTheFly computation is enabled, widget computes segment values on the fly and do not use precalculated 'segments_values' table
     * Caching is also possible (if second parameter > 0 is specified)
     * @param bool $enable
     * @param int  $cacheTimeoutMinutes
     */
    public function setOnTheFly(bool $enable, int $cacheTimeoutMinutes = 0): void
    {
        $this->onTheFly = $enable;
        $this->onTheFlyCacheTimeoutMinutes = $cacheTimeoutMinutes;
    }

    protected function getDir()
    {
        $reflector = new ReflectionClass(get_class($this));
        $filename = $reflector->getFileName();
        return dirname($filename);
    }

    public function identifier()
    {
        $path = explode('\\', get_class($this));
        return array_pop($path);
    }

    public function render()
    {
        [$segmentLink, $count, $wasCalculated] = $this->getSegmentLinkAndCount($this->segmentCode());
        $this->template->segmentLink = $segmentLink;
        $this->template->count = $count;
        $this->template->wasCalculated = $wasCalculated;

        if ($this->segmentCodeToCompare()) {
            [$segmentLinkToCompare, $countToCompare, $wasCalculated] = $this->getSegmentLinkAndCount($this->segmentCodeToCompare());
            $this->template->segmentLinkToCompare = $segmentLinkToCompare;
            $this->template->countToCompare = $countToCompare;
        }

        $this->template->setFile($this->getDir() . DIRECTORY_SEPARATOR . $this->templateName());
        $this->template->render();
    }

    private function getSegmentLinkAndCount($segmentCode): array
    {
        if (!$this->segmentsRepository->exists($segmentCode)) {
            throw new \Exception('Trying to render ' . __CLASS__ . ' with non-existing segment: ' . $segmentCode . '. Did you need to run application:seed command?');
        }

        $link = $this->presenter->link(':Segment:StoredSegments:show', $this->segmentsRepository->findByCode($segmentCode)->id);

        $wasCalculated = true;

        if ($this->onTheFly) {
            $segment = $this->segmentFactory->buildSegment($segmentCode);
            $callable = function () use ($segment) {
                return $segment->totalCount();
            };

            if ($this->onTheFlyCacheTimeoutMinutes > 0) {
                $cacheKey = 'segment_' . $segmentCode;
                $count = $this->cacheRepository->loadAndUpdate(
                    $cacheKey,
                    $callable,
                    DateTime::from("-  {$this->onTheFlyCacheTimeoutMinutes} minutes")
                );
            } else {
                $count = $callable();
            }
        } else {
            $segmentValues = $this->segmentsValuesRepository->mostRecentValues($segmentCode);
            if ($segmentValues === false) {
                $wasCalculated = false;
                $count = 0;
            } else {
                $count = $segmentValues->value;
            }
        }

        return [$link, $count, $wasCalculated];
    }
}
