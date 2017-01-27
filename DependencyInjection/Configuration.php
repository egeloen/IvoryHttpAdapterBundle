<?php

/*
 * This file is part of the Ivory Http Adapter bundle package.
 *
 * (c) Eric GELOEN <geloen.eric@gmail.com>
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 */

namespace Ivory\HttpAdapterBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author GeLo <geloen.eric@gmail.com>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = $this->createTreeBuilder();
        $treeBuilder
            ->root('ivory_http_adapter')
            ->beforeNormalization()
                ->always(function ($config) {
                    if (empty($config['adapters'])) {
                        $config['adapters'] = ['default' => ['type' => 'socket']];
                    }

                    if (!isset($config['default'])) {
                        reset($config['adapters']);
                        $config['default'] = key($config['adapters']);
                    }

                    return $config;
                })
            ->end()
            ->children()
                ->scalarNode('default')->end()
                ->append($this->createAdaptersNode())
                ->append($this->createConfigsNode())
                ->append($this->createSubscribersNode())
            ->end();

        return $treeBuilder;
    }

    /**
     * @return NodeDefinition
     */
    private function createAdaptersNode()
    {
        return $this->createNode('adapters')
            ->useAttributeAsKey('name')
            ->prototype('array')
                ->children()
                    ->scalarNode('type')->isRequired()->end()
                    ->append($this->createConfigsNode())
                    ->append($this->createSubscribersNode())
                ->end()
            ->end();
    }

    /**
     * @return NodeDefinition
     */
    private function createConfigsNode()
    {
        return $this->createNode('configs')
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('protocol_version')->end()
                ->booleanNode('keep_alive')->end()
                ->scalarNode('encoding_type')->end()
                ->scalarNode('boundary')->end()
                ->scalarNode('timeout')->end()
                ->scalarNode('user_agent')->end()
            ->end();
    }

    /**
     * @return NodeDefinition
     */
    private function createSubscribersNode()
    {
        return $this->createNode('subscribers')
            ->canBeEnabled()
            ->children()
                ->append($this->createBasicAuthSubscriberNode())
                ->append($this->createCacheSubscriberNode())
                ->append($this->createRedirectSubscriberNode())
                ->scalarNode('cookie')->end()
                ->scalarNode('history')->end()
                ->scalarNode('logger')->end()
                ->booleanNode('retry')->end()
                ->booleanNode('status_code')->end()
                ->scalarNode('stopwatch')->end()
            ->end();
    }

    /**
     * @return NodeDefinition
     */
    private function createBasicAuthSubscriberNode()
    {
        return $this->createNode('basic_auth')
            ->children()
                ->scalarNode('username')->isRequired()->end()
                ->scalarNode('password')->isRequired()->end()
                ->scalarNode('matcher')->end()
            ->end();
    }

    /**
     * @return NodeDefinition
     */
    private function createCacheSubscriberNode()
    {
        return $this->createNode('cache')
            ->children()
                ->scalarNode('adapter')->isRequired()->end()
                ->integerNode('lifetime')->defaultValue(null)->end()
                ->booleanNode('exception')->defaultValue(true)->end()
            ->end();
    }

    /**
     * @return NodeDefinition
     */
    private function createRedirectSubscriberNode()
    {
        return $this->createNode('redirect')
            ->children()
                ->scalarNode('max')->end()
                ->booleanNode('strict')->end()
                ->booleanNode('throw_exception')->end()
            ->end();
    }

    /**
     * @param string $name
     *
     * @return NodeDefinition|ArrayNodeDefinition
     */
    private function createNode($name)
    {
        return $this->createTreeBuilder()->root($name);
    }

    /**
     * @return TreeBuilder
     */
    private function createTreeBuilder()
    {
        return new TreeBuilder();
    }
}
