<?php

declare(strict_types=1);

namespace Neika\SymfonyBundle;

use Neika\Sdk\Contract\ChatbotDataSourceInterface;
use Neika\Sdk\Contract\ChatbotToolInterface;
use Neika\Sdk\Contract\ToolChoiceLoaderInterface;
use Neika\SymfonyBundle\DependencyInjection\Compiler\ChatbotDefinitionNamePass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class NeikaSymfonyBundle extends AbstractBundle
{
    public const TRANSLATION_DOMAIN = 'neika';

    protected string $extensionAlias = 'neika';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new ChatbotDefinitionNamePass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('backend_url')->defaultValue('')->end()
                ->arrayNode('widget')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('cdn_url')->defaultValue('')->end()
                        ->booleanNode('defer')->defaultTrue()->end()
                    ->end()
                ->end()
            ->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(ChatbotToolInterface::class)
            ->addTag('neika.tool');
        $container->registerForAutoconfiguration(ChatbotDataSourceInterface::class)
            ->addTag('neika.source');
        $container->registerForAutoconfiguration(ToolChoiceLoaderInterface::class)
            ->addTag('neika.tool_choice_loader');

        $configurator->parameters()
            ->set('neika.backend_url', $config['backend_url'])
            ->set('neika.widget.enabled', $config['widget']['enabled'])
            ->set('neika.widget.cdn_url', $config['widget']['cdn_url'])
            ->set('neika.widget.defer', $config['widget']['defer']);

        $configurator->import(__DIR__ . '/../config/services.php');
    }
}
