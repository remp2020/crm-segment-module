<?php

namespace Crm\SegmentModule\DI;

use Contributte\Translation\DI\TranslationProviderInterface;

use Crm\SegmentModule\Models\Config;
use Nette\Application\IPresenterFactory;
use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;

final class SegmentModuleExtension extends CompilerExtension implements TranslationProviderInterface
{
    public function loadConfiguration()
    {
        // load services from config and register them to Nette\DI Container
        $this->compiler->loadDefinitionsFromConfig(
            $this->loadFromFile(__DIR__.'/../config/config.neon')['services']
        );
    }

    public function getConfigSchema(): \Nette\Schema\Schema
    {
        return Expect::structure([
            // segment nesting feature requires default SegmentInterface implementation to be used
            'segment_nesting' => Expect::bool()->default(false)->dynamic(),
        ]);
    }

    public function beforeCompile()
    {
        $builder = $this->getContainerBuilder();
        // load presenters from extension to Nette
        $builder->getDefinition($builder->getByType(IPresenterFactory::class))
            ->addSetup('setMapping', [['Segment' => 'Crm\SegmentModule\Presenters\*Presenter']]);

        foreach ($builder->findByType(Config::class) as $definition) {
            $definition->addSetup('setSegmentNestingEnabled', [$this->config->segment_nesting]);
        }
    }

    /**
     * Return array of directories, that contain resources for translator.
     * @return string[]
     */
    public function getTranslationResources(): array
    {
        return [__DIR__ . '/../lang/'];
    }
}
