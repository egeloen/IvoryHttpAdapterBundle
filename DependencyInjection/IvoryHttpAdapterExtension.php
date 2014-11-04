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

        $this->loadAdapters($config, $container, $loader);
    }

    /**
     * Loads the adapters.
     *
     * @param array                                                   $config    The configuration.
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container The container.
     * @param \Symfony\Component\Config\Loader\LoaderInterface        $loader    The loader.
     */
    protected function loadAdapters(array $config, ContainerBuilder $container, LoaderInterface $loader)
    {
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

        $adapters = array();
        foreach ($config['adapters'] as $name => $adapter) {
            $adapters[] = $name;
            $this->loadAdapter($name, $adapter, $config['configs'], $container);
            $this->loadSubscribers($name, $adapter, $config['subscribers'], $container, $loader);
        }

        $container->setParameter(RegisterListenerCompilerPass::PARAMETER, $adapters);
        $container->setAlias(self::createServiceName(), self::createServiceName($config['default']));
    }

    /**
     * Loads an adapter.
     *
     * @param string                                                  $name      The name.
     * @param array                                                   $adapter   The adapter.
     * @param array                                                   $configs   The global configuration.
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container The container.
     */
    protected function loadAdapter($name, array $adapter, array $configs, ContainerBuilder $container)
    {
        $eventDispatcher = self::createServiceName($name.'.event_dispatcher');
        $configuration = self::createServiceName($name.'.configuration');

        $container->setDefinition(
            $eventDispatcher,
            new DefinitionDecorator(self::createServiceName('event_dispatcher'))
        );

        $container->setDefinition($configuration,
            $this->createConfigurationDefinition($adapter, $configs, $eventDispatcher)
        );

        $container->setDefinition(
            $adapterName = self::createServiceName($name),
            $this->createAdapterDefinition($adapter, $configuration)
        );
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
    protected function loadSubscribers(
        $name,
        array $adapter,
        array $subscribers,
        ContainerBuilder $container,
        LoaderInterface $loader
    ) {
        foreach (array_merge($subscribers, $adapter['subscribers']) as $subscriberName => $subscriber) {
            $loader->load('subscribers/'.$subscriberName.'.xml');

            $container->setDefinition(
                self::createServiceName($name.'.'.$subscriberName),
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
    protected function createAdapterDefinition(array $adapter, $configuration)
    {
        $definition = new DefinitionDecorator(self::createServiceName('abstract'));
        $definition->setClass('%'.self::createServiceName($adapter['type'].'.class').'%');

        $definition->addMethodCall(
            'setConfiguration',
            array(new Reference($configuration))
        );

        return $definition;
    }

    /**
     * Creates a configuration definition.
     *
     * @param array  $adapter         The adapter.
     * @param array  $configs         The global configuration.
     * @param string $eventDispatcher The event dispatcher service name.
     *
     * @return \Symfony\Component\DependencyInjection\DefinitionDecorator The configuration definition.
     */
    protected function createConfigurationDefinition(array $adapter, array $configs, $eventDispatcher)
    {
        $definition = new DefinitionDecorator(self::createServiceName('configuration'));
        $definition->addArgument(new Reference($eventDispatcher));

        foreach (array_merge($configs, $adapter['configs']) as $property => $value) {
            $definition->addMethodCall($this->getMethod($property), array($value));
        }

        return $definition;
    }

    /**
     * Creates a subscriber definition.
     *
     * @param string       $adapterName    The adatper name.
     * @param string       $subscriberName The subscriber name.
     * @param array|string $configuration  The configuration.
     *
     * @return \Symfony\Component\DependencyInjection\DefinitionDecorator The subscriber definition.
     */
    protected function createSubscriberDefinition(
        $adapterName,
        $subscriberName,
        $configuration,
        ContainerBuilder $container
    ) {
        $parent = self::createServiceName('subscriber.'.$subscriberName);

        $definition = new DefinitionDecorator($parent);
        $definition->setClass($container->getDefinition($parent)->getClass());
        $definition->addTag(RegisterListenerCompilerPass::SUBSCRIBER_TAG, array('adapter' => $adapterName));

        switch ($subscriberName) {
            case 'basic_auth':
                $this->configureBasicAuthSubscriberDefinition($definition, $configuration);
                break;

            case 'cookie':
                $this->configureCookieSubscriberDefinition($definition, $configuration);
                break;

            case 'history':
                $this->configureHistorySubscriberDefinition($definition, $configuration);
                break;

            case 'logger':
                $this->configureLoggerSubscriberDefinition($definition, $configuration);
                break;

            case 'redirect':
                $this->configureRedirectSubscriberDefinition($definition, $configuration);
                break;
        }

        return $definition;
    }

    /**
     * Configures the basic auth subscriber definition.
     *
     * @param \Symfony\Component\DependencyInjection\Definition $definition The definition.
     * @param array                                             $basicAuth  The basic auth.
     */
    protected function configureBasicAuthSubscriberDefinition(Definition $definition, array $basicAuth)
    {
        $definition->setArguments(array($basicAuth['username'], $basicAuth['password']));

        if (isset($basicAuth['matcher'])) {
            $definition->addArgument($basicAuth['matcher']);
        }
    }

    /**
     * Configures the cookie subscriber definition.
     *
     * @param \Symfony\Component\DependencyInjection\Definition $definition The definition.
     * @param string|null                                       $cookieJar  The cookie jar.
     */
    protected function configureCookieSubscriberDefinition(Definition $definition, $cookieJar = null)
    {
        if ($cookieJar === null) {
            $cookieJar = 'default';
        }

        if (in_array($cookieJar, array('default', 'file', 'session'), true)) {
            $cookieJar = 'ivory.http_adapter.subscriber.cookie.jar.'.$cookieJar;
        }

        $definition->setArguments(array(new Reference($cookieJar)));
    }

    /**
     * Configures the history subscriber definition.
     *
     * @param \Symfony\Component\DependencyInjection\Definition $definition The definition.
     * @param string|null                                       $journal    The journal.
     */
    protected function configureHistorySubscriberDefinition(Definition $definition, $journal = null)
    {
        $definition->setArguments(array(new Reference($journal ?: 'ivory.http_adapter.subscriber.history.journal')));
    }

    /**
     * Configures the logger subscriber definition.
     *
     * @param \Symfony\Component\DependencyInjection\Definition $definition The definition.
     * @param string|null                                       $logger     The logger.
     */
    protected function configureLoggerSubscriberDefinition(Definition $definition, $logger = null)
    {
        $definition->setArguments(array(new Reference($logger ?: 'logger')));
    }

    /**
     * Configures the redirect subscriber definition.
     *
     * @param \Symfony\Component\DependencyInjection\Definition $definition The definition.
     * @param array                                             $redirect   The redirect.
     */
    protected function configureRedirectSubscriberDefinition(Definition $definition, array $redirect = array())
    {
        if (isset($redirect['max'])) {
            $definition->addMethodCall('setMax', array($redirect['max']));
        }

        if (isset($redirect['strict'])) {
            $definition->addMethodCall('setStrict', array($redirect['strict']));
        }

        if (isset($redirect['throw_exception'])) {
            $definition->addMethodCall('setThrowException', array($redirect['throw_exception']));
        }
    }

    /**
     * Gets a method name.
     *
     * @param string $property The property.
     *
     * @return string The method name.
     */
    protected function getMethod($property)
    {
        return 'set'.str_replace('_', '', $property);
    }
}
