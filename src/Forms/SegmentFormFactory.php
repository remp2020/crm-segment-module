<?php

namespace Crm\SegmentModule\Forms;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\UI\Form;
use Crm\SegmentModule\Events\BeforeSegmentCodeUpdateEvent;
use Crm\SegmentModule\Exceptions\SegmentQueryValidationException;
use Crm\SegmentModule\Models\Config;
use Crm\SegmentModule\Models\Criteria\Generator;
use Crm\SegmentModule\Models\Segment;
use Crm\SegmentModule\Models\SegmentConfig;
use Crm\SegmentModule\Models\SegmentException;
use Crm\SegmentModule\Models\SegmentFactoryInterface;
use Crm\SegmentModule\Models\SegmentQueryValidator;
use Crm\SegmentModule\Models\SimulableSegmentInterface;
use Crm\SegmentModule\Repositories\SegmentCodeInUseException;
use Crm\SegmentModule\Repositories\SegmentGroupsRepository;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Latte\Engine;
use Latte\Essential\TranslatorExtension;
use League\Event\Emitter;
use Nette\Forms\Controls\TextInput;
use Nette\Utils\ArrayHash;
use Nette\Utils\Html;
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
        private Translator $translator,
        private Config $segmentConfig,
        private SegmentFactoryInterface $segmentFactory,
        private Emitter $emitter,
        private SegmentQueryValidator $segmentQueryValidator,
    ) {
    }

    public function create($id): Form
    {
        $defaults = [];
        $locked = false;
        $referencingSegmentCode = null;
        if (isset($id)) {
            $segment = $this->segmentsRepository->find($id);
            $defaults = $segment->toArray();
            $locked = $segment->locked || $segment->deleted_at;

            try {
                $this->emitter->emit(new BeforeSegmentCodeUpdateEvent($segment));
            } catch (SegmentCodeInUseException $exception) {
                $referencingSegmentCode = $exception->getReferencingSegmentCode();
            }
        } else {
            $segment = null;
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

        $codeInput = $form->addText('code', 'segment.fields.code')
            ->setRequired('segment.required.code')
            ->setHtmlAttribute('placeholder', 'segment.placeholder.code')
            ->setDisabled($locked || $referencingSegmentCode)
            ->addRule(function (TextInput $control) use ($segment) {
                $newValue = $control->getValue();
                if ($segment && $segment->code === $newValue) {
                    return true;
                }
                return $this->segmentsRepository->findByCode($control->getValue()) === null;
            }, 'segment.copy.validation.code');

        if ($referencingSegmentCode) {
            $codeInput->setOption('description', "Cannot edit segment code, it's referenced by other segment '{$referencingSegmentCode}'");
        }


        $form->addSelect('segment_group_id', 'segment.fields.segment_group_id', $this->segmentGroupsRepository->all()->fetchPairs('id', 'name'))
            ->setDisabled($locked);

        $form->addText('table_name', 'segment.fields.table_name')
            ->setRequired('segment.required.table_name')
            ->setHtmlAttribute('placeholder', 'segment.placeholder.table_name')
            ->setDisabled($locked);


        $engine = new Engine();
        $engine->addExtension(new TranslatorExtension($this->translator));
        $queryStringHelp = $engine->renderToString(
            __DIR__ . DIRECTORY_SEPARATOR . 'queryStringHelp.latte',
            [
                'segmentNestingEnabled' => $this->segmentConfig->isSegmentNestingEnabled()
            ]
        );

        $form->addTextArea('query_string', 'segment.fields.query_string', 30, 10)
            ->setOption('description', Html::fromHtml($queryStringHelp))
            ->setRequired()
            ->setHtmlAttribute('data-codeeditor', 'sql')
            ->setDisabled($locked);

        $form->addTextArea('fields', 'segment.fields.query_fields', 30, 3)
            ->setRequired()
            ->setDisabled($locked)
            ->getControlPrototype()
                ->addAttributes(['class' => 'ace', 'data-lang' => 'sql']);

        $form->addHidden('segment_id', $id);

        $form->addTextArea('criteria', 'segment.fields.criteria', 30, 8)
            ->setHtmlAttribute('data-codeeditor', 'javascript')
            ->setDisabled($locked);

        $form->addTextArea('note', 'segment.fields.note', 30, 8)
            ->setDisabled($locked);

        if ($this->hasQuerySimulationAbility()) {
            $form->addCheckbox('skip_query_validation', 'segment.fields.skip_query_validation')
                ->setDefaultValue(false)
                ->setDisabled($locked);
        }

        $form->setDefaults($defaults);

        $form->addSubmit('send', $this->translator->translate('system.save'))
            ->setDisabled($locked)
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('system.save'));

        $form->onValidate[] = [$this, 'validateSegmentQuery'];
        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded(Form $form, ArrayHash $values): void
    {
        $id = $values['segment_id'];
        unset($values['segment_id'], $values['skip_query_validation']);

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
            try {
                $this->segmentsRepository->update($row, (array) $values);
            } catch (SegmentCodeInUseException $exception) {
                $form->addError($this->translator->translate('segment.messages.errors.code_update_referenced_by_other_segment', [
                    'code' => $exception->getReferencingSegmentCode()
                ]));
                return;
            }

            $this->onUpdate->__invoke($row);
        } else {
            $group = $this->segmentGroupsRepository->find($values['segment_group_id']);
            $row = $this->segmentsRepository->add($values['name'], $values['version'], $values['code'], $values['table_name'], $values['fields'], $values['query_string'], $group, $values['criteria'] ? $values['criteria'] : null, $values['note']);
            $this->onSave->__invoke($row);
        }
    }

    public function validateSegmentQuery(Form $form, ArrayHash $values): void
    {
        $skipQueryValidation = isset($values['skip_query_validation']) && $values['skip_query_validation'] === true;
        if ($skipQueryValidation) {
            return;
        }

        $segmentConfig = new SegmentConfig(
            $values['table_name'],
            $values['query_string'],
            $values['fields']
        );

        $segment = $this->segmentFactory->buildSegment($segmentConfig);

        try {
            $query = $segment instanceof Segment ? $segment->query() : $values['query_string'];
            $this->segmentQueryValidator->validate($query);
            return;
        } catch (SegmentQueryValidationException $exception) {
            $form->addError($this->translator->translate('segment.edit.messages.segment_invalid', [
                'reason' => $exception->getMessage()
            ]));
        }

        if (!($segment instanceof SimulableSegmentInterface)) {
            return;
        }

        try {
            $segment->simulate();
        } catch (SegmentException $exception) {
            $form->addError($this->translator->translate('segment.edit.messages.segment_invalid', [
                'reason' => $exception->getMessage()
            ]));
        }
    }

    private function hasQuerySimulationAbility(): bool
    {
        $segment = $this->segmentFactory->buildSegment(new SegmentConfig('', '', '')); // dummy segment just to check if it's simulable
        return $segment instanceof SimulableSegmentInterface;
    }
}
