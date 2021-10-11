<?php

namespace Crm\SegmentModule;

use Crm\ApiModule\Api\ApiRoutersContainerInterface;
use Crm\ApiModule\Router\ApiIdentifier;
use Crm\ApiModule\Router\ApiRoute;
use Crm\ApplicationModule\AssetsManager;
use Crm\ApplicationModule\Commands\CommandsContainerInterface;
use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\Menu\MenuContainerInterface;
use Crm\ApplicationModule\Menu\MenuItem;
use Crm\ApplicationModule\SeederManager;
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
        $commandsContainer->registerCommand($this->getInstance(\Crm\SegmentModule\Commands\UpdateCountsCommand::class));
        $commandsContainer->registerCommand($this->getInstance(\Crm\SegmentModule\Commands\ProcessCriteriaSegmentsCommand::class));
        $commandsContainer->registerCommand($this->getInstance(\Crm\SegmentModule\Commands\CompressSegmentsValues::class));
    }

    public function registerApiCalls(ApiRoutersContainerInterface $apiRoutersContainer)
    {
        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'user-segments', 'list'),
                \Crm\SegmentModule\Api\ListApiHandler::class,
                \Crm\ApiModule\Authorization\BearerTokenAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'user-segments', 'users'),
                \Crm\SegmentModule\Api\UsersApiHandler::class,
                \Crm\ApiModule\Authorization\BearerTokenAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'user-segments', 'check'),
                \Crm\SegmentModule\Api\CheckApiHandler::class,
                \Crm\ApiModule\Authorization\BearerTokenAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'segments', 'list'),
                \Crm\SegmentModule\Api\SegmentsListApiHandler::class,
                \Crm\ApiModule\Authorization\BearerTokenAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'segments', 'groups'),
                \Crm\SegmentModule\Api\ListGroupsHandler::class,
                \Crm\ApiModule\Authorization\AdminLoggedAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'segments', 'criteria'),
                \Crm\SegmentModule\Api\CriteriaHandler::class,
                \Crm\ApiModule\Authorization\AdminLoggedAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'segments', 'detail'),
                \Crm\SegmentModule\Api\CreateOrUpdateSegmentHandler::class,
                \Crm\ApiModule\Authorization\AdminLoggedAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'segments', 'show'),
                \Crm\SegmentModule\Api\ShowSegmentHandler::class,
                \Crm\ApiModule\Authorization\AdminLoggedAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'segments', 'counts'),
                \Crm\SegmentModule\Api\CountsHandler::class,
                \Crm\ApiModule\Authorization\AdminLoggedAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'segments', 'related'),
                \Crm\SegmentModule\Api\RelatedHandler::class,
                \Crm\ApiModule\Authorization\AdminLoggedAuthorization::class
            )
        );
    }

    public function registerSeeders(SeederManager $seederManager)
    {
        $seederManager->addSeeder($this->getInstance(SegmentsSeeder::class));
    }

    public function registerAssets(AssetsManager $assetsManager)
    {
        $assetsManager->copyAssets(__DIR__ . '/assets/segmenter', 'layouts/admin/segmenter');
    }
}
