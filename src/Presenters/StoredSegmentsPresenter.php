<?php

namespace Crm\SegmentModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\ExcelFactory;
use Crm\ApplicationModule\Graphs\Criteria;
use Crm\ApplicationModule\Graphs\GraphDataItem;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\SegmentModule\Forms\SegmentFormFactory;
use Crm\SegmentModule\Forms\SegmentRecalculationSettingsFormFactory;
use Crm\SegmentModule\Repository\SegmentGroupsRepository;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Crm\SegmentModule\Repository\SegmentsValuesRepository;
use Crm\SegmentModule\SegmentFactoryInterface;
use Crm\SegmentModule\SegmentWidgetInterface;
use Crm\UsersModule\Auth\Access\AccessToken;
use Nette\Application\Responses\CallbackResponse;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\TextInput;
use Nette\Utils\Strings;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tomaj\Form\Renderer\BootstrapRenderer;
use Tracy\Debugger;

class StoredSegmentsPresenter extends AdminPresenter
{
    public const SHOW_STATS_PANEL_WIDGET_PLACEHOLDER = 'segment.detail.statspanel.row';

    private $segmentsRepository;

    private $segmentsValuesRepository;

    private $segmentFactory;

    private $segmentFormFactory;

    private $excelFactory;

    private $segmentGroupsRepository;

    private $accessToken;

    private WidgetManager $widgetManager;

    private LazyWidgetManager $lazyWidgetManager;

    public function __construct(
        SegmentsRepository $segmentsRepository,
        SegmentsValuesRepository $segmentsValuesRepository,
        SegmentFactoryInterface $segmentFactory,
        SegmentFormFactory $segmentFormFactory,
        ExcelFactory $excelFactory,
        SegmentGroupsRepository $segmentGroupsRepository,
        AccessToken $accessToken,
        WidgetManager $widgetManager,
        LazyWidgetManager $lazyWidgetManager
    ) {
        parent::__construct();

        $this->segmentsRepository = $segmentsRepository;
        $this->segmentsValuesRepository = $segmentsValuesRepository;
        $this->segmentFactory = $segmentFactory;
        $this->segmentFormFactory = $segmentFormFactory;
        $this->excelFactory = $excelFactory;
        $this->segmentGroupsRepository = $segmentGroupsRepository;
        $this->accessToken = $accessToken;
        $this->widgetManager = $widgetManager;
        $this->lazyWidgetManager = $lazyWidgetManager;
    }

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
        $this->template->segmentGroups = $this->segmentGroupsRepository->all();
        $this->template->segments = $this->segmentsRepository->all();
        $this->template->deletedSegments = $this->segmentsRepository->deleted();

