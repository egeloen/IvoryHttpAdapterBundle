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

use Ivory\HttpAdapterBundle\DependencyInjection\Compiler\RegisterListenerCompilerPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

/**
 * Ivory http adapter extension.
 *
 * @author GeLo <geloen.eric@gmail.com>
 */
class IvoryHttpAdapterExtension extends ConfigurableExtension
{
    /**
     * Creates a service name.
     *
     * @param string|null $suffix The suffix.
     *
     * @return string The service name.
     */
    public static function createServiceName($suffix = null)
    {
        return 'ivory.http_adapter'.($suffix !== null ? '.' : null).$suffix;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadInternal(array $config, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('adapters.xml');

        if ($container->getParameter('kernel.debug')) {
            $loader->load('data_collector.xml');
        }

        $this->loadAdapters($config, $container, $loader);
    }

    /**
     * Loads the adapters.
     *
     * @param array                                                   $config    The configuration.
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container The container.
     * @param \Symfony\Component\Config\Loader\LoaderInterface        $loader    The loader.
     */
    private function loadAdapters(array $config, ContainerBuilder $container, LoaderInterface $loader)
    {
        foreach ($config['adapters'] as $name => $adapter) {
            $this->loadAdapter($name, $adapter, $config['configs'], $config['subscribers'], $container);
            $this->loadSubscribers($name, $adapter, $config['subscribers'], $container, $loader);
        }

        $container->setParameter(RegisterListenerCompilerPass::PARAMETER, array_keys($config['adapters']));
        $container->setAlias(self::createServiceName(), self::createServiceName($config['default']));
    }

    /**
     * Loads an adapter.
     *
     * @param string                                                  $name        The name.
     * @param array                                                   $adapter     The adapter.
     * @param array                                                   $configs     The global configuration.
     * @param array                                                   $subscribers The global subscribers.
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container   The container.
     */
    private function loadAdapter($name, array $adapter, array $configs, array $subscribers, ContainerBuilder $container)
    {
        $httpAdapterName = self::createServiceName($name);
        $httpAdapter = $httpAdapterName.'.adapter';
        $configuration = $httpAdapterName.'.configuration';

        $container->setDefinition($configuration, $this->createConfigurationDefinition($adapter, $configs));
        $container->setDefinition($httpAdapter, $this->createAdapterDefinition($adapter, $configuration));

        if ($adapter['subscribers']['enabled'] || $subscribers['enabled'] || $container->getParameter('kernel.debug')) {
            $eventDispatcherHttpAdapter = $httpAdapterName.'.wrapper.event_dispatcher';
            $eventDispatcher = $httpAdapterName.'.event_dispatcher';

            $container->setDefinition(
                $eventDispatcher,
                new DefinitionDecorator(self::createServiceName('event_dispatcher'))
            );

            $container->setDefinition(
                $eventDispatcherHttpAdapter,
                new Definition(
                    'Ivory\HttpAdapter\EventDispatcherHttpAdapter',
                    [new Reference($httpAdapter), new Reference($eventDispatcher)]
                )
            );

            $httpAdapter = $eventDispatcherHttpAdapter;
        }

        if ($container->getParameter('kernel.debug')) {
            $stopwatchHttpAdapter = $httpAdapterName.'.wrapper.stopwatch';

            $container->setDefinition(
                $stopwatchHttpAdapter,
                new Definition(
                    'Ivory\HttpAdapter\StopwatchHttpAdapter',
                    [new Reference($httpAdapter), new Reference('debug.stopwatch')]
                )
            );

            $httpAdapter = $stopwatchHttpAdapter;
        }

        $container->setAlias($httpAdapterName, $httpAdapter);
    }

    /**
     * Loads the subscribers.
     *
     * @param string                                                  $name        The name.
     * @param array                                                   $adapter     The adapter.
     * @param array                                                   $subscribers The global subscribers.
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container   The container.
     * @param \Symfony\Component\Config\Loader\LoaderInterface        $loader      The loader.
     */
    private function loadSubscribers(
        $name,
        array $adapter,
        array $subscribers,
        ContainerBuilder $container,
        LoaderInterface $loader
    ) {
        if ($container->getParameter('kernel.debug')) {
            $subscribers['debug'] = null;
        }

        unset($adapter['subscribers']['enabled']);
        unset($subscribers['enabled']);

        foreach (array_merge($subscribers, $adapter['subscribers']) as $subscriberName => $subscriber) {
            $loader->load('subscribers/'.$subscriberName.'.xml');

            $container->setDefinition(
                self::createServiceName($name.'.subscriber.'.$subscriberName),
                $this->createSubscriberDefinition($name, $subscriberName, $subscriber, $container)
            );
        }
    }

    /**
     * Creates an adapter definition.
     *
     * @param array  $adapter       The adapter.
     * @param string $configuration The configuration service name.
     *
     * @return \Symfony\Component\DependencyInjection\DefinitionDecorator The adapter definition.
     */
    private function createAdapterDefinition(array $adapter, $configuration)
    {
        $definition = new DefinitionDecorator(self::createServiceName('abstract'));
        $definition->setArguments(array($adapter['type']));
        $definition->addMethodCall('setConfiguration', array(new Reference($configuration)));

        return $definition;
    }

    /**
     * Creates a configuration definition.
     *
     * @param array  $adapter The adapter.
     * @param array  $configs The global configuration.
     *
     * @return \Symfony\Component\DependencyInjection\DefinitionDecorator The configuration definition.
     */
    private function createConfigurationDefinition(array $adapter, array $configs)
    {
        $definition = new DefinitionDecorator(self::createServiceName('configuration'));

        foreach (array_merge($configs, $adapter['configs']) as $property => $value) {
            $definition->addMethodCall($this->getMethod($property), array($value));
        }

        return $definition;
    }

    /**
     * Creates a subscriber definition.
     *
     * @param string                                                  $adapterName    The adatper name.
     * @param string                                                  $subscriberName The subscriber name.
     * @param array|string                                            $configuration  The configuration.
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container      The container builder.
     *
     * @return \Symfony\Component\DependencyInjection\DefinitionDecorator The subscriber definition.
     */
    private function createSubscriberDefinition(
        $adapterName,
        $subscriberName,
        $configuration,
        ContainerBuilder $container
    ) {
        $parent = self::createServiceName('subscriber.'.$subscriberName);

        $subscriber = new DefinitionDecorator($parent);
        $subscriber->setClass($container->getDefinition($parent)->getClass());
        $subscriber->addTag(RegisterListenerCompilerPass::SUBSCRIBER_TAG, array('adapter' => $adapterName));

        switch ($subscriberName) {
            case 'basic_auth':
                $this->configureBasicAuthSubscriberDefinition($subscriber, $configuration, $adapterName, $container);
                break;

            case 'cache':
                $this->configureCacheSubscriberDefinition($subscriber, $configuration, $adapterName, $container);
                break;

            case 'cookie':
                $this->configureCookieSubscriberDefinition($subscriber, $configuration);
                break;

            case 'history':
                $this->configureHistorySubscriberDefinition($subscriber, $configuration);
                break;

            case 'logger':
                $this->configureLoggerSubscriberDefinition($subscriber, $configuration);
                break;

            case 'redirect':
                $this->configureRedirectSubscriberDefinition($subscriber, $configuration, $adapterName, $container);
                break;

            case 'retry':
                $this->configureRetrySubscriberDefinition($subscriber, $adapterName, $container);
                break;

            case 'stopwatch':
                $this->configureStopwatchSubscriberDefinition($subscriber, $configuration);
                break;
        }

        return $subscriber;
    }

    /**
     * Configures the basic auth subscriber definition.
     *
     * @param \Symfony\Component\DependencyInjection\Definition       $subscriber  The subscriber.
     * @param array                                                   $basicAuth   The basic auth.
     * @param string                                                  $adapterName The adapter name.
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container   The container.
     */
    private function configureBasicAuthSubscriberDefinition(
        Definition $subscriber,
        array $basicAuth,
        $adapterName,
        ContainerBuilder $container
    ) {
        $model = new DefinitionDecorator(self::createServiceName('subscriber.basic_auth.model'));
        $model->setArguments(array($basicAuth['username'], $basicAuth['password']));

        if (isset($basicAuth['matcher'])) {
            $model->addArgument($basicAuth['matcher']);
        }

        $container->setDefinition($service = self::createServiceName($adapterName.'.basic_auth.model'), $model);
        $subscriber->setArguments(array(new Reference($service)));
    }

    /**
     * Configures the cache subscriber definition.
     *
     * @param \Symfony\Component\DependencyInjection\Definition       $subscriber  The subscriber.
     * @param array                                                   $cache       The cache.
     * @param string                                                  $adapterName The adapter name.
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container   The container.
     */
    private function configureCacheSubscriberDefinition(
        Definition $subscriber,
        array $cache,
        $adapterName,
        ContainerBuilder $container
    ) {
        $model = new DefinitionDecorator(self::createServiceName('subscriber.cache.model'));
        $model->setArguments(array(new Reference($cache['adapter']), null, $cache['lifetime'], $cache['exception']));

        $container->setDefinition($service = self::createServiceName($adapterName.'.cache.model'), $model);
        $subscriber->setArguments(array(new Reference($service)));
    }

    /**
     * Configures the cookie subscriber definition.
     *
     * @param \Symfony\Component\DependencyInjection\Definition $subscriber The subscriber.
     * @param string|null                                       $cookieJar  The cookie jar.
     */
    private function configureCookieSubscriberDefinition(Definition $subscriber, $cookieJar = null)
    {
        if ($cookieJar === null) {
            $cookieJar = 'default';
        }

        if (in_array($cookieJar, array('default', 'file', 'session'), true)) {
            $cookieJar = 'ivory.http_adapter.subscriber.cookie.jar.'.$cookieJar;
        }

        $subscriber->setArguments(array(new Reference($cookieJar)));
    }

    /**
     * Configures the history subscriber definition.
     *
     * @param \Symfony\Component\DependencyInjection\Definition $definition The subscriber.
     * @param string|null                                       $journal    The journal.
     */
    private function configureHistorySubscriberDefinition(Definition $definition, $journal = null)
    {
        $definition->setArguments(array(new Reference($journal ?: 'ivory.http_adapter.subscriber.history.journal')));
    }

    /**
     * Configures the logger subscriber definition.
     *
     * @param \Symfony\Component\DependencyInjection\Definition $subscriber The definition.
     * @param string|null                                       $logger     The logger.
     */
    private function configureLoggerSubscriberDefinition(Definition $subscriber, $logger = null)
    {
        $subscriber->setArguments(array(new Reference($logger ?: 'logger')));
    }

    /**
     * Configures the redirect subscriber definition.
     *
     * @param \Symfony\Component\DependencyInjection\Definition       $subscriber  The subscriber.
     * @param array                                                   $redirect    The redirect.
     * @param string                                                  $adapterName The adapter name.
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container   The container.
     */
    private function configureRedirectSubscriberDefinition(
        Definition $subscriber,
        array $redirect,
        $adapterName,
        ContainerBuilder $container
    ) {
        $model = new DefinitionDecorator(self::createServiceName('subscriber.redirect.model'));

        if (isset($redirect['max'])) {
            $model->addMethodCall('setMax', array($redirect['max']));
        }

        if (isset($redirect['strict'])) {
            $model->addMethodCall('setStrict', array($redirect['strict']));
        }

        if (isset($redirect['throw_exception'])) {
            $model->addMethodCall('setThrowException', array($redirect['throw_exception']));
        }

        $container->setDefinition($service = self::createServiceName($adapterName.'.redirect.model'), $model);
        $subscriber->setArguments(array(new Reference($service)));
    }

    /**
     * Configures the retry subscriber definition.
     *
     * @param \Symfony\Component\DependencyInjection\Definition       $subscriber  The subscriber.
     * @param string                                                  $adapterName The adapter name.
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container   The container.
     */
    private function configureRetrySubscriberDefinition(
        Definition $subscriber,
        $adapterName,
        ContainerBuilder $container
    ) {
        $container->setDefinition(
            $service = self::createServiceName($adapterName.'.retry.model'),
            new DefinitionDecorator(self::createServiceName('subscriber.retry.model'))
        );

        $subscriber->setArguments(array(new Reference($service)));
    }

    /**
     * Configures the stopwatch subscriber definition.
     *
     * @param \Symfony\Component\DependencyInjection\Definition $subscriber The subscriber.
     * @param string|null                                       $stopwatch  The stopwatch.
     */
    private function configureStopwatchSubscriberDefinition(Definition $subscriber, $stopwatch = null)
    {
        $subscriber->setArguments(array(new Reference($stopwatch ?: 'debug.stopwatch')));
    }

    /**
     * Gets a method name.
     *
     * @param string $property The property.
     *
     * @return string The method name.
     */
    private function getMethod($property)
    {
        return 'set'.str_replace('_', '', $property);
    }
}
