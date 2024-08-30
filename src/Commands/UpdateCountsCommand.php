<?php

namespace Crm\SegmentModule\Commands;

use Crm\ApplicationModule\Commands\DecoratedCommandTrait;
use Crm\ApplicationModule\Models\Redis\RedisClientFactory;
use Crm\ApplicationModule\Models\Redis\RedisClientTrait;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\SegmentModule\DI\SegmentRecalculationConfig;
use Crm\SegmentModule\Models\SegmentFactoryInterface;
use Crm\SegmentModule\Models\SegmentWidgetInterface;
use Crm\SegmentModule\Presenters\StoredSegmentsPresenter;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Crm\SegmentModule\Repositories\SegmentsValuesRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;
use Tracy\ILogger;

class UpdateCountsCommand extends Command
{
    use RedisClientTrait, DecoratedCommandTrait;

    private const CACHE_TIME_KEY = 'update_segment_counts_command_time';

    private const CACHE_SEGMENT_RECOUNT_KEY = 'segments_recount_lock';

    private DateTime $now;

    public function __construct(
        private LazyWidgetManager $lazyWidgetManager,
        private SegmentFactoryInterface $segmentFactory,
        private SegmentsRepository $segmentsRepository,
        private SegmentsValuesRepository $segmentsValuesRepository,
        private SegmentRecalculationConfig $segmentRecalculationConfig,
        RedisClientFactory $redisClientFactory
    ) {
        parent::__construct();
        $this->redisClientFactory = $redisClientFactory;
    }

    protected function configure()
    {
        $this->setName('segment:actualize_counts')
            ->setDescription('Actualize segment counts')
            ->addOption(
                'no_widgets',
                null,
                InputOption::VALUE_NONE,
                "Prevents recalculation of widget statistics available on the segment detail screen.",
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->now = new DateTime();
        $preventWidgetRecalculation = $input->getOption('no_widgets');

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

                if (!$preventWidgetRecalculation) {
                    // recalculation for widgets copied from StoredSegmentsPresenter::handleRecalculate
                    $ids = $segment->getIds();
                    $widgets = $this->lazyWidgetManager->getWidgets(StoredSegmentsPresenter::SHOW_STATS_PANEL_WIDGET_PLACEHOLDER);
                    foreach ($widgets as $widget) {
                        if (!($widget instanceof SegmentWidgetInterface)) {
                            throw new \Exception(sprintf("registered widget instance doesn't implement SegmentWidgetInterface: %s", gettype($widget)));
                        }
                        $widget->recalculate($segmentRow, $ids);
                    }
                }

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
        if (count($segmentIdsBeingRecounted) > 0) {
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
