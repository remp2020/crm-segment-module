<?php

namespace Crm\SegmentModule\Commands;

use Crm\SegmentModule\Repository\SegmentsValuesRepository;
use DateInterval;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CompressSegmentsValues extends Command
{
    private $segmentsValuesRepository;

    public function __construct(
        SegmentsValuesRepository $segmentsValuesRepository
    ) {
        parent::__construct();
        $this->segmentsValuesRepository = $segmentsValuesRepository;
    }

    protected function configure()
    {
        $this->setName('segment:compress_segments_values')
            ->addOption(
                'from',
                null,
                InputOption::VALUE_REQUIRED
            )
            ->addOption(
                'to',
                null,
                InputOption::VALUE_REQUIRED
            )
            ->setDescription('Compress segments values (in given dates interval) by keeping only one value per hour for each segment.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('');
        $output->writeln('<info>***** COMPRESSING SEGMENTS VALUES *****</info>');
        $output->writeln('');

        $fromString = $input->getOption('from');
        if (! $fromString) {
            $output->writeln('Required option --from=DATE is missing');
            return;
        }
        $from = DateTime::createFromFormat('Y-m-d', $fromString)->setTime(0, 0, 0, 0);
        if (! $from) {
            $output->writeln("$fromString is not a valid date, accepted format is YYYY-MM-DD.");
            return 1;
        }

        $toString = $input->getOption('to');
        if (! $toString) {
            $output->writeln('Required option --to=DATE is missing');
            return 1;
        }
        $to = DateTime::createFromFormat('Y-m-d', $toString)->setTime(0, 0, 0, 0);
        if (! $to) {
            $output->writeln("$toString is not a valid date, accepted format is YYYY-MM-DD.");
            return 1;
        }

        $oneDay = DateInterval::createFromDateString('1 day');

        $dayIterator = clone $from;

        $totalDeleted = 0;

        while ($dayIterator <= $to) {
            $totalDeleted += $this->compress($dayIterator, $output);
            $dayIterator = $dayIterator->add($oneDay);
        }

        $output->writeln("Compressing finished, $totalDeleted record(s) deleted.");

        return 0;
    }

    private function compress(DateTime $day, OutputInterface $output): int
    {
        $output->writeln("Compressing values for $day");

        $ids = $this->segmentsValuesRepository->getTable()
            ->select('MIN(id) AS id')
            ->where('DATE(`date`) = ?', $day)
            ->group('HOUR(`date`), segment_id')
            ->fetchAssoc('id=id');

        if (empty($ids)) {
            return 0;
        }

        return $this->segmentsValuesRepository->getTable()
            ->where('DATE(`date`) = ?', $day)
            ->where('id NOT IN (?)', $ids)
            ->delete();
    }
}
