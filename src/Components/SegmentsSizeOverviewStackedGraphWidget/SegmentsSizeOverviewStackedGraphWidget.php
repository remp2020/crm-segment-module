<?php

namespace Crm\SegmentModule\Components\SegmentsSizeOverviewStackedGraphWidget;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Components\Graphs\GoogleBarGraphGroup\GoogleBarGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Models\Graphs\Criteria;
use Crm\ApplicationModule\Models\Graphs\GraphDataItem;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use InvalidArgumentException;

class SegmentsSizeOverviewStackedGraphWidget extends BaseLazyWidget
{
    private string $templateName = 'segments_size_overview_stacked_graph_widget.latte';
    private array $segmentCodes = [];

    public function __construct(
        LazyWidgetManager $widgetManager,
        private readonly SegmentsRepository $segmentsRepository,
        private readonly Translator $translator,
    ) {
        parent::__construct($widgetManager);
    }

    public function setSegmentCodes(array $segmentCodes): self
    {
        $this->segmentCodes = array_unique($segmentCodes);

        return $this;
    }

    public function render()
    {
        $segmentsCount = count($this->getSegmentIds($this->segmentCodes));
        if ($segmentsCount === 0) {
            throw new InvalidArgumentException('No or not existing segments are configured for Segments Size Overview graph.');
        }

        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }

    public function createComponentGraph(GoogleBarGraphGroupControlFactoryInterface $factory)
    {
        $this->getPresenter()->getSession()->close();

        $params = $this->getParams();
        $segmentIds = $this->getSegmentIds($this->segmentCodes);

        $graphCriteria = (new Criteria())
            ->setTableName('segments_values')
            ->setGroupBy('segments.name')
            ->setSeries('segments.name')
            ->setJoin('LEFT JOIN segments ON segments.id = segments_values.segment_id')
            ->setWhere(sprintf(
                'AND segment_id IN (%s)',
                implode(',', $segmentIds)
            ))
            ->setTimeField('date')
            ->setValueField('MAX(value)');

        if ($params['dateFrom']) {
            $graphCriteria->setStart($params['dateFrom']);
        }
        if ($params['dateTo']) {
            $graphCriteria->setEnd($params['dateTo']);
        }

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria($graphCriteria);

        $graphTitle = $this->translator->translate('segment.components.segments_size_overview_stacked_graph_widget.title');

        $control = $factory->create()
            ->setGraphTitle($graphTitle)
            ->setGraphHelp($graphTitle)
            ->addGraphDataItem($graphDataItem);

        return $control;
    }

    /**
     * @return array{dateFrom: ?string, dateTo: ?string}
     */
    private function getParams(): array
    {
        $params = $this->getPresenterIfExists()?->params ?? [];

        return [
            'dateFrom' => $params['dateFrom'] ?? null,
            'dateTo' => $params['dateTo'] ?? null,
        ];
    }

    /**
     * @param string[] $segmentCodes
     * @return int[]
     */
    private function getSegmentIds(array $segmentCodes): array
    {
        if (count($segmentCodes) === 0) {
            return [];
        }

        return $this->segmentsRepository->getTable()
            ->select('DISTINCT id')
            ->where('code IN (?)', $segmentCodes)
            ->fetchPairs('id', 'id');
    }
}
