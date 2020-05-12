<?php

namespace Crm\SegmentModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\ExcelFactory;
use Crm\ApplicationModule\Graphs\Criteria;
use Crm\ApplicationModule\Graphs\GraphDataItem;
use Crm\SegmentModule\Forms\SegmentFormFactory;
use Crm\SegmentModule\Repository\SegmentGroupsRepository;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Crm\SegmentModule\Repository\SegmentsValuesRepository;
use Crm\SegmentModule\SegmentFactory;
use Crm\UsersModule\Auth\Access\AccessToken;
use Nette\Application\Responses\FileResponse;
use Nette\Database\Context;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class StoredSegmentsPresenter extends AdminPresenter
{
    private $segmentsRepository;

    private $segmentsValuesRepository;

    private $segmentFactory;

    private $segmentFormFactory;

    private $excelFactory;

    private $segmentGroupsRepository;

    private $accessToken;

    private $database;

    public function __construct(
        SegmentsRepository $segmentsRepository,
        SegmentsValuesRepository $segmentsValuesRepository,
        SegmentFactory $segmentFactory,
        SegmentFormFactory $segmentFormFactory,
        ExcelFactory $excelFactory,
        SegmentGroupsRepository $segmentGroupsRepository,
        AccessToken $accessToken,
        Context $database
    ) {
        parent::__construct();

        $this->segmentsRepository = $segmentsRepository;
        $this->segmentsValuesRepository = $segmentsValuesRepository;
        $this->segmentFactory = $segmentFactory;
        $this->segmentFormFactory = $segmentFormFactory;
        $this->excelFactory = $excelFactory;
        $this->segmentGroupsRepository = $segmentGroupsRepository;
        $this->accessToken = $accessToken;
        $this->database = $database;
    }

    public function renderDefault()
    {
        $this->template->segmentGroups = $this->segmentGroupsRepository->all();
        $this->template->segments = $this->segmentsRepository->all();
        $this->template->deletedSegments = $this->segmentsRepository->deleted();
    }

    public function renderNew($version = 2)
    {
        $this->template->version = $version;
    }

    public function renderEdit($id, $version = null)
    {
        $segment = $this->segmentsRepository->find($id);
        $this->template->segment = $segment;
        $this->template->version = $version == null ? $segment->version : $version;
    }

    public function renderShow($id, $data = false)
    {
        $segmentRow = $this->loadSegment($id);
        $this->template->segment = $segmentRow;
        $this->template->showData = $data;

        $segment = $this->segmentFactory->buildSegment($segmentRow->code);

        $ids = [];
        $tableData = [];
        $displayFields = false;

        $segment->process(function ($row) use (&$ids, $data, &$tableData, &$displayFields) {
            $ids[] = $row->id;

            if ($data) {
                if (!$displayFields) {
                    $displayFields = array_keys((array) $row);
                }
                $tableData[] = array_values((array) $row);
            }
        }, 100000);

        $this->template->ids = $ids;
        $this->template->fields = $displayFields;
        $this->template->data = $tableData;
    }

    public function handleRecalculate(int $id)
    {
        // load segment
        $segmentRow = $this->loadSegment($id);
        $segment = $this->segmentFactory->buildSegment($segmentRow->code);

        // store cached count
        $count = $segment->totalCount();
        $this->segmentsValuesRepository->cacheSegmentCount($segmentRow, $count);

        $this->presenter->flashMessage($this->translator->translate('segment.messages.segment_count_recalculated'));

        // reload snippet / page
        if ($this->isAjax()) {
            $this->template->segment = $segmentRow;
            $this->template->recalculated = true;
            $this->redrawControl('segmentCount');
        } else {
            $this->redirect("show", $id);
        }
    }

    public function renderDownload($id, $format, $extension)
    {
        $segmentRow = $this->loadSegment($id);
        $segment = $this->segmentFactory->buildSegment($segmentRow->code);

        $keys = false;
        $i = 1;

        $excelSpreadSheet = $this->excelFactory->createExcel('Segment - ' . $segmentRow->name);
        $excelSpreadSheet->getActiveSheet()->setTitle('Segment ' . $segmentRow->id);

        $segment->process(function ($row) use (&$excelSpreadSheet, &$keys, &$i) {
            if (!$keys) {
                $keys = true;
                $tableData[] = array_keys((array) $row);
            }
            $tableData[] = array_values((array) $row);
            $excelSpreadSheet->getActiveSheet()->fromArray($tableData, null, 'A' . $i);
            $i += count($tableData);
        }, 0);

        if ($format == 'CSV') {
            $writer = new Csv($excelSpreadSheet);
            $writer->setDelimiter(';');
            $writer->setUseBOM(true);
            $writer->setEnclosure('"');
        } elseif ($format == 'Excel2007') {
            $writer = new Xlsx($excelSpreadSheet);
        } elseif ($format == 'OpenDocument') {
            $writer = new Ods($excelSpreadSheet);
        } else {
            throw new \Exception('');
        }

        $filePath = APP_ROOT . 'content/segments/segment-'.$id.'-export-' . date('y-m-d-h-i-s').'.'.$extension;

        if (!is_writable(dirname($filePath))) {
            $this->flashMessage('Cannot create segment. Problem with permissions. ('.$filePath.')', 'danger');
            $this->redirect('show', $id);
        }
        $writer->save($filePath);
        $this->sendResponse(new FileResponse($filePath));
        $this->terminate();
    }

    private function loadSegment($id)
    {
        $segment = $this->segmentsRepository->find($id);
        if (!$segment) {
            $this->flashMessage($this->translator->translate('segment.messages.segment_not_found'), 'danger');
            $this->redirect('default');
        }
        return $segment;
    }

    public function createComponentSegmentForm()
    {
        $id = null;
        if (isset($this->params['id'])) {
            $id = intval($this->params['id']);
        }

        $form = $this->segmentFormFactory->create($id);

        $this->segmentFormFactory->onSave = function ($segment) {
            $this->flashMessage($this->translator->translate('segment.messages.segment_was_created'));
            $this->redirect('show', $segment->id);
        };
        $this->segmentFormFactory->onUpdate = function ($segment) {
            $this->flashMessage($this->translator->translate('segment.messages.segment_was_updated'));
            $this->redirect('show', $segment->id);
        };
        return $form;
    }

    protected function createComponentSegmentValuesGraph(GoogleLineGraphGroupControlFactoryInterface $factory)
    {
        $graphDataItem1 = new GraphDataItem();
        $graphDataItem1->setCriteria((new Criteria())
            ->setTableName('segments_values')
            ->setTimeField('date')
            ->setWhere('AND segment_id=' . intval($this->params['id']))
            ->setValueField('MAX(value)')
            ->setStart('-1 month'))
            ->setName('Segment values');

        $control = $factory->create()
            ->setGraphTitle('Segment values')
            ->setGraphHelp('Segment values')
            ->addGraphDataItem($graphDataItem1);

        return $control;
    }

    public function renderEmbed($id)
    {
        $this->template->crmHost = $this->getHttpRequest()->getUrl()->getBaseUrl() . "/api/v1";
        $this->template->segmentAuth = 'Bearer ' . $this->accessToken->getToken($this->getHttpRequest());

        $segment = $this->segmentsRepository->find($id);
        $this->template->segment = $segment;
    }

    public function handleDelete($segmentId)
    {
        $segment = $this->segmentsRepository->find($segmentId);
        $this->segmentsRepository->softDelete($segment);

        $this->flashMessage($this->translator->translate('segment.messages.segment_was_deleted'));
        $this->redirect('default');
    }
}
