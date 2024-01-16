<?php

namespace Crm\SegmentModule;

use Crm\ApiModule\Api\ApiRoutersContainerInterface;
use Crm\ApiModule\Authorization\AdminLoggedAuthorization;
use Crm\ApiModule\Authorization\BearerTokenAuthorization;
use Crm\ApiModule\Router\ApiIdentifier;
use Crm\ApiModule\Router\ApiRoute;
use Crm\ApplicationModule\AssetsManager;
use Crm\ApplicationModule\Commands\CommandsContainerInterface;
use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\Menu\MenuContainerInterface;
use Crm\ApplicationModule\Menu\MenuItem;
use Crm\ApplicationModule\SeederManager;
use Crm\SegmentModule\Api\CheckApiHandler;
use Crm\SegmentModule\Api\CountsHandler;
use Crm\SegmentModule\Api\CreateOrUpdateSegmentHandler;
use Crm\SegmentModule\Api\CriteriaHandler;
use Crm\SegmentModule\Api\ListApiHandler;
use Crm\SegmentModule\Api\ListGroupsHandler;
use Crm\SegmentModule\Api\RelatedHandler;
use Crm\SegmentModule\Api\SegmentsListApiHandler;
use Crm\SegmentModule\Api\ShowSegmentHandler;
use Crm\SegmentModule\Api\UsersApiHandler;
use Crm\SegmentModule\Commands\CompressSegmentsValues;
use Crm\SegmentModule\Commands\ProcessCriteriaSegmentsCommand;
use Crm\SegmentModule\Commands\UpdateCountsCommand;
use Crm\SegmentModule\Seeders\ConfigSeeder;
use Crm\SegmentModule\Seeders\SegmentsSeeder;

class SegmentModule extends CrmModule
{
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
}