        $segmentSlowRecalculateThreshold = $this->applicationConfig->get('segment_slow_recalculate_threshold');
        if (!is_numeric($segmentSlowRecalculateThreshold) || $segmentSlowRecalculateThreshold < 0) {
            Debugger::log(
                'Bad value in configuration for `segment_slow_recalculate_threshold`. Value must be positive number.',
                Debugger::WARNING
            );
        } else {
            $this->template->segmentSlowRecalculateThreshold = $this->applicationConfig->get('segment_slow_recalculate_threshold');
        }
    }

    /**
     * @admin-access-level write
     */
    public function renderNew($version = 2)
    {
        $this->template->version = $version;
    }

    /**
     * @admin-access-level write
     */
    public function renderEdit($id, $version = null)
    {
        $segment = $this->segmentsRepository->find($id);
        if ($segment->locked) {
            $this->flashMessage($this->translator->translate('segment.edit.messages.segment_locked'), 'warning');
        }
        $this->template->segment = $segment;
        $this->template->version = $version == null ? $segment->version : $version;
    }

    /**
     * @admin-access-level read
     */
    public function renderShow($id, $data = false)
    {
        $segmentRow = $this->loadSegment($id);
        $this->template->segment = $segmentRow;
        $this->template->showData = $data;

        $segmentSlowRecalculateThreshold = $this->applicationConfig->get('segment_slow_recalculate_threshold');
        if (!is_numeric($segmentSlowRecalculateThreshold) || $segmentSlowRecalculateThreshold < 0) {
            Debugger::log(
                'Bad value in configuration for `segment_slow_recalculate_threshold`. Value must be positive number.',
                Debugger::WARNING
            );
        } else {
            $this->template->segmentSlowRecalculateThreshold = $this->applicationConfig->get('segment_slow_recalculate_threshold');
        }

        $segment = $this->segmentFactory->buildSegment($segmentRow->code);

        $tableData = [];
        $displayFields = false;

        if ($data) {
            $segment->process(function ($row) use (&$tableData, &$displayFields) {
                if (!$displayFields) {
                    $displayFields = array_keys((array)$row);
                }
                $tableData[] = (array) $row;
            }, PHP_INT_MAX);
        }

        $this->template->statsPanelWidgetPlaceholder = self::SHOW_STATS_PANEL_WIDGET_PLACEHOLDER;
        $this->template->fields = $displayFields;
        $this->template->data = $tableData;
    }

    /**
     * @admin-access-level write
     */
    public function handleRecalculate(int $id)
    {
        Debugger::timer('recalculate_segment');
        // load segment
        $segmentRow = $this->loadSegment($id);
        $segment = $this->segmentFactory->buildSegment($segmentRow->code);

        // store cached count
        $ids = $segment->getIds();
        $recalculateTime = round(Debugger::timer('recalculate_segment'), 2);
        $count = count($ids);
        $this->segmentsValuesRepository->cacheSegmentCount($segmentRow, $count, $recalculateTime);

        $widgets = $this->widgetManager->getWidgets(self::SHOW_STATS_PANEL_WIDGET_PLACEHOLDER);
        foreach ($widgets as $widget) {
            if (!($widget instanceof SegmentWidgetInterface)) {
                throw new \Exception(sprintf("registered widget instance doesn't implement SegmentWidgetInterface: %s", gettype($widget)));
            }
            $widget->recalculate($segmentRow, $ids);
        }

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

    /**
     * @admin-access-level read
     */
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

        $fileName = 'segment-' . $id . '-export-' . date('y-m-d-h-i-s') . '.' . $extension;
        $this->getHttpResponse()->addHeader('Content-Encoding', 'windows-1250');
        $this->getHttpResponse()->addHeader('Content-Type', 'application/octet-stream; charset=windows-1250');
        $this->getHttpResponse()->addHeader('Content-Disposition', "attachment; filename=" . $fileName);

        $response = new CallbackResponse(function () use ($writer) {
            $writer->save("php://output");
        });

        $this->sendResponse($response);
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

    /**
     * @admin-access-level write
     */
    public function renderEmbed($id)
    {
        $this->template->crmHost = $this->getHttpRequest()->getUrl()->getBaseUrl() . "api/v1";
        $this->template->segmentAuth = 'Bearer ' . $this->accessToken->getToken($this->getHttpRequest());

        $segment = $this->segmentsRepository->find($id);
        $this->template->segment = $segment;
    }

    protected function createComponentSegmentRecalculationSettingsForm(
        SegmentRecalculationSettingsFormFactory $segmentPeriodicityFormFactory
    ) {
        $id = null;
        if (isset($this->params['id'])) {
            $id = (int) $this->params['id'];
        }

        $form = $segmentPeriodicityFormFactory->create($id);

        $segmentPeriodicityFormFactory->onSave = function ($segment) {
            $this->flashMessage($this->translator->translate('segment.messages.segment_recalculation_settings_saved'));
            $this->redirect('show', $segment->id);
        };
        $segmentPeriodicityFormFactory->onUpdate = function ($segment) {
            $this->flashMessage($this->translator->translate('segment.messages.segment_recalculation_settings_saved'));
            $this->redirect('show', $segment->id);
        };
        return $form;
    }

    /**
     * @admin-access-level write
     */
    public function handleDelete($segmentId)
    {
        $segment = $this->segmentsRepository->find($segmentId);
        $this->segmentsRepository->softDelete($segment);

        $this->flashMessage($this->translator->translate('segment.messages.segment_was_deleted'));
        $this->redirect('default');
    }

    protected function createComponentCopySegmentForm(): Form
    {
        $form = new Form;

        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapRenderer());
        $form->getElementPrototype()->addClass('ajax');

        $form->addText('name', 'segment.fields.name')
            ->setRequired('segment.required.name')
            ->addRule(function (TextInput $control) {
                return $this->segmentsRepository->findByCode(Strings::webalize($control->getValue())) === null;
            }, 'segment.copy.validation.name');

        $form->addHidden('segment_id');

        $form->addSubmit('send', 'system.save')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('system.save'));

        $form->onSubmit[] = function (Form $form) {
            $this->redrawControl('copySegmentModal');
        };

        $form->onSuccess[] = function (Form $form) {
            $values = $form->getValues();

            $segment = $this->segmentsRepository->find($values['segment_id']);

            $newSegment = $this->segmentsRepository->add(
                $values['name'],
                $segment->version,
                Strings::webalize($values['name']),
                $segment->table_name,
                $segment->fields,
                $segment->query_string,
                $segment->segment_group,
                $segment->criteria,
            );

            $this->redirect('edit', $newSegment->id);
        };

        return $form;
    }
}
