<?php

namespace Crm\SegmentModule\Forms;

use Crm\ApplicationModule\UI\Form;
use Crm\SegmentModule\Repositories\SegmentGroupsRepository;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Nette\Localization\Translator;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;

class AdminFilterFormFactory
{
    public $onFilter;

    public $onCancel;

    public function __construct(
        private readonly Translator $translator,
        private readonly SegmentsRepository $segmentsRepository,
        private readonly SegmentGroupsRepository $segmentGroupsRepository,
    ) {
    }

    public function create()
    {
        $form = new Form;
        $form->setRenderer(new BootstrapInlineRenderer());
        $form->setTranslator($this->translator);

        $sourceTables = $this->segmentsRepository->getTable()
            ->select('table_name')
            ->group('table_name')
            ->fetchPairs('table_name', 'table_name');

        $segmentGroups = $this->segmentGroupsRepository->all()
            ->fetchPairs('code', 'name');

        $form->addText('name', 'segment.admin.admin_filter_form.name.label');
        $form->addText('code', 'segment.admin.admin_filter_form.code.label');

        $form->addMultiSelect('group', 'segment.admin.admin_filter_form.group.label', $segmentGroups)
            ->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->addMultiSelect('table_name', 'segment.admin.admin_filter_form.table_name.label', $sourceTables)
            ->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->addSubmit('send', 'segment.admin.admin_filter_form.submit')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-filter"></i> ' . $this->translator->translate('segment.admin.admin_filter_form.submit'));

        $form->addSubmit('cancel', 'segment.admin.admin_filter_form.cancel_filter')->onClick[] = function () use ($form) {
            $emptyDefaults = array_fill_keys(array_keys((array) $form->getComponents()), null);
            $this->onCancel->__invoke($emptyDefaults);
        };

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $this->onFilter->__invoke((array) $values);
    }
}
