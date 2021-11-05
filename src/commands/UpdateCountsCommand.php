<?php

namespace Crm\SegmentModule\Commands;

use Crm\SegmentModule\Repository\SegmentsRepository;
use Crm\SegmentModule\Repository\SegmentsValuesRepository;
use Crm\SegmentModule\SegmentFactoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;

class UpdateCountsCommand extends Command
{
    /** @var SegmentFactoryInterface */
    private $segmentFactory;

    /** @var SegmentsRepository */
    private $segmentsRepository;

    /** @var SegmentsValuesRepository  */
    private $segmentsValuesRepository;

    public function __construct(
        SegmentFactoryInterface $segmentFactory,
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
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('');
        $output->writeln('<info>***** SEGMENTS COUNT *****</info>');
        $output->writeln('');

        foreach ($this->segmentsRepository->all() as $segmentRow) {
            Debugger::timer('recalculate_segment');
            try {
                $output->write("Updating count for segment <info>{$segmentRow->code}</info>: ");
                $segment = $this->segmentFactory->buildSegment($segmentRow->code);
                $count = $segment->totalCount();
                $recalculateTime = round(Debugger::timer('recalculate_segment'), 2);

                $this->segmentsValuesRepository->cacheSegmentCount($segmentRow, $count, $recalculateTime);

                $output->writeln("OK (" . $recalculateTime . "s)");
            } catch (\Exception $e) {
                if (!isset($recalculateTime)) {
                    $recalculateTime = round(Debugger::timer('recalculate_segment'), 2);
                }
                Debugger::log($e, Debugger::EXCEPTION);
                $output->writeln("ERR (" . $recalculateTime . "s): " . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
