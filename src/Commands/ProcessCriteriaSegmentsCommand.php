<?php

namespace Crm\SegmentModule\Commands;

use Crm\ApplicationModule\Commands\DecoratedCommandTrait;
use Crm\SegmentModule\Criteria\Generator;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Nette\Utils\Json;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessCriteriaSegmentsCommand extends Command
{
    use DecoratedCommandTrait;

    private $segmentsRepository;

    private $generator;

    public function __construct(
        SegmentsRepository $segmentsRepository,
        Generator $generator
    ) {
        parent::__construct();
        $this->segmentsRepository = $segmentsRepository;
        $this->generator = $generator;
    }

    protected function configure()
    {
        $this->setName('segment:process-criteria')
            ->setDescription('Process segment criteria');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $segments = $this->segmentsRepository->all()->where(['version' => 2]);
        foreach ($segments as $segment) {
            $output->write("Processing <comment>{$segment->code}</comment>: ");
            $query = $this->generator->process($segment->table_name, Json::decode($segment->criteria, Json::FORCE_ARRAY));
            if ($query) {
                $this->segmentsRepository->update($segment, ['query_string' => $query]);
            }
            $output->writeln("OK");
        }

        $output->writeln("Done");

        return Command::SUCCESS;
    }
}
