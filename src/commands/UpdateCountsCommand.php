<?php

namespace Crm\SegmentModule\Commands;

use Crm\SegmentModule\Repository\SegmentsRepository;
use Crm\SegmentModule\Repository\SegmentsValuesRepository;
use Crm\SegmentModule\SegmentFactory;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCountsCommand extends Command
{
    /** @var SegmentFactory */
    private $segmentFactory;

    /** @var SegmentsRepository */
    private $segmentsRepository;

    /** @var SegmentsValuesRepository  */
    private $segmentsValuesRepository;

    public function __construct(
        SegmentFactory $segmentFactory,
        SegmentsRepository $segmentsRepository,
        SegmentsValuesRepository $segmentsValuesRepository
    ) {
        parent::__construct();
        $this->segmentsRepository = $segmentsRepository;
        $this->segmentFactory = $segmentFactory;
        $this->segmentsValuesRepository = $segmentsValuesRepository;
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('segment:actualize_counts')
            ->setDescription('Actualize segment counts')
        ;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('');
        $output->writeln('<info>***** SEGMENTS COUNT *****</info>');
        $output->writeln('');

        foreach ($this->segmentsRepository->all() as $segmentRow) {
            $segment = $this->segmentFactory->buildSegment($segmentRow->code);
            $count = $segment->totalCount();
            $this->segmentsRepository->update($segmentRow, ['cache_count' => $count]);

            $this->segmentsValuesRepository->add($segmentRow, new DateTime(), $count);

            $output->writeln("Actualized segment <info>{$segmentRow->code}</info>");
        }
    }
}
