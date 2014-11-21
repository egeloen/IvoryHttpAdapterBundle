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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Ivory http adapter configuration.
 *
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
                ->always(function($config) {
                    if (empty($config['adapters'])) {
                        $config['adapters'] = array(
                            'default' => array(
                                'type'        => 'socket',
                                'configs'     => array(),
                                'subscribers' => array(),
                            )
                        );
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
     * Creates the adapters node.
     *
     * @return \Symfony\Component\Config\Definition\Builder\NodeDefinition The adapters node.
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
     * Creates the configs node.
     *
     * @return \Symfony\Component\Config\Definition\Builder\NodeDefinition The configs node.
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
     * Creates the subscribers node.
     *
     * @return \Symfony\Component\Config\Definition\Builder\NodeDefinition The subscribers node.
     */
    private function createSubscribersNode()
    {
        return $this->createNode('subscribers')
            ->addDefaultsIfNotSet()
            ->children()
                ->append($this->createBasicAuthSubscriberNode())
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
     * Creates the basic auth subscriber node.
     *
     * @return \Symfony\Component\Config\Definition\Builder\NodeDefinition The basic auth subscriber node.
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
     * Creates the redirect subscriber node.
     *
     * @return \Symfony\Component\Config\Definition\Builder\NodeDefinition The redirect subscriber node.
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
     * Creates a node.
     *
     * @param string $name The node name.
     *
     * @return \Symfony\Component\Config\Definition\Builder\NodeDefinition The node.
     */
    private function createNode($name)
    {
        return $this->createTreeBuilder()->root($name);
    }

    /**
     * Creates a tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder.
     */
    private function createTreeBuilder()
    {
        return new TreeBuilder();
    }
}
