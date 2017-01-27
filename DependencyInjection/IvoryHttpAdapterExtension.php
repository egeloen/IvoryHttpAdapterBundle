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
use Symfony\Component\HttpKernel\Kernel;

/**
 * @author GeLo <geloen.eric@gmail.com>
 */
class IvoryHttpAdapterExtension extends ConfigurableExtension
{
    /**
     * @param string|null $suffix
     *
     * @return string
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

        $definition = $container->getDefinition('ivory.http_adapter.abstract');

        if (Kernel::VERSION_ID < 20600) {
            $definition
                ->setFactoryClass('Ivory\HttpAdapter\HttpAdapterFactory')
                ->setFactoryMethod('create');
        } else {
            $definition->setFactory(['Ivory\HttpAdapter\HttpAdapterFactory', 'create']);
        }

        if ($container->getParameter('kernel.debug')) {
            $loader->load('data_collector.xml');
        }

        $this->loadAdapters($config, $container, $loader);
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     * @param LoaderInterface  $loader
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
     * @param string           $name
     * @param array            $adapter
     * @param array            $configs
     * @param array            $subscribers
     * @param ContainerBuilder $container
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
     * @param string           $name
     * @param array            $adapter
     * @param array            $subscribers
     * @param ContainerBuilder $container
     * @param LoaderInterface  $loader
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
     * @param array  $adapter
     * @param string $configuration
     *
     * @return DefinitionDecorator
     */
    private function createAdapterDefinition(array $adapter, $configuration)
    {
        $definition = new DefinitionDecorator(self::createServiceName('abstract'));
        $definition->setArguments([$adapter['type']]);
        $definition->addMethodCall('setConfiguration', [new Reference($configuration)]);

        return $definition;
    }

    /**
     * @param array $adapter
     * @param array $configs
     *
     * @return DefinitionDecorator
     */
    private function createConfigurationDefinition(array $adapter, array $configs)
    {
        $definition = new DefinitionDecorator(self::createServiceName('configuration'));

        foreach (array_merge($configs, $adapter['configs']) as $property => $value) {
            $definition->addMethodCall($this->getMethod($property), [$value]);
        }

        return $definition;
    }

    /**
     * @param string           $adapterName
     * @param string           $subscriberName
     * @param array|string     $configuration
     * @param ContainerBuilder $container
     *
     * @return DefinitionDecorator
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
        $subscriber->addTag(RegisterListenerCompilerPass::SUBSCRIBER_TAG, ['adapter' => $adapterName]);

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
     * @param Definition       $subscriber
     * @param array            $basicAuth
     * @param string           $adapterName
     * @param ContainerBuilder $container
     */
    private function configureBasicAuthSubscriberDefinition(
        Definition $subscriber,
        array $basicAuth,
        $adapterName,
        ContainerBuilder $container
    ) {
        $model = new DefinitionDecorator(self::createServiceName('subscriber.basic_auth.model'));
        $model->setArguments([$basicAuth['username'], $basicAuth['password']]);

        if (isset($basicAuth['matcher'])) {
            $model->addArgument($basicAuth['matcher']);
        }

        $container->setDefinition($service = self::createServiceName($adapterName.'.basic_auth.model'), $model);
        $subscriber->setArguments([new Reference($service)]);
    }

    /**
     * @param Definition       $subscriber
     * @param array            $cache
     * @param string           $adapterName
     * @param ContainerBuilder $container
     */
    private function configureCacheSubscriberDefinition(
        Definition $subscriber,
        array $cache,
        $adapterName,
        ContainerBuilder $container
    ) {
        $model = new DefinitionDecorator(self::createServiceName('subscriber.cache.model'));
        $model->setArguments([new Reference($cache['adapter']), null, $cache['lifetime'], $cache['exception']]);

        $container->setDefinition($service = self::createServiceName($adapterName.'.cache.model'), $model);
        $subscriber->setArguments([new Reference($service)]);
    }

    /**
     * @param Definition  $subscriber
     * @param string|null $cookieJar
     */
    private function configureCookieSubscriberDefinition(Definition $subscriber, $cookieJar = null)
    {
        if ($cookieJar === null) {
            $cookieJar = 'default';
        }

        if (in_array($cookieJar, ['default', 'file', 'session'], true)) {
            $cookieJar = 'ivory.http_adapter.subscriber.cookie.jar.'.$cookieJar;
        }

        $subscriber->setArguments([new Reference($cookieJar)]);
    }

    /**
     * @param Definition  $definition
     * @param string|null $journal
     */
    private function configureHistorySubscriberDefinition(Definition $definition, $journal = null)
    {
        $definition->setArguments([new Reference($journal ?: 'ivory.http_adapter.subscriber.history.journal')]);
    }

    /**
     * @param Definition  $subscriber
     * @param string|null $logger
     */
    private function configureLoggerSubscriberDefinition(Definition $subscriber, $logger = null)
    {
        $subscriber->setArguments([new Reference($logger ?: 'logger')]);
    }

    /**
     * @param Definition       $subscriber
     * @param array            $redirect
     * @param string           $adapterName
     * @param ContainerBuilder $container
     */
    private function configureRedirectSubscriberDefinition(
        Definition $subscriber,
        array $redirect,
        $adapterName,
        ContainerBuilder $container
    ) {
        $model = new DefinitionDecorator(self::createServiceName('subscriber.redirect.model'));

        if (isset($redirect['max'])) {
            $model->addMethodCall('setMax', [$redirect['max']]);
        }

        if (isset($redirect['strict'])) {
            $model->addMethodCall('setStrict', [$redirect['strict']]);
        }

        if (isset($redirect['throw_exception'])) {
            $model->addMethodCall('setThrowException', [$redirect['throw_exception']]);
        }

        $container->setDefinition($service = self::createServiceName($adapterName.'.redirect.model'), $model);
        $subscriber->setArguments([new Reference($service)]);
    }

    /**
     * @param Definition       $subscriber
     * @param string           $adapterName
     * @param ContainerBuilder $container
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

        $subscriber->setArguments([new Reference($service)]);
    }

    /**
     * @param Definition  $subscriber
     * @param string|null $stopwatch
     */
    private function configureStopwatchSubscriberDefinition(Definition $subscriber, $stopwatch = null)
    {
        $subscriber->setArguments([new Reference($stopwatch ?: 'debug.stopwatch')]);
    }

    /**
     * @param string $property
     *
     * @return string
     */
    private function getMethod($property)
    {
        return 'set'.str_replace('_', '', $property);
    }
}
