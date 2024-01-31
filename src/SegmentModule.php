<?php

namespace Crm\SegmentModule;

use Contributte\Translation\Translator;
use Crm\ApiModule\Models\Api\ApiRoutersContainerInterface;
use Crm\ApiModule\Models\Authorization\AdminLoggedAuthorization;
use Crm\ApiModule\Models\Authorization\BearerTokenAuthorization;
use Crm\ApiModule\Models\Router\ApiIdentifier;
use Crm\ApiModule\Models\Router\ApiRoute;
use Crm\ApplicationModule\Application\CommandsContainerInterface;
use Crm\ApplicationModule\Application\Managers\AssetsManager;
use Crm\ApplicationModule\Application\Managers\SeederManager;
use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\Models\Criteria\CriteriaStorage;
use Crm\ApplicationModule\Models\Event\LazyEventEmitter;
use Crm\ApplicationModule\Models\Menu\MenuContainerInterface;
use Crm\ApplicationModule\Models\Menu\MenuItem;
use Crm\SegmentModule\Api\CheckApiHandler;
use Crm\SegmentModule\Api\CountsHandler;
use Crm\SegmentModule\Api\CreateOrUpdateSegmentHandler;
use Crm\SegmentModule\Api\CriteriaHandler;
use Crm\SegmentModule\Api\ItemsHandler;
use Crm\SegmentModule\Api\ListApiHandler;
use Crm\SegmentModule\Api\ListGroupsHandler;
use Crm\SegmentModule\Api\RelatedHandler;
use Crm\SegmentModule\Api\SegmentsListApiHandler;
use Crm\SegmentModule\Api\ShowSegmentHandler;
use Crm\SegmentModule\Api\UsersApiHandler;
use Crm\SegmentModule\Commands\CompressSegmentsValues;
use Crm\SegmentModule\Commands\ProcessCriteriaSegmentsCommand;
use Crm\SegmentModule\Commands\UpdateCountsCommand;
use Crm\SegmentModule\Events\BeforeSegmentCodeUpdateEvent;
use Crm\SegmentModule\Events\BeforeSegmentCodeUpdateHandler;
use Crm\SegmentModule\Models\Config;
use Crm\SegmentModule\Seeders\ConfigSeeder;
use Crm\SegmentModule\Seeders\SegmentsSeeder;
use Crm\SegmentModule\Segment\SegmentCriteria;
use Nette\DI\Container;

class SegmentModule extends CrmModule
{
    public function __construct(
        Container $container,
        Translator $translator,
        private Config $segmentConfig,
    ) {
        parent::__construct($container, $translator);
    }

    public function registerAdminMenuItems(MenuContainerInterface $menuContainer)
    {
        $mainMenu = new MenuItem(
            $this->translator->translate('segment.menu.segments'),
            ':Segment:StoredSegments:default',
            'fa fa-sliders-h',
            650,
            true
        );

        $menuContainer->attachMenuItem($mainMenu);
    }

    public function registerCommands(CommandsContainerInterface $commandsContainer)
    {
        $commandsContainer->registerCommand($this->getInstance(UpdateCountsCommand::class));
        $commandsContainer->registerCommand($this->getInstance(ProcessCriteriaSegmentsCommand::class));
        $commandsContainer->registerCommand($this->getInstance(CompressSegmentsValues::class));
    }

    public function registerApiCalls(ApiRoutersContainerInterface $apiRoutersContainer)
    {
        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'user-segments', 'list'),
                ListApiHandler::class,
                BearerTokenAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'user-segments', 'users'),
                UsersApiHandler::class,
                BearerTokenAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'user-segments', 'check'),
                CheckApiHandler::class,
                BearerTokenAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'segments', 'list'),
                SegmentsListApiHandler::class,
                BearerTokenAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'segments', 'groups'),
                ListGroupsHandler::class,
                AdminLoggedAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'segments', 'criteria'),
                CriteriaHandler::class,
                AdminLoggedAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'segments', 'detail'),
                CreateOrUpdateSegmentHandler::class,
                AdminLoggedAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'segments', 'show'),
                ShowSegmentHandler::class,
                AdminLoggedAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'segments', 'counts'),
                CountsHandler::class,
                AdminLoggedAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'segments', 'items'),
                ItemsHandler::class,
                AdminLoggedAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'segments', 'related'),
                RelatedHandler::class,
                AdminLoggedAuthorization::class
            )
        );
    }

    public function registerSeeders(SeederManager $seederManager)
    {
        $seederManager->addSeeder($this->getInstance(SegmentsSeeder::class));
        $seederManager->addSeeder($this->getInstance(ConfigSeeder::class));
    }

    public function registerAssets(AssetsManager $assetsManager)
    {
        $assetsManager->copyAssets(__DIR__ . '/assets/segmenter', 'layouts/admin/segmenter');
    }

    public function registerSegmentCriteria(CriteriaStorage $criteriaStorage)
    {
        if ($this->segmentConfig->isSegmentNestingEnabled()) {
            $criteriaStorage->register('users', 'segment', $this->getInstance(SegmentCriteria::class));
        }
    }

    public function registerLazyEventHandlers(LazyEventEmitter $lazyEventEmitter)
    {
        $lazyEventEmitter->addListener(
            BeforeSegmentCodeUpdateEvent::class,
            BeforeSegmentCodeUpdateHandler::class
        );
    }
}
