<?php

namespace Crm\SegmentModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroup\GoogleLineGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Models\Exports\ExcelFactory;
use Crm\ApplicationModule\Models\Graphs\Criteria;
use Crm\ApplicationModule\Models\Graphs\GraphDataItem;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\ApplicationModule\UI\Form;
use Crm\SegmentModule\Forms\AdminFilterFormFactory;
use Crm\SegmentModule\Forms\SegmentFormFactory;
use Crm\SegmentModule\Forms\SegmentRecalculationSettingsFormFactory;
use Crm\SegmentModule\Models\AdminFilterFormData;
use Crm\SegmentModule\Models\Config\SegmentSlowRecalculateThresholdFactory;
use Crm\SegmentModule\Models\Segment;
use Crm\SegmentModule\Models\SegmentException;
use Crm\SegmentModule\Models\SegmentFactoryInterface;
use Crm\SegmentModule\Models\SegmentWidgetInterface;
use Crm\SegmentModule\Repositories\SegmentCodeInUseException;
use Crm\SegmentModule\Repositories\SegmentGroupsRepository;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Crm\SegmentModule\Repositories\SegmentsValuesRepository;
use Crm\UsersModule\Models\Auth\Access\AccessToken;
use Nette\Application\Attributes\Persistent;
use Nette\Application\Responses\CallbackResponse;
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

    #[Persistent]
    public $formData = [];

    public function __construct(
        private SegmentsRepository $segmentsRepository,
        private SegmentsValuesRepository $segmentsValuesRepository,
        private SegmentFactoryInterface $segmentFactory,
        private SegmentFormFactory $segmentFormFactory,
        private ExcelFactory $excelFactory,
        private SegmentGroupsRepository $segmentGroupsRepository,
        private AccessToken $accessToken,
        private LazyWidgetManager $lazyWidgetManager,
        private SegmentSlowRecalculateThresholdFactory $segmentSlowRecalculateThresholdFactory,
        private AdminFilterFormFactory $adminFilterFormFactory,
        private AdminFilterFormData $adminFilterFormData,
    ) {
        parent::__construct();
    }

    public function startup()
    {
        parent::startup();
        $this->adminFilterFormData->parse($this->formData);
    }

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
        $segments = $this->adminFilterFormData->getFilteredSegments(deleted: false)->fetchAll();

        $segmentGroups = [];
        foreach ($this->segmentGroupsRepository->all() as $segmentGroup) {
            $segmentGroups[$segmentGroup->code] = $segmentGroup;
        }

        $groupedSegments = [];
        foreach ($segments as $segment) {
            $groupedSegments[$segment->segment_group->code][] = $segment;
        }

        $deletedSegments = $this->adminFilterFormData->getFilteredSegments(deleted: true)->fetchAll();

        $this->template->segmentGroups = $segmentGroups;
        $this->template->groupedSegments = $groupedSegments;
        $this->template->deletedSegments = $deletedSegments;
        $this->template->segmentSlowRecalculateThresholdInSeconds = $this->segmentSlowRecalculateThresholdFactory->build()->thresholdInSeconds;
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

        if ($segmentRow?->deleted_at) {
            return $this->redirect('StoredSegments:edit', $id);
        }

        $this->template->segment = $segmentRow;
        $this->template->showData = $data;
        $this->template->segmentSlowRecalculateThresholdInSeconds = $this->segmentSlowRecalculateThresholdFactory->build()->thresholdInSeconds;

        $segment = $this->segmentFactory->buildSegment($segmentRow->code);

        $tableData = [];
        $displayFields = false;

        if ($data) {
            try {
                $processingCallback = function ($row) use (&$tableData, &$displayFields) {
                    if (!$displayFields) {
                        $displayFields = array_keys((array)$row);
                    }
                    $tableData[] = (array) $row;
                };

                if ($segment instanceof Segment) {
                    $segment->process($processingCallback, PHP_INT_MAX);
                } else {
                    $segment->process($processingCallback);
                }
            } catch (SegmentException $exception) {
                $errorMessage = $this->translator->translate('segment.messages.errors.segment_data_show_error', [
                    'reason' => $exception->getMessage(),
                ]);
                $this->flashMessage($errorMessage, 'error');
            }
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
        try {
            $ids = $segment->getIds();
        } catch (SegmentException) {
            if ($this->isAjax()) {
                $this->template->recalculated = 'error';
                $this->redrawControl('segmentCount');
                return;
            }

            $this->flashMessage($this->translator->translate('segment.messages.segment_count_recalculation_error'), 'error');
            $this->redirect('show', $id);
        }

        $recalculateTime = round(Debugger::timer('recalculate_segment'), 2);
        $count = count($ids);
        $this->segmentsValuesRepository->cacheSegmentCount($segmentRow, $count, $recalculateTime);

        $widgets = $this->lazyWidgetManager->getWidgets(self::SHOW_STATS_PANEL_WIDGET_PLACEHOLDER);
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
            $this->template->recalculated = 'success';
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

        $processingCallback = function ($row) use (&$excelSpreadSheet, &$keys, &$i) {
            if (!$keys) {
                $keys = true;
                $tableData[] = array_keys((array) $row);
            }
            $tableData[] = array_values((array) $row);
            $excelSpreadSheet->getActiveSheet()->fromArray($tableData, null, 'A' . $i);
            $i += count($tableData);
        };

        if ($segment instanceof Segment) {
            $segment->process($processingCallback, 0);
        } else {
            $segment->process($processingCallback);
        }

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

    public function createComponentAdminFilterForm()
    {
        $form = $this->adminFilterFormFactory->create();
        $form->setDefaults($this->adminFilterFormData->getFormValues());

        $this->adminFilterFormFactory->onFilter = function (array $values) {
            $this->redirect($this->action, ['formData' => array_map(function ($item) {
                if ($item === '' || $item === []) {
                    return null;
                }
                return $item;
            }, $values)]);
        };
        $this->adminFilterFormFactory->onCancel = function (array $emptyValues) {
            $this->redirect($this->action, ['formData' => $emptyValues]);
        };

        return $form;
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
        try {
            $this->segmentsRepository->softDelete($segment);
            $this->flashMessage($this->translator->translate('segment.messages.segment_was_deleted'));
            $this->redirect('default');
        } catch (SegmentCodeInUseException $exception) {
            $this->flashMessage($this->translator->translate('segment.messages.errors.delete_referenced_by_other_segment', [
                'code' => $exception->getReferencingSegmentCode()
            ]), 'error');
            $this->redirect('this');
        }
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
