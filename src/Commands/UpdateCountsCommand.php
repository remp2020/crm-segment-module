<?php

namespace Crm\SegmentModule\Commands;

use Crm\ApplicationModule\ActiveRow;
use Crm\ApplicationModule\Commands\DecoratedCommandTrait;
use Crm\ApplicationModule\RedisClientFactory;
use Crm\ApplicationModule\RedisClientTrait;
use Crm\SegmentModule\DI\SegmentRecalculationConfig;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Crm\SegmentModule\Repository\SegmentsValuesRepository;
use Crm\SegmentModule\SegmentFactoryInterface;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;
use Tracy\ILogger;

class UpdateCountsCommand extends Command
{
    use RedisClientTrait, DecoratedCommandTrait;

    private const CACHE_TIME_KEY = 'update_segment_counts_command_time';

    private const CACHE_SEGMENT_RECOUNT_KEY = 'segments_recount_lock';

    private DateTime $now;

    private SegmentFactoryInterface $segmentFactory;

    private SegmentsRepository $segmentsRepository;

    private SegmentsValuesRepository $segmentsValuesRepository;

    private SegmentRecalculationConfig $segmentRecalculationConfig;

    public function __construct(
        SegmentFactoryInterface $segmentFactory,
        SegmentsRepository $segmentsRepository,
        SegmentsValuesRepository $segmentsValuesRepository,
        SegmentRecalculationConfig $segmentRecalculationConfig,
        RedisClientFactory $redisClientFactory
    ) {
        parent::__construct();
        $this->segmentsRepository = $segmentsRepository;
        $this->segmentFactory = $segmentFactory;
        $this->segmentsValuesRepository = $segmentsValuesRepository;
        $this->segmentRecalculationConfig = $segmentRecalculationConfig;
        $this->redisClientFactory = $redisClientFactory;
    }

    protected function configure()
    {
        $this->setName('segment:actualize_counts')
            ->setDescription('Actualize segment counts');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->now = new DateTime();

        $segments = $this->getSegmentsToRecount();
        $this->sortSegmentsByPeriodicity($segments);

        // mark segments as being recounted
        foreach ($segments as $segment) {
            $this->redis()->hset(self::CACHE_SEGMENT_RECOUNT_KEY, $segment->id, $this->now);
        }

        // start recount
        $minRecountInterval = $this->segmentRecalculationConfig->getDefaultRecalculationPeriodicityInterval();
        $segmentWithMinInterval = null;
        foreach ($segments as $segmentRow) {
            $recountInterval = $this->segmentPeriodicityInterval($segmentRow);
            if ($this->isShorterInterval($recountInterval, $minRecountInterval)) {
                $minRecountInterval = $recountInterval;
                $segmentWithMinInterval = $segmentRow;
            }

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
            } finally {
                // make sure redis key is deleted when recount finishes/fails
                $this->redis()->hdel(self::CACHE_SEGMENT_RECOUNT_KEY, [$segmentRow->id]);
            }
        }

