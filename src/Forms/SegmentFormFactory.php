<?php

namespace Crm\SegmentModule\Forms;

use Contributte\Translation\Translator;
use Crm\SegmentModule\Criteria\Generator;
use Crm\SegmentModule\Repository\SegmentGroupsRepository;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\TextInput;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Tomaj\Form\Renderer\BootstrapRenderer;

class SegmentFormFactory
{
    public $onUpdate;

    public $onSave;

    public function __construct(
        private SegmentsRepository $segmentsRepository,
        private SegmentGroupsRepository $segmentGroupsRepository,
        private Generator $generator,
        private Translator $translator
    ) {
    }

    public function create($id): Form
    {
        $defaults = [];
        $locked = false;
        if (isset($id)) {
            $segment = $this->segmentsRepository->find($id);
            $defaults = $segment->toArray();
            $locked = $segment->locked;
        }

        $form = new Form;

        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);
        $form->addProtection();

        $form->addText('name', 'segment.fields.name')
            ->setRequired('segment.required.name')
            ->setHtmlAttribute('placeholder', 'segment.placeholder.name')
            ->setDisabled($locked);

        $form->addSelect('version', 'segment.fields.version', ['1' => '1', '2' => '2'])
            ->setRequired('segment.required.name')
            ->setDisabled($locked);

        $form->addText('code', 'segment.fields.code')
            ->setRequired('segment.required.code')
            ->setHtmlAttribute('placeholder', 'segment.placeholder.code')
            ->setDisabled($locked)
            ->addRule(function (TextInput $control) use (&$segment) {
                $newValue = $control->getValue();
                if ($segment && $segment->code === $newValue) {
                    return true;
                }
                return $this->segmentsRepository->findByCode($control->getValue()) === null;
            }, 'segment.copy.validation.code');

        $form->addSelect('segment_group_id', 'segment.fields.segment_group_id', $this->segmentGroupsRepository->all()->fetchPairs('id', 'name'))
            ->setDisabled($locked);

        $form->addText('table_name', 'segment.fields.table_name')
            ->setRequired('segment.required.table_name')
            ->setHtmlAttribute('placeholder', 'segment.placeholder.table_name')
            ->setDisabled($locked);

        $form->addTextArea('query_string', 'segment.fields.query_string', 30, 10)
            ->setRequired()
            ->setDisabled($locked)
            ->getControlPrototype()
                ->addAttributes(['class' => 'ace', 'data-lang' => 'sql']);

        $form->addTextArea('fields', 'segment.fields.query_fields', 30, 3)
            ->setRequired()
            ->setDisabled($locked)
            ->getControlPrototype()
                ->addAttributes(['class' => 'ace', 'data-lang' => 'sql']);

        $form->addHidden('segment_id', $id);

        $form->addTextArea('criteria', 'segment.fields.criteria', 30, 8)
            ->setDisabled($locked);

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

        if (isset($values['criteria']) && $values['criteria']) {
            try {
                $parsedCriteria = Json::decode($values['criteria'], Json::FORCE_ARRAY);
            } catch (JsonException $ex) {
                $form['criteria']->addError($ex->getMessage());
                return;
            }

            $values['query_string'] = $this->generator->process($values['table_name'], $parsedCriteria);
        }

        if ($id) {
            $row = $this->segmentsRepository->find($id);
            if ($row->locked) {
                $form->addError($this->translator->translate('segment.edit.messages.segment_locked'));
                return;
            }
            $this->segmentsRepository->update($row, $values);
            $this->onUpdate->__invoke($row);
        } else {
            $group = $this->segmentGroupsRepository->find($values['segment_group_id']);
            $row = $this->segmentsRepository->add($values['name'], $values['version'], $values['code'], $values['table_name'], $values['fields'], $values['query_string'], $group, $values['criteria'] ? $values['criteria'] : null);
            $this->onSave->__invoke($row);
        }
    }
}
