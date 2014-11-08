<?php

/*
 * This file is part of the Ivory Http Adapter bundle package.
 *
 * (c) Eric GELOEN <geloen.eric@gmail.com>
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 */

namespace Ivory\HttpAdapterBundle\DependencyInjection\Compiler;

use Ivory\HttpAdapterBundle\DependencyInjection\IvoryHttpAdapterExtension;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Register listener compiler pass.
 *
 * @author GeLo <geloen.eric@gmail.com>
 */
class RegisterListenerCompilerPass implements CompilerPassInterface
{
    const PARAMETER = 'ivory.http_adapters';
    const SUBSCRIBER_TAG = 'ivory.http_adapter.subscriber';
    const LISTENER_TAG = 'ivory.http_adapter.listener';

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $this->processListeners($container);
        $this->processSubscribers($container);
    }

    /**
     * Processes listeners.
     *
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container The container builder.
     */
    private function processListeners(ContainerBuilder $container)
    {
        foreach ($container->findTaggedServiceIds(self::LISTENER_TAG) as $id => $listeners) {
            foreach ($listeners as $listener) {
                foreach ($this->getAdapters($listener, $container) as $adapter) {
                    $container
                        ->getDefinition($this->getEventDispatcherName($adapter))
                        ->addMethodCall(
                            'addListenerService',
                            array(
                                $listener['event'],
                                array($id, $listener['method']),
                                isset($listener['priority']) ? $listener['priority'] : 0
                            )
                        );
                }
            }
        }
    }

    /**
     * Processes subscribers.
     *
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container The container builder.
     */
    private function processSubscribers(ContainerBuilder $container)
    {
        foreach ($container->findTaggedServiceIds(self::SUBSCRIBER_TAG) as $id => $subscribers) {
            foreach ($subscribers as $subscriber) {
                foreach ($this->getAdapters($subscriber, $container) as $adapter) {
                    $container
                        ->getDefinition($this->getEventDispatcherName($adapter))
                        ->addMethodCall('addSubscriberService', array($id, $container->getDefinition($id)->getClass()));
                }
            }
        }
    }

    /**
     * Gets the adapters.
     *
     * @param array                                                   $configuration The configuration.
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container     The container.
     *
     * @return array The adapters.
     */
    private function getAdapters(array $configuration, ContainerBuilder $container)
    {
        if (!isset($configuration['adapter'])) {
            $configuration['adapter'] = $container->getParameter(self::PARAMETER);
        }

        return (array) $configuration['adapter'];
    }

    /**
     * Gets the event dispatcher service name.
     *
     * @param string $adapter The adapter name.
     *
     * @return string The event dispatcher name.
     */
    private function getEventDispatcherName($adapter)
    {
        return IvoryHttpAdapterExtension::createServiceName($adapter.'.event_dispatcher');
    }
}