        $this->checkCommandRunPeriodicity($minRecountInterval, $segmentWithMinInterval);
        return Command::SUCCESS;
    }

    /**
     * Is $a shorter than $b?
     * @param \DateInterval $a
     * @param \DateInterval $b
     *
     * @return bool
     */
    private function isShorterInterval(\DateInterval $a, \DateInterval $b): bool
    {
        $now = new \DateTime();
        $timeA = (clone $now)->add($a);
        $timeB = (clone $now)->add($b);

        return $timeA < $timeB;
    }

    private function getSegmentsToRecount(): array
    {
        $lockThresholdTime = (clone $this->now)->modify('-30 min');

        // remove expired locks
        $segmentRecountLocks = $this->redis()->hgetall(self::CACHE_SEGMENT_RECOUNT_KEY);
        $segmentIdsBeingRecounted = [];
        foreach ($segmentRecountLocks as $segmentId => $updateCountStart) {
            if (new \DateTime($updateCountStart) < $lockThresholdTime) {
                $this->redis()->hdel(self::CACHE_SEGMENT_RECOUNT_KEY, [$segmentId]);
            } else {
                $segmentIdsBeingRecounted[] = $segmentId;
            }
        }

        $query = $this->segmentsRepository->all();
        if ($segmentRecountLocks && count($segmentRecountLocks) > 0) {
            $query->where('id NOT IN (?)', $segmentIdsBeingRecounted);
        }
        $segments = $query->fetchAll();

        // filter only those that according to their periodicity should be recalculated
        return array_filter($segments, fn ($segment) => $this->shouldRecalculate($segment));
    }

    private function shouldRecalculate(ActiveRow $segmentRow): bool
    {
        $periodicity = SegmentRecalculationConfig::DEFAULT_RECALCULATION_PERIODICITY;
        if ($segmentRow->cache_count_periodicity) {
            $periodicity = Json::decode($segmentRow->cache_count_periodicity, true);
        }

        if ($periodicity['unit'] === 'days' && $segmentRow->cache_count_updated_at) {
            $recalculateTime = DateTime::createFromFormat('H:i', $this->segmentRecalculationConfig->getDailyRecalculationTime());
            $recalculateOn = $segmentRow->cache_count_updated_at->modify('+' . $periodicity['amount'] . ' days');
            if ($this->now < $recalculateOn || $this->now < $recalculateTime) {
                return false;
            }
        }

        if ($periodicity['unit'] === 'hours' && $segmentRow->cache_count_updated_at) {
            $recalculateTime = DateTime::createFromFormat('i', $this->segmentRecalculationConfig->getHourlyRecalculationMinute());
            $recalculateOn = $segmentRow->cache_count_updated_at->modify('+' . $periodicity['amount'] . ' hours');
            if ($this->now < $recalculateOn || $this->now < $recalculateTime) {
                return false;
            }
        }

        if ($periodicity['unit'] === 'minutes' && $segmentRow->cache_count_updated_at) {
            $recalculateOn = $segmentRow->cache_count_updated_at->modify('+' . $periodicity['amount'] . ' minutes');
            if ($this->now < $recalculateOn) {
                return false;
            }
        }

        return true;
    }

    private function segmentPeriodicityInterval($segmentRow): \DateInterval
    {
        $periodicity = SegmentRecalculationConfig::DEFAULT_RECALCULATION_PERIODICITY;
        if ($segmentRow->cache_count_periodicity) {
            $periodicity = Json::decode($segmentRow->cache_count_periodicity, true);
        }
        return \DateInterval::createFromDateString($periodicity['amount'] . ' ' . $periodicity['unit']);
    }

    private function sortSegmentsByPeriodicity(array &$segments): void
    {
        $now = new DateTime();
        // ua - sort by value, keep index (segment ID) association
        uasort($segments, function ($a, $b) use ($now) {
            // shorter interval will result in older date
            // older dates will be sorted before newer dates
            $timeA = (clone $now)->add($this->segmentPeriodicityInterval($a));
            $timeB = (clone $now)->add($this->segmentPeriodicityInterval($b));
            return $timeA <=> $timeB;
        });
    }

    private function checkCommandRunPeriodicity(\DateInterval $shortestRecalculationPeriod, ?ActiveRow $associatedSegment): void
    {
        $commandRunTime = $this->redis()->get(self::CACHE_TIME_KEY);
        if ($commandRunTime) {
            $warningThreshold = (clone $this->now)
                // 2x
                ->sub($shortestRecalculationPeriod)
                ->sub($shortestRecalculationPeriod);
            $lastCommandRunTime = new DateTime($commandRunTime);

            if ($lastCommandRunTime < $warningThreshold) {
                $commandsRunDiffInterval = $lastCommandRunTime->diff($this->now);

                if ($associatedSegment) {
                    Debugger::log(
                        "UpdateCountsCommand - command run frequency is too low. " .
                        "Segment ID={$associatedSegment->id} has recalculation period set to " . $this->dateIntervalString($shortestRecalculationPeriod) .
                        ", but the command last run was at " .
                        (new DateTime($commandRunTime))->format(DATE_RFC3339) .
                        " ( " . $this->dateIntervalString($commandsRunDiffInterval) . " ago).",
                        ILogger::WARNING
                    );
                } else {
                    Debugger::log(
                        "UpdateCountsCommand - command run frequency is too low. " .
                        "Default recalculation period set to " . $this->dateIntervalString($shortestRecalculationPeriod) .
                        ", but the command last run was at " .
                        (new DateTime($commandRunTime))->format(DATE_RFC3339) .
                        " ( " . $this->dateIntervalString($commandsRunDiffInterval) . " ago).",
                        ILogger::WARNING
                    );
                }
            }
        }
        $this->redis()->set(self::CACHE_TIME_KEY, $this->now);
    }

    private function dateIntervalString(\DateInterval $dateInterval): string
    {
        return $dateInterval->format("%m month(s), %d day(s), %i min(s), %s sec(s)");
    }
}
