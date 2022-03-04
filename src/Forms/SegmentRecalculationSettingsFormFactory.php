<?php

namespace Crm\SegmentModule\Forms;

use Crm\SegmentModule\Repository\SegmentsRepository;
use Kdyby\Translation\Translator;
use Nette\Application\UI\Form;
use Nette\Utils\Json;
use Tomaj\Form\Renderer\BootstrapRenderer;

class SegmentRecalculationSettingsFormFactory
{
    private $segmentsRepository;

    public $onUpdate;

    public $onSave;

    private $translator;

    public function __construct(
        SegmentsRepository $segmentsRepository,
        Translator $translator
    ) {
        $this->segmentsRepository = $segmentsRepository;
        $this->translator = $translator;
    }

    public function create($id)
    {
        $defaults = [];
        $segment = $this->segmentsRepository->find($id);
        if ($segment->cache_count_periodicity) {
            $defaults = Json::decode($segment->cache_count_periodicity, true);
        }
        $defaults['segment_id'] = $id;
        $locked = $segment->locked;

        $form = new Form;

        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);
        $form->getElementPrototype()->addAttributes(['class' => 'form-horizontal']);
        $form->addProtection();

        $form->addInteger('amount')
            ->getControlPrototype()
            ->addAttributes(['class' => 'form-control']);

        $form->addSelect('unit', '', [
            'minutes' => $this->translator->trans('segment.recalculation_settings.minutes'),
            'hours' => $this->translator->trans('segment.recalculation_settings.hours'),
            'days' => $this->translator->trans('segment.recalculation_settings.days'),
        ])
            ->getControlPrototype()
            ->addAttributes(['class' => 'form-control']);

        $form->addHidden('segment_id');

        $form->setDefaults($defaults);

        $form->addSubmit('send', $this->translator->translate('system.save'))
            ->setDisabled($locked)
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('system.save'));

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $id = $values['segment_id'];
        unset($values['segment_id']);

        $periodicity = [];

        if ($values['unit'] && $values['amount']) {
            $periodicity['amount'] = $values['amount'];
            $periodicity['unit'] = $values['unit'];
        }

        $row = $this->segmentsRepository->find($id);
        $this->segmentsRepository->update($row, [
            'cache_count_periodicity' => !empty($periodicity) ? Json::encode($periodicity) : null,
        ]);
        $this->onUpdate->__invoke($row);
    }
}
