<?php

namespace Crm\SegmentModule\Components\DashboardSegmentValueBaseWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\ApplicationModule\Repositories\CacheRepository;
use Crm\SegmentModule\Models\SegmentFactoryInterface;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Crm\SegmentModule\Repositories\SegmentsValuesRepository;
use Nette\Utils\DateTime;
use ReflectionClass;

/**
 * Widget class for rendering single segment value in dashboard
 * when extending, provide segment code value (and optionally segmentCodeToCompare).
 * Widget also expects 'dashboard_segment_value_widget.latte' template in widget directory. If not found,
 * default template is used.
 */
abstract class DashboardSegmentValueBaseWidget extends BaseLazyWidget
{
    private bool $onTheFly = false;

    private int $onTheFlyCacheTimeoutMinutes = 0;

    protected ?string $titleText = null;
    protected ?string $tooltipText = null;

    public function __construct(
        private SegmentsRepository $segmentsRepository,
        private SegmentsValuesRepository $segmentsValuesRepository,
        private SegmentFactoryInterface $segmentFactory,
        private CacheRepository $cacheRepository,
        LazyWidgetManager $lazyWidgetManager,
    ) {
        parent::__construct($lazyWidgetManager);
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
        $segment = $this->segmentsRepository->findByCode($this->segmentCode());
        $missingSegment = false;
        if ($segment) {
            [$count, $wasCalculated] = $this->getSegmentCount($this->segmentCode());
            $segmentLink = $this->presenter->link(':Segment:StoredSegments:show', $segment->id);

            $this->template->titleText = $this->titleText ?? $segment->name;
            if ($this->tooltipText) {
                $this->template->tooltipText = $this->tooltipText;
            }
            $this->template->count = $count;
            $this->template->segmentLink = $segmentLink;
        } else {
            $wasCalculated = false;
            $missingSegment = true;
        }

        $this->template->missingSegment = $missingSegment;
        $this->template->segmentCode = $this->segmentCode();
        $this->template->wasCalculated = $wasCalculated;

        if ($this->segmentCodeToCompare()) {
            $segmentLinkToCompare = $this->presenter->link(
                ':Segment:StoredSegments:show',
                $this->segmentsRepository->findByCode($this->segmentCodeToCompare())->id,
            );
            [$countToCompare, $wasCalculated] = $this->getSegmentCount($this->segmentCodeToCompare());
            $this->template->segmentLinkToCompare = $segmentLinkToCompare;
            $this->template->countToCompare = $countToCompare;
        }

        $templatePath = $this->getDir() . DIRECTORY_SEPARATOR . $this->templateName();
        if (!file_exists($templatePath)) {
            // use default template
            $templatePath = __DIR__ . DIRECTORY_SEPARATOR . $this->templateName();
        }

        $this->template->setFile($templatePath);
        $this->template->render();
    }

    private function getSegmentCount($segmentCode): array
    {
        $wasCalculated = true;

        if ($this->onTheFly) {
            $segment = $this->segmentFactory->buildSegment($segmentCode);
            $callable = static fn () => $segment->totalCount();

            if ($this->onTheFlyCacheTimeoutMinutes > 0) {
                $cacheKey = 'segment_' . $segmentCode;
                $count = $this->cacheRepository->loadAndUpdate(
                    $cacheKey,
                    $callable,
                    DateTime::from("-  {$this->onTheFlyCacheTimeoutMinutes} minutes"),
                );
            } else {
                $count = $callable();
            }
        } else {
            $segmentValues = $this->segmentsValuesRepository->mostRecentValues($segmentCode);
            if (!$segmentValues) {
                $wasCalculated = false;
                $count = 0;
            } else {
                $count = $segmentValues->value;
            }
        }

        return [$count, $wasCalculated];
    }
}
