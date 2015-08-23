<?php

/*
 * This file is part of the Ivory Http Adapter bundle package.
 *
 * (c) Eric GELOEN <geloen.eric@gmail.com>
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 */

namespace Ivory\HttpAdapterBundle\Tests\DependencyInjection;

use Ivory\HttpAdapter\Event\Events;
use Ivory\HttpAdapterBundle\DependencyInjection\Compiler\RegisterListenerCompilerPass;
use Ivory\HttpAdapterBundle\DependencyInjection\IvoryHttpAdapterExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Abstract Ivory http adapter extension test.
 *
 * @author GeLo <geloen.eric@gmail.com>
 */
abstract class AbstractIvoryHttpAdapterExtensionTest extends \PHPUnit_Framework_TestCase
{
    /** @var \Symfony\Component\DependencyInjection\ContainerBuilder */
    private $container;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.debug', false);
        $this->container->addCompilerPass(new RegisterListenerCompilerPass());
        $this->container->registerExtension($httpAdapter = new IvoryHttpAdapterExtension());
        $this->container->loadFromExtension($httpAdapter->getAlias());
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        unset($this->container);
    }

    /**
     * Loads a configuration.
     *
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container     The container.
     * @param string                                                  $configuration The configuration.
     */
    abstract protected function loadConfiguration(ContainerBuilder $container, $configuration);

    public function testDefaultHttpAdapter()
    {
        $this->container->compile();

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\SocketHttpAdapter',
            $httpAdapter = $this->container->get('ivory.http_adapter')
        );

        $this->assertSame($httpAdapter, $this->container->get('ivory.http_adapter.default'));
        $this->assertSame($httpAdapter, $this->container->get('ivory.http_adapter.default.adapter'));

        $this->assertFalse($this->container->has('ivory.http_adapter.default.wrapper.stopwatch'));
        $this->assertFalse($this->container->has('ivory.http_adapter.default.wrapper.event_dispatcher'));
    }

    public function testDebugHttpAdapter()
    {
        $this->container->setParameter('kernel.debug', true);
        $this->container->set('debug.stopwatch', $this->getMock('Symfony\Component\Stopwatch\Stopwatch'));
        $this->container->compile();

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\StopwatchHttpAdapter',
            $stopwatchHttpAdapter = $this->container->get('ivory.http_adapter')
        );

        $this->assertSame($stopwatchHttpAdapter, $this->container->get('ivory.http_adapter.default'));
        $this->assertSame($stopwatchHttpAdapter, $this->container->get('ivory.http_adapter.default.wrapper.stopwatch'));

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\EventDispatcherHttpAdapter',
            $eventDispatcherHttpAdapter = $this->container->get('ivory.http_adapter.default.wrapper.event_dispatcher')
        );

        $stopwatchListener = $this->assertListener(
            $eventDispatcher = $this->container->get('ivory.http_adapter.default.event_dispatcher'),
            Events::REQUEST_CREATED,
            'Ivory\HttpAdapter\Event\Subscriber\StopwatchSubscriber',
            false
        );

        $this->assertSame($stopwatchListener->getStopwatch(), $this->container->get('debug.stopwatch'));

        $dataCollector = $this->assertListener(
            $eventDispatcher,
            Events::REQUEST_CREATED,
            'Ivory\HttpAdapterBundle\DataCollector\IvoryHttpAdapterDataCollector',
            false
        );

        $this->assertSame($dataCollector, $this->container->get('ivory.http_adapter.data_collector'));
    }

    /**
     * @dataProvider httpAdapterProvider
     */
    public function testHttpAdapter($configuration, $service, $class)
    {
        if (($configuration === 'guzzle4' && !class_exists('GuzzleHttp\Adapter\Curl\CurlAdapter'))
            || ($configuration === 'guzzle5' && !class_exists('GuzzleHttp\Ring\Client\CurlHandler'))
            || ($configuration === 'guzzle6') && !class_exists('GuzzleHttp\Handler\CurlHandler')
            || ($configuration === 'pecl_http') && !extension_loaded('pecl_http')) {
            $this->markTestSkipped();
        }

        $this->loadConfiguration($this->container, $configuration);
        $this->container->compile();

        $httpAdapter = $this->container->get($service);

        $this->assertInstanceOf($class, $httpAdapter);
        $this->assertSame($httpAdapter, $this->container->get('ivory.http_adapter'));
    }

    public function testGlobalProtocolVersion()
    {
        $this->loadConfiguration($this->container, 'global_protocol_version');
        $this->container->compile();

        $this->assertSame(
            1.0,
            $this->container->get('ivory.http_adapter')->getConfiguration()->getProtocolVersion()
        );
    }

    public function testGlobalKeepAlive()
    {
        $this->loadConfiguration($this->container, 'global_keep_alive');
        $this->container->compile();

        $this->assertTrue($this->container->get('ivory.http_adapter')->getConfiguration()->getKeepAlive());
    }

    public function testGlobalEncodingType()
    {
        $this->loadConfiguration($this->container, 'global_encoding_type');
        $this->container->compile();

        $this->assertSame(
            'application/json',
            $this->container->get('ivory.http_adapter')->getConfiguration()->getEncodingType()
        );
    }

    public function testGlobalBoundary()
    {
        $this->loadConfiguration($this->container, 'global_boundary');
        $this->container->compile();

        $this->assertSame('global', $this->container->get('ivory.http_adapter')->getConfiguration()->getBoundary());
    }

    public function testGlobalTimeout()
    {
        $this->loadConfiguration($this->container, 'global_timeout');
        $this->container->compile();

        $this->assertSame(60, $this->container->get('ivory.http_adapter')->getConfiguration()->getTimeout());
    }

    public function testGlobalUserAgent()
    {
        $this->loadConfiguration($this->container, 'global_user_agent');
        $this->container->compile();

        $this->assertSame('global', $this->container->get('ivory.http_adapter')->getConfiguration()->getUserAgent());
    }

    public function testGlobalBasicAuthSubscriber()
    {
        $this->loadConfiguration($this->container, 'global_basic_auth');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.default.event_dispatcher'),
            Events::REQUEST_CREATED,
            'Ivory\HttpAdapter\Event\Subscriber\BasicAuthSubscriber'
        );

        $this->assertSame(
            $basicAuth = $this->container->get('ivory.http_adapter.default.basic_auth.model'),
            $listener->getBasicAuth()
        );

        $this->assertSame('foo', $basicAuth->getUsername());
        $this->assertSame('bar', $basicAuth->getPassword());
        $this->assertFalse($basicAuth->hasMatcher());
    }

    public function testGlobalBasicAuthSubscriberWithMatcher()
    {
        $this->loadConfiguration($this->container, 'global_basic_auth_with_matcher');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.default.event_dispatcher'),
            Events::REQUEST_CREATED,
            'Ivory\HttpAdapter\Event\Subscriber\BasicAuthSubscriber'
        );

        $this->assertSame(
            $basicAuth = $this->container->get('ivory.http_adapter.default.basic_auth.model'),
            $listener->getBasicAuth()
        );

        $this->assertSame('domain.com', $basicAuth->getMatcher());
    }

    public function testGlobalCookieSubscriber()
    {
        $this->container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $this->loadConfiguration($this->container, 'global_cookie');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.default.event_dispatcher'),
            Events::REQUEST_CREATED,
            'Ivory\HttpAdapter\Event\Subscriber\CookieSubscriber'
        );

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\Event\Cookie\Jar\CookieJar',
            $cookieJar = $listener->getCookieJar()
        );

        $this->assertSame($cookieJar, $this->container->get('ivory.http_adapter.subscriber.cookie.jar.default'));

        $this->assertSame(
            $cookieJar->getCookieFactory(),
            $this->container->get('ivory.http_adapter.subscriber.cookie.factory')
        );
    }

    public function testGlobalCookieSubscriberWithFile()
    {
        $this->container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $this->loadConfiguration($this->container, 'global_cookie_with_file');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.default.event_dispatcher'),
            Events::REQUEST_CREATED,
            'Ivory\HttpAdapter\Event\Subscriber\CookieSubscriber'
        );

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\Event\Cookie\Jar\FileCookieJar',
            $cookieJar = $listener->getCookieJar()
        );

        $this->assertSame($cookieJar, $this->container->get('ivory.http_adapter.subscriber.cookie.jar.file'));
        $this->assertSame(
            $file = $cookieJar->getFile(),
            $this->container->getParameter('kernel.cache_dir').'/ivory/http-adapter/cookie.jar'
        );

        if (!file_exists($file)) {
            $parent = dirname($file);
            $grandParent = dirname($parent);

            if (!file_exists($grandParent)) {
                mkdir($grandParent);
            }

            if (!file_exists($parent)) {
                mkdir($parent);
            }
        }

        $this->assertSame(
            $cookieJar->getCookieFactory(),
            $this->container->get('ivory.http_adapter.subscriber.cookie.factory')
        );
    }

    public function testGlobalCookieSubscriberWithSession()
    {
        $this->container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $this->loadConfiguration($this->container, 'global_cookie_with_session');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.default.event_dispatcher'),
            Events::REQUEST_CREATED,
            'Ivory\HttpAdapter\Event\Subscriber\CookieSubscriber'
        );

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\Event\Cookie\Jar\SessionCookieJar',
            $cookieJar = $listener->getCookieJar()
        );

        $this->assertSame($cookieJar, $this->container->get('ivory.http_adapter.subscriber.cookie.jar.session'));
        $this->assertSame($cookieJar->getKey(), 'ivory.http_adapter.cookie.jar');

        $this->assertSame(
            $cookieJar->getCookieFactory(),
            $this->container->get('ivory.http_adapter.subscriber.cookie.factory')
        );
    }

    public function testGlobalHistorySubscriber()
    {
        $this->loadConfiguration($this->container, 'global_history');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.default.event_dispatcher'),
            Events::REQUEST_CREATED,
            'Ivory\HttpAdapter\Event\Subscriber\HistorySubscriber'
        );

        $this->assertInstanceOf('Ivory\HttpAdapter\Event\History\Journal', $journal = $listener->getJournal());
        $this->assertSame($journal, $this->container->get('ivory.http_adapter.subscriber.history.journal'));

        $this->assertSame(
            $journal->getJournalEntryFactory(),
            $this->container->get('ivory.http_adapter.subscriber.history.journal.entry.factory')
        );
    }

    public function testGlobalHistorySubscriberWithService()
    {
        $this->container->set('custom_journal', $this->getMock('Ivory\HttpAdapter\Event\History\JournalInterface'));
        $this->loadConfiguration($this->container, 'global_history_with_service');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.default.event_dispatcher'),
            Events::REQUEST_CREATED,
            'Ivory\HttpAdapter\Event\Subscriber\HistorySubscriber'
        );

        $this->assertSame($listener->getJournal(), $this->container->get('custom_journal'));
    }

    public function testGlobalLoggerSubscriber()
    {
        $this->container->set('logger', $this->getMock('Psr\Log\LoggerInterface'));
        $this->loadConfiguration($this->container, 'global_logger');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.default.event_dispatcher'),
            Events::REQUEST_CREATED,
            'Ivory\HttpAdapter\Event\Subscriber\LoggerSubscriber'
        );

        $this->assertSame($listener->getLogger(), $this->container->get('logger'));
    }

    public function testGlobalLoggerSubscriberWithService()
    {
        $this->container->set('custom_logger', $this->getMock('Psr\Log\LoggerInterface'));
        $this->loadConfiguration($this->container, 'global_logger_with_service');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.default.event_dispatcher'),
            Events::REQUEST_CREATED,
            'Ivory\HttpAdapter\Event\Subscriber\LoggerSubscriber'
        );

        $this->assertSame($listener->getLogger(), $this->container->get('custom_logger'));
    }

    public function testGlobalRedirectSubscriber()
    {
        $this->loadConfiguration($this->container, 'global_redirect');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.default.event_dispatcher'),
            Events::REQUEST_SENT,
            'Ivory\HttpAdapter\Event\Subscriber\RedirectSubscriber'
        );

        $this->assertSame(
            $redirect = $this->container->get('ivory.http_adapter.default.redirect.model'),
            $listener->getRedirect()
        );

        $this->assertSame(5, $redirect->getMax());
        $this->assertFalse($redirect->isStrict());
        $this->assertTrue($redirect->getThrowException());
    }

    public function testGlobalRedirectSubscriberWithConfiguration()
    {
        $this->loadConfiguration($this->container, 'global_redirect_with_configuration');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.default.event_dispatcher'),
            Events::REQUEST_SENT,
            'Ivory\HttpAdapter\Event\Subscriber\RedirectSubscriber'
        );

        $this->assertSame(
            $redirect = $this->container->get('ivory.http_adapter.default.redirect.model'),
            $listener->getRedirect()
        );

        $this->assertSame(3, $redirect->getMax());
        $this->assertTrue($redirect->isStrict());
        $this->assertFalse($redirect->getThrowException());
    }

    public function testGlobalRetrySubscriber()
    {
        $this->loadConfiguration($this->container, 'global_retry');
        $this->container->compile();

        $this->assertListener(
            $this->container->get('ivory.http_adapter.default.event_dispatcher'),
            Events::REQUEST_ERRORED,
            'Ivory\HttpAdapter\Event\Subscriber\RetrySubscriber'
        );
    }

    public function testGlobalStatusCodeSubscriber()
    {
        $this->loadConfiguration($this->container, 'global_status_code');
        $this->container->compile();

        $this->assertListener(
            $this->container->get('ivory.http_adapter.default.event_dispatcher'),
            Events::REQUEST_SENT,
            'Ivory\HttpAdapter\Event\Subscriber\StatusCodeSubscriber'
        );
    }

    public function testGlobalStopwatchSubscriber()
    {
        $this->container->set('debug.stopwatch', $this->getMock('Symfony\Component\Stopwatch\Stopwatch'));
        $this->loadConfiguration($this->container, 'global_stopwatch');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.default.event_dispatcher'),
            Events::REQUEST_CREATED,
            'Ivory\HttpAdapter\Event\Subscriber\StopwatchSubscriber'
        );

        $this->assertSame($listener->getStopwatch(), $this->container->get('debug.stopwatch'));
    }

    public function testGlobalStopwatchSubscriberWithService()
    {
        $this->container->set('custom_stopwatch', $this->getMock('Symfony\Component\Stopwatch\Stopwatch'));
        $this->loadConfiguration($this->container, 'global_stopwatch_with_service');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.default.event_dispatcher'),
            Events::REQUEST_CREATED,
            'Ivory\HttpAdapter\Event\Subscriber\StopwatchSubscriber'
        );

        $this->assertSame($listener->getStopwatch(), $this->container->get('custom_stopwatch'));
    }

    public function testLocalProtocolVersion()
    {
        $this->loadConfiguration($this->container, 'local_protocol_version');
        $this->container->compile();

        $this->assertSame(
            1.1,
            $this->container->get('ivory.http_adapter.local')->getConfiguration()->getProtocolVersion()
        );

        $this->assertSame(
            1.0,
            $this->container->get('ivory.http_adapter.global')->getConfiguration()->getProtocolVersion()
        );
    }

    public function testLocalKeepAlive()
    {
        $this->loadConfiguration($this->container, 'local_keep_alive');
        $this->container->compile();

        $this->assertFalse($this->container->get('ivory.http_adapter.local')->getConfiguration()->getKeepAlive());
        $this->assertTrue($this->container->get('ivory.http_adapter.global')->getConfiguration()->getKeepAlive());
    }

    public function testLocalEncodingType()
    {
        $this->loadConfiguration($this->container, 'local_encoding_type');
        $this->container->compile();

        $this->assertSame(
            'application/xml',
            $this->container->get('ivory.http_adapter.local')->getConfiguration()->getEncodingType()
        );

        $this->assertSame(
            'application/json',
            $this->container->get('ivory.http_adapter.global')->getConfiguration()->getEncodingType()
        );
    }

    public function testLocalBoundary()
    {
        $this->loadConfiguration($this->container, 'local_boundary');
        $this->container->compile();

        $this->assertSame(
            'local',
            $this->container->get('ivory.http_adapter.local')->getConfiguration()->getBoundary()
        );

        $this->assertSame(
            'global',
            $this->container->get('ivory.http_adapter.global')->getConfiguration()->getBoundary()
        );
    }

    public function testLocalTimeout()
    {
        $this->loadConfiguration($this->container, 'local_timeout');
        $this->container->compile();

        $this->assertSame(20, $this->container->get('ivory.http_adapter.local')->getConfiguration()->getTimeout());
        $this->assertSame(60, $this->container->get('ivory.http_adapter.global')->getConfiguration()->getTimeout());
    }

    public function testLocalUserAgent()
    {
        $this->loadConfiguration($this->container, 'local_user_agent');
        $this->container->compile();

        $this->assertSame('local', $this->container->get('ivory.http_adapter.local')->getConfiguration()->getUserAgent());
        $this->assertSame('global', $this->container->get('ivory.http_adapter.global')->getConfiguration()->getUserAgent());
    }

    public function testLocalBasicAuthSubscriber()
    {
        $this->loadConfiguration($this->container, 'local_basic_auth');
        $this->container->compile();

        $localListener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local.event_dispatcher'),
            Events::REQUEST_CREATED,
            'Ivory\HttpAdapter\Event\Subscriber\BasicAuthSubscriber'
        );

        $this->assertSame(
            $basicAuth = $this->container->get('ivory.http_adapter.local.basic_auth.model'),
            $localListener->getBasicAuth()
        );

        $this->assertSame('foo', $basicAuth->getUsername());
        $this->assertSame('bar', $basicAuth->getPassword());
        $this->assertFalse($basicAuth->hasMatcher());

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\SocketHttpAdapter',
            $this->container->get('ivory.http_adapter.global')
        );
    }

    public function testLocalBasicAuthSubscriberWithMatcher()
    {
        $this->loadConfiguration($this->container, 'local_basic_auth_with_matcher');
        $this->container->compile();

        $localListener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local.event_dispatcher'),
            Events::REQUEST_CREATED,
            'Ivory\HttpAdapter\Event\Subscriber\BasicAuthSubscriber'
        );

        $this->assertSame(
            $basicAuth = $this->container->get('ivory.http_adapter.local.basic_auth.model'),
            $localListener->getBasicAuth()
        );

        $this->assertSame('foo', $basicAuth->getUsername());
        $this->assertSame('bar', $basicAuth->getPassword());
        $this->assertSame('domain.com', $basicAuth->getMatcher());

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\SocketHttpAdapter',
            $this->container->get('ivory.http_adapter.global')
        );
    }

    public function testLocalCookieSubscriber()
    {
        $this->container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $this->loadConfiguration($this->container, 'local_cookie');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local.event_dispatcher'),
            Events::REQUEST_CREATED,
            'Ivory\HttpAdapter\Event\Subscriber\CookieSubscriber'
        );

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\Event\Cookie\Jar\CookieJar',
            $cookieJar = $listener->getCookieJar()
        );

        $this->assertSame($cookieJar, $this->container->get('ivory.http_adapter.subscriber.cookie.jar.default'));

        $this->assertSame(
            $cookieJar->getCookieFactory(),
            $this->container->get('ivory.http_adapter.subscriber.cookie.factory')
        );

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\SocketHttpAdapter',
            $this->container->get('ivory.http_adapter.global')
        );
    }

    public function testLocalCookieSubscriberWithFile()
    {
        $this->container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $this->loadConfiguration($this->container, 'local_cookie_with_file');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local.event_dispatcher'),
            Events::REQUEST_CREATED,
            'Ivory\HttpAdapter\Event\Subscriber\CookieSubscriber'
        );

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\Event\Cookie\Jar\FileCookieJar',
            $cookieJar = $listener->getCookieJar()
        );

        $this->assertSame($cookieJar, $this->container->get('ivory.http_adapter.subscriber.cookie.jar.file'));
        $this->assertSame(
            $file = $cookieJar->getFile(),
            $this->container->getParameter('kernel.cache_dir').'/ivory/http-adapter/cookie.jar'
        );

        if (!file_exists($file)) {
            $parent = dirname($file);
            $grandParent = dirname($parent);

            if (!file_exists($grandParent)) {
                mkdir($grandParent);
            }

            if (!file_exists($parent)) {
                mkdir($parent);
            }
        }

        $this->assertSame(
            $cookieJar->getCookieFactory(),
            $this->container->get('ivory.http_adapter.subscriber.cookie.factory')
        );

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\SocketHttpAdapter',
            $this->container->get('ivory.http_adapter.global')
        );
    }

    public function testLocalCookieSubscriberWithSession()
    {
        $this->container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $this->loadConfiguration($this->container, 'local_cookie_with_session');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local.event_dispatcher'),
            Events::REQUEST_CREATED,
            'Ivory\HttpAdapter\Event\Subscriber\CookieSubscriber'
        );

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\Event\Cookie\Jar\SessionCookieJar',
            $cookieJar = $listener->getCookieJar()
        );

        $this->assertSame($cookieJar, $this->container->get('ivory.http_adapter.subscriber.cookie.jar.session'));
        $this->assertSame($cookieJar->getKey(), 'ivory.http_adapter.cookie.jar');

        $this->assertSame(
            $cookieJar->getCookieFactory(),
            $this->container->get('ivory.http_adapter.subscriber.cookie.factory')
        );

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\SocketHttpAdapter',
            $this->container->get('ivory.http_adapter.global')
        );
    }

    public function testLocalHistorySubscriber()
    {
        $this->loadConfiguration($this->container, 'local_history');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local.event_dispatcher'),
            Events::REQUEST_CREATED,
            'Ivory\HttpAdapter\Event\Subscriber\HistorySubscriber'
        );

        $this->assertInstanceOf('Ivory\HttpAdapter\Event\History\Journal', $journal = $listener->getJournal());
        $this->assertSame($journal, $this->container->get('ivory.http_adapter.subscriber.history.journal'));

        $this->assertSame(
            $journal->getJournalEntryFactory(),
            $this->container->get('ivory.http_adapter.subscriber.history.journal.entry.factory')
        );

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\SocketHttpAdapter',
            $this->container->get('ivory.http_adapter.global')
        );
    }

    public function testLocalHistorySubscriberWithService()
    {
        $this->container->set('custom_journal', $this->getMock('Ivory\HttpAdapter\Event\History\JournalInterface'));
        $this->loadConfiguration($this->container, 'local_history_with_service');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local.event_dispatcher'),
            Events::REQUEST_CREATED,
            'Ivory\HttpAdapter\Event\Subscriber\HistorySubscriber'
        );

        $this->assertSame($listener->getJournal(), $this->container->get('custom_journal'));

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\SocketHttpAdapter',
            $this->container->get('ivory.http_adapter.global')
        );
    }

    public function testLocalLoggerSubscriber()
    {
        $this->container->set('logger', $this->getMock('Psr\Log\LoggerInterface'));
        $this->loadConfiguration($this->container, 'local_logger');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local.event_dispatcher'),
            Events::REQUEST_CREATED,
            'Ivory\HttpAdapter\Event\Subscriber\LoggerSubscriber'
        );

        $this->assertSame($listener->getLogger(), $this->container->get('logger'));

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\SocketHttpAdapter',
            $this->container->get('ivory.http_adapter.global')
        );
    }

    public function testLocalLoggerSubscriberWithService()
    {
        $this->container->set('custom_logger', $this->getMock('Psr\Log\LoggerInterface'));
        $this->loadConfiguration($this->container, 'local_logger_with_service');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local.event_dispatcher'),
            Events::REQUEST_CREATED,
            'Ivory\HttpAdapter\Event\Subscriber\LoggerSubscriber'
        );

        $this->assertSame($listener->getLogger(), $this->container->get('custom_logger'));

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\SocketHttpAdapter',
            $this->container->get('ivory.http_adapter.global')
        );
    }

    public function testLocalRedirectSubscriber()
    {
        $this->loadConfiguration($this->container, 'local_redirect');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local.event_dispatcher'),
            Events::REQUEST_SENT,
            'Ivory\HttpAdapter\Event\Subscriber\RedirectSubscriber'
        );

        $this->assertSame(
            $redirect = $this->container->get('ivory.http_adapter.local.redirect.model'),
            $listener->getRedirect()
        );

        $this->assertSame(5, $redirect->getMax());
        $this->assertFalse($redirect->isStrict());
        $this->assertTrue($redirect->getThrowException());

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\SocketHttpAdapter',
            $this->container->get('ivory.http_adapter.global')
        );
    }

    public function testLocalRedirectSubscriberWithConfiguration()
    {
        $this->loadConfiguration($this->container, 'local_redirect_with_configuration');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local.event_dispatcher'),
            Events::REQUEST_SENT,
            'Ivory\HttpAdapter\Event\Subscriber\RedirectSubscriber'
        );

        $this->assertSame(
            $redirect = $this->container->get('ivory.http_adapter.local.redirect.model'),
            $listener->getRedirect()
        );

        $this->assertSame(3, $redirect->getMax());
        $this->assertTrue($redirect->isStrict());
        $this->assertFalse($redirect->getThrowException());

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\SocketHttpAdapter',
            $this->container->get('ivory.http_adapter.global')
        );
    }

    public function testLocalRetrySubscriber()
    {
        $this->loadConfiguration($this->container, 'local_retry');
        $this->container->compile();

        $this->assertListener(
            $this->container->get('ivory.http_adapter.local.event_dispatcher'),
            Events::REQUEST_ERRORED,
            'Ivory\HttpAdapter\Event\Subscriber\RetrySubscriber'
        );

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\SocketHttpAdapter',
            $this->container->get('ivory.http_adapter.global')
        );
    }

    public function testLocalStatusCodeSubscriber()
    {
        $this->loadConfiguration($this->container, 'local_status_code');
        $this->container->compile();

        $this->assertListener(
            $this->container->get('ivory.http_adapter.local.event_dispatcher'),
            Events::REQUEST_SENT,
            'Ivory\HttpAdapter\Event\Subscriber\StatusCodeSubscriber'
        );

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\SocketHttpAdapter',
            $this->container->get('ivory.http_adapter.global')
        );
    }

    public function testLocalStopwatchSubscriber()
    {
        $this->container->set('debug.stopwatch', $this->getMock('Symfony\Component\Stopwatch\Stopwatch'));
        $this->loadConfiguration($this->container, 'local_stopwatch');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local.event_dispatcher'),
            Events::REQUEST_CREATED,
            'Ivory\HttpAdapter\Event\Subscriber\StopwatchSubscriber'
        );

        $this->assertSame($listener->getStopwatch(), $this->container->get('debug.stopwatch'));

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\SocketHttpAdapter',
            $this->container->get('ivory.http_adapter.global')
        );
    }

    public function testLocalStopwatchSubscriberWithService()
    {
        $this->container->set('custom_stopwatch', $this->getMock('Symfony\Component\Stopwatch\Stopwatch'));
        $this->loadConfiguration($this->container, 'local_stopwatch_with_service');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local.event_dispatcher'),
            Events::REQUEST_CREATED,
            'Ivory\HttpAdapter\Event\Subscriber\StopwatchSubscriber'
        );

        $this->assertSame($listener->getStopwatch(), $this->container->get('custom_stopwatch'));

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\SocketHttpAdapter',
            $this->container->get('ivory.http_adapter.global')
        );
    }

    public function testListener()
    {
        $this->loadConfiguration($this->container, 'listener');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.default.event_dispatcher'),
            Events::REQUEST_SENT,
            'Ivory\HttpAdapter\Event\Subscriber\StatusCodeSubscriber'
        );

        $this->assertSame($listener, $this->container->get('my_listener'));
    }

    public function testListenerWithAdapter()
    {
        $this->loadConfiguration($this->container, 'listener_with_adapter');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local.event_dispatcher'),
            Events::REQUEST_SENT,
            'Ivory\HttpAdapter\Event\Subscriber\StatusCodeSubscriber'
        );

        $this->assertSame($listener, $this->container->get('my_listener'));

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\SocketHttpAdapter',
            $this->container->get('ivory.http_adapter.global')
        );
    }

    public function testSubscriber()
    {
        $this->loadConfiguration($this->container, 'subscriber');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.default.event_dispatcher'),
            Events::REQUEST_SENT,
            'Ivory\HttpAdapter\Event\Subscriber\StatusCodeSubscriber'
        );

        $this->assertSame($listener, $this->container->get('my_subscriber'));
    }

    public function testSubscriberWithAdapter()
    {
        $this->loadConfiguration($this->container, 'subscriber_with_adapter');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local.event_dispatcher'),
            Events::REQUEST_SENT,
            'Ivory\HttpAdapter\Event\Subscriber\StatusCodeSubscriber'
        );

        $this->assertSame($listener, $this->container->get('my_subscriber'));

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\SocketHttpAdapter',
            $this->container->get('ivory.http_adapter.global')
        );
    }

    /**
     * Get the http adapter provider.
     *
     * @return array The http adapter provider.
     */
    public function httpAdapterProvider()
    {
        return array(
            array('buzz', 'ivory.http_adapter.buzz', 'Ivory\HttpAdapter\BuzzHttpAdapter'),
            array('cake', 'ivory.http_adapter.cake', 'Ivory\HttpAdapter\CakeHttpAdapter'),
            array('curl', 'ivory.http_adapter.curl', 'Ivory\HttpAdapter\CurlHttpAdapter'),
            array(
                'file_get_contents',
                'ivory.http_adapter.file_get_contents',
                'Ivory\HttpAdapter\FileGetContentsHttpAdapter',
            ),
            array('fopen', 'ivory.http_adapter.fopen', 'Ivory\HttpAdapter\FopenHttpAdapter'),
            array('guzzle3', 'ivory.http_adapter.guzzle3', 'Ivory\HttpAdapter\Guzzle3HttpAdapter'),
            array('guzzle4', 'ivory.http_adapter.guzzle4', 'Ivory\HttpAdapter\Guzzle4HttpAdapter'),
            array('guzzle5', 'ivory.http_adapter.guzzle5', 'Ivory\HttpAdapter\Guzzle5HttpAdapter'),
            array('guzzle6', 'ivory.http_adapter.guzzle6', 'Ivory\HttpAdapter\Guzzle6HttpAdapter'),
            array('httpful', 'ivory.http_adapter.httpful', 'Ivory\HttpAdapter\HttpfulHttpAdapter'),
            array('pecl_http', 'ivory.http_adapter.pecl_http', 'Ivory\HttpAdapter\PeclHttpAdapter'),
            array('react', 'ivory.http_adapter.react', 'Ivory\HttpAdapter\ReactHttpAdapter'),
            array('socket', 'ivory.http_adapter.socket', 'Ivory\HttpAdapter\SocketHttpAdapter'),
            array('zend1', 'ivory.http_adapter.zend1', 'Ivory\HttpAdapter\Zend1HttpAdapter'),
            array('zend2', 'ivory.http_adapter.zend2', 'Ivory\HttpAdapter\Zend2HttpAdapter'),
        );
    }

    /**
     * Asserts a listener.
     *
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher The event dispatcher.
     * @param string                                                      $eventName       The event name.
     * @param string                                                      $class           The class.
     * @param boolean                                                     $unicity         The unicity.
     *
     * @return object The listener.
     */
    private function assertListener($eventDispatcher, $eventName, $class, $unicity = true)
    {
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $eventDispatcher);

        $listeners = $eventDispatcher->getListeners($eventName);

        if ($unicity) {
            $this->assertCount(1, $listeners);
        }

        foreach ($listeners as $listener) {
            if ($listener[0] instanceof $class) {
                return $listener[0];
            }
        }

        $this->fail();
    }
}
