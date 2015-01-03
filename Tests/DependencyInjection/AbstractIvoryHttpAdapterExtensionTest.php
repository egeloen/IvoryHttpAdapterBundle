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
        if (!defined('APP_DIR')) {
            define('APP_DIR', 'app');
        }

        if (!defined('DS')) {
            define('DS', DIRECTORY_SEPARATOR);
        }

        if (!defined('ROOT')) {
            define('ROOT', realpath(__DIR__.'/../../vendor/cakephp/cakephp'));
        }

        if (!defined('WWW_ROOT')) {
            define('WWW_ROOT', ROOT.DS.APP_DIR.DS.'webroot'.DS);
        }

        require_once __DIR__.'/../../vendor/cakephp/cakephp/lib/Cake/bootstrap.php';
        \App::uses('HttpSocket', 'Network/Http');

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

        $httpAdapter = $this->container->get('ivory.http_adapter.default');

        $this->assertInstanceOf('Ivory\HttpAdapter\SocketHttpAdapter', $httpAdapter);
        $this->assertNoListeners($httpAdapter);

        $this->assertSame($httpAdapter, $this->container->get('ivory.http_adapter'));
    }

    public function testDebugHttpAdapter()
    {
        $this->container->setParameter('kernel.debug', true);
        $this->container->set('debug.stopwatch', $this->getMock('Symfony\Component\Stopwatch\Stopwatch'));
        $this->container->compile();

        $this->assertInstanceOf(
            'Ivory\HttpAdapter\StopwatchHttpAdapter',
            $httpAdapter = $this->container->get('ivory.http_adapter')
        );

        $stopwatchListener = $this->assertListener(
            $httpAdapter,
            Events::PRE_SEND,
            'Ivory\HttpAdapter\Event\Subscriber\StopwatchSubscriber',
            false
        );

        $this->assertSame($stopwatchListener->getStopwatch(), $this->container->get('debug.stopwatch'));

        $dataCollector = $this->assertListener(
            $httpAdapter,
            Events::PRE_SEND,
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
        if ((($configuration === 'guzzle_http') && !class_exists('GuzzleHttp\Client'))
            || (($configuration === 'react') && !class_exists('React\HttpClient\Request'))
            || (($configuration === 'zend2') && !class_exists('Zend\Http\Client'))) {
            $this->markTestSkipped();
        }

        $this->loadConfiguration($this->container, $configuration);
        $this->container->compile();

        $httpAdapter = $this->container->get($service);

        $this->assertInstanceOf($class, $httpAdapter);
        $this->assertNoListeners($httpAdapter);

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
            $this->container->get('ivory.http_adapter'),
            Events::PRE_SEND,
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
            $this->container->get('ivory.http_adapter'),
            Events::PRE_SEND,
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
            $this->container->get('ivory.http_adapter'),
            Events::PRE_SEND,
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
            $this->container->get('ivory.http_adapter'),
            Events::PRE_SEND,
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
            $this->container->get('ivory.http_adapter'),
            Events::PRE_SEND,
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
            $this->container->get('ivory.http_adapter'),
            Events::PRE_SEND,
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
            $this->container->get('ivory.http_adapter'),
            Events::PRE_SEND,
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
            $this->container->get('ivory.http_adapter'),
            Events::PRE_SEND,
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
            $this->container->get('ivory.http_adapter'),
            Events::PRE_SEND,
            'Ivory\HttpAdapter\Event\Subscriber\LoggerSubscriber'
        );

        $this->assertSame($listener->getLogger(), $this->container->get('custom_logger'));
    }

    public function testGlobalRedirectSubscriber()
    {
        $this->loadConfiguration($this->container, 'global_redirect');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter'),
            Events::POST_SEND,
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
            $this->container->get('ivory.http_adapter'),
            Events::POST_SEND,
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
            $this->container->get('ivory.http_adapter'),
            Events::EXCEPTION,
            'Ivory\HttpAdapter\Event\Subscriber\RetrySubscriber'
        );
    }

    public function testGlobalStatusCodeSubscriber()
    {
        $this->loadConfiguration($this->container, 'global_status_code');
        $this->container->compile();

        $this->assertListener(
            $this->container->get('ivory.http_adapter'),
            Events::POST_SEND,
            'Ivory\HttpAdapter\Event\Subscriber\StatusCodeSubscriber'
        );
    }

    public function testGlobalStopwatchSubscriber()
    {
        $this->container->set('debug.stopwatch', $this->getMock('Symfony\Component\Stopwatch\Stopwatch'));
        $this->loadConfiguration($this->container, 'global_stopwatch');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter'),
            Events::PRE_SEND,
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
            $this->container->get('ivory.http_adapter'),
            Events::PRE_SEND,
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
            $this->container->get('ivory.http_adapter.local'),
            Events::PRE_SEND,
            'Ivory\HttpAdapter\Event\Subscriber\BasicAuthSubscriber'
        );

        $this->assertSame(
            $basicAuth = $this->container->get('ivory.http_adapter.local.basic_auth.model'),
            $localListener->getBasicAuth()
        );

        $this->assertSame('foo', $basicAuth->getUsername());
        $this->assertSame('bar', $basicAuth->getPassword());
        $this->assertFalse($basicAuth->hasMatcher());

        $this->assertNoListeners($this->container->get('ivory.http_adapter.global'));
    }

    public function testLocalBasicAuthSubscriberWithMatcher()
    {
        $this->loadConfiguration($this->container, 'local_basic_auth_with_matcher');
        $this->container->compile();

        $localListener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local'),
            Events::PRE_SEND,
            'Ivory\HttpAdapter\Event\Subscriber\BasicAuthSubscriber'
        );

        $this->assertSame(
            $basicAuth = $this->container->get('ivory.http_adapter.local.basic_auth.model'),
            $localListener->getBasicAuth()
        );

        $this->assertSame('foo', $basicAuth->getUsername());
        $this->assertSame('bar', $basicAuth->getPassword());
        $this->assertSame('domain.com', $basicAuth->getMatcher());

        $this->assertNoListeners($this->container->get('ivory.http_adapter.global'));
    }

    public function testLocalCookieSubscriber()
    {
        $this->container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $this->loadConfiguration($this->container, 'local_cookie');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local'),
            Events::PRE_SEND,
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

        $this->assertNoListeners($this->container->get('ivory.http_adapter.global'));
    }

    public function testLocalCookieSubscriberWithFile()
    {
        $this->container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $this->loadConfiguration($this->container, 'local_cookie_with_file');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local'),
            Events::PRE_SEND,
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

        $this->assertNoListeners($this->container->get('ivory.http_adapter.global'));
    }

    public function testLocalCookieSubscriberWithSession()
    {
        $this->container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $this->loadConfiguration($this->container, 'local_cookie_with_session');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local'),
            Events::PRE_SEND,
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

        $this->assertNoListeners($this->container->get('ivory.http_adapter.global'));
    }

    public function testLocalHistorySubscriber()
    {
        $this->loadConfiguration($this->container, 'local_history');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local'),
            Events::PRE_SEND,
            'Ivory\HttpAdapter\Event\Subscriber\HistorySubscriber'
        );

        $this->assertInstanceOf('Ivory\HttpAdapter\Event\History\Journal', $journal = $listener->getJournal());
        $this->assertSame($journal, $this->container->get('ivory.http_adapter.subscriber.history.journal'));

        $this->assertSame(
            $journal->getJournalEntryFactory(),
            $this->container->get('ivory.http_adapter.subscriber.history.journal.entry.factory')
        );

        $this->assertNoListeners($this->container->get('ivory.http_adapter.global'));
    }

    public function testLocalHistorySubscriberWithService()
    {
        $this->container->set('custom_journal', $this->getMock('Ivory\HttpAdapter\Event\History\JournalInterface'));
        $this->loadConfiguration($this->container, 'local_history_with_service');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local'),
            Events::PRE_SEND,
            'Ivory\HttpAdapter\Event\Subscriber\HistorySubscriber'
        );

        $this->assertSame($listener->getJournal(), $this->container->get('custom_journal'));

        $this->assertNoListeners($this->container->get('ivory.http_adapter.global'));
    }

    public function testLocalLoggerSubscriber()
    {
        $this->container->set('logger', $this->getMock('Psr\Log\LoggerInterface'));
        $this->loadConfiguration($this->container, 'local_logger');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local'),
            Events::PRE_SEND,
            'Ivory\HttpAdapter\Event\Subscriber\LoggerSubscriber'
        );

        $this->assertSame($listener->getLogger(), $this->container->get('logger'));

        $this->assertNoListeners($this->container->get('ivory.http_adapter.global'));
    }

    public function testLocalLoggerSubscriberWithService()
    {
        $this->container->set('custom_logger', $this->getMock('Psr\Log\LoggerInterface'));
        $this->loadConfiguration($this->container, 'local_logger_with_service');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local'),
            Events::PRE_SEND,
            'Ivory\HttpAdapter\Event\Subscriber\LoggerSubscriber'
        );

        $this->assertSame($listener->getLogger(), $this->container->get('custom_logger'));

        $this->assertNoListeners($this->container->get('ivory.http_adapter.global'));
    }

    public function testLocalRedirectSubscriber()
    {
        $this->loadConfiguration($this->container, 'local_redirect');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local'),
            Events::POST_SEND,
            'Ivory\HttpAdapter\Event\Subscriber\RedirectSubscriber'
        );

        $this->assertSame(
            $redirect = $this->container->get('ivory.http_adapter.local.redirect.model'),
            $listener->getRedirect()
        );

        $this->assertSame(5, $redirect->getMax());
        $this->assertFalse($redirect->isStrict());
        $this->assertTrue($redirect->getThrowException());

        $this->assertNoListeners($this->container->get('ivory.http_adapter.global'));
    }

    public function testLocalRedirectSubscriberWithConfiguration()
    {
        $this->loadConfiguration($this->container, 'local_redirect_with_configuration');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local'),
            Events::POST_SEND,
            'Ivory\HttpAdapter\Event\Subscriber\RedirectSubscriber'
        );

        $this->assertSame(
            $redirect = $this->container->get('ivory.http_adapter.local.redirect.model'),
            $listener->getRedirect()
        );

        $this->assertSame(3, $redirect->getMax());
        $this->assertTrue($redirect->isStrict());
        $this->assertFalse($redirect->getThrowException());

        $this->assertNoListeners($this->container->get('ivory.http_adapter.global'));
    }

    public function testLocalRetrySubscriber()
    {
        $this->loadConfiguration($this->container, 'local_retry');
        $this->container->compile();

        $this->assertListener(
            $this->container->get('ivory.http_adapter.local'),
            Events::EXCEPTION,
            'Ivory\HttpAdapter\Event\Subscriber\RetrySubscriber'
        );

        $this->assertNoListeners($this->container->get('ivory.http_adapter.global'));
    }

    public function testLocalStatusCodeSubscriber()
    {
        $this->loadConfiguration($this->container, 'local_status_code');
        $this->container->compile();

        $this->assertListener(
            $this->container->get('ivory.http_adapter.local'),
            Events::POST_SEND,
            'Ivory\HttpAdapter\Event\Subscriber\StatusCodeSubscriber'
        );

        $this->assertNoListeners($this->container->get('ivory.http_adapter.global'));
    }

    public function testLocalStopwatchSubscriber()
    {
        $this->container->set('debug.stopwatch', $this->getMock('Symfony\Component\Stopwatch\Stopwatch'));
        $this->loadConfiguration($this->container, 'local_stopwatch');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local'),
            Events::PRE_SEND,
            'Ivory\HttpAdapter\Event\Subscriber\StopwatchSubscriber'
        );

        $this->assertSame($listener->getStopwatch(), $this->container->get('debug.stopwatch'));

        $this->assertNoListeners($this->container->get('ivory.http_adapter.global'));
    }

    public function testLocalStopwatchSubscriberWithService()
    {
        $this->container->set('custom_stopwatch', $this->getMock('Symfony\Component\Stopwatch\Stopwatch'));
        $this->loadConfiguration($this->container, 'local_stopwatch_with_service');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local'),
            Events::PRE_SEND,
            'Ivory\HttpAdapter\Event\Subscriber\StopwatchSubscriber'
        );

        $this->assertSame($listener->getStopwatch(), $this->container->get('custom_stopwatch'));

        $this->assertNoListeners($this->container->get('ivory.http_adapter.global'));
    }

    public function testListener()
    {
        $this->loadConfiguration($this->container, 'listener');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter'),
            Events::POST_SEND,
            'Ivory\HttpAdapter\Event\Subscriber\StatusCodeSubscriber'
        );

        $this->assertSame($listener, $this->container->get('my_listener'));
    }

    public function testListenerWithAdapter()
    {
        $this->loadConfiguration($this->container, 'listener_with_adapter');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local'),
            Events::POST_SEND,
            'Ivory\HttpAdapter\Event\Subscriber\StatusCodeSubscriber'
        );

        $this->assertSame($listener, $this->container->get('my_listener'));

        $this->assertNoListeners($this->container->get('ivory.http_adapter.global'));
    }

    public function testSubscriber()
    {
        $this->loadConfiguration($this->container, 'subscriber');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter'),
            Events::POST_SEND,
            'Ivory\HttpAdapter\Event\Subscriber\StatusCodeSubscriber'
        );

        $this->assertSame($listener, $this->container->get('my_subscriber'));
    }

    public function testSubscriberWithAdapter()
    {
        $this->loadConfiguration($this->container, 'subscriber_with_adapter');
        $this->container->compile();

        $listener = $this->assertListener(
            $this->container->get('ivory.http_adapter.local'),
            Events::POST_SEND,
            'Ivory\HttpAdapter\Event\Subscriber\StatusCodeSubscriber'
        );

        $this->assertSame($listener, $this->container->get('my_subscriber'));

        $this->assertNoListeners($this->container->get('ivory.http_adapter.global'));
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
            array('guzzle', 'ivory.http_adapter.guzzle', 'Ivory\HttpAdapter\GuzzleHttpAdapter'),
            array('guzzle_http', 'ivory.http_adapter.guzzle_http', 'Ivory\HttpAdapter\GuzzleHttpHttpAdapter'),
            array('httpful', 'ivory.http_adapter.httpful', 'Ivory\HttpAdapter\HttpfulHttpAdapter'),
            array('react', 'ivory.http_adapter.react', 'Ivory\HttpAdapter\ReactHttpAdapter'),
            array('socket', 'ivory.http_adapter.socket', 'Ivory\HttpAdapter\SocketHttpAdapter'),
            array('zend1', 'ivory.http_adapter.zend1', 'Ivory\HttpAdapter\Zend1HttpAdapter'),
            array('zend2', 'ivory.http_adapter.zend2', 'Ivory\HttpAdapter\Zend2HttpAdapter'),
        );
    }

    /**
     * Asserts a listener.
     *
     * @param \Ivory\HttpAdapter\HttpAdapterInterface $httpAdapter The http adapter.
     * @param string                                  $eventName   The event name.
     * @param string                                  $class       The class.
     * @param boolean                                 $unicity     The unicity.
     *
     * @return object The listener.
     */
    private function assertListener($httpAdapter, $eventName, $class, $unicity = true)
    {
        $listeners = $httpAdapter->getConfiguration()->getEventDispatcher()->getListeners($eventName);

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

    /**
     * Asserts there are no listeners.
     *
     * @param \Ivory\HttpAdapter\HttpAdapterInterface $httpAdapter The http adapter.
     */
    private function assertNoListeners($httpAdapter)
    {
        $this->assertEmpty($httpAdapter->getConfiguration()->getEventDispatcher()->getListeners());
    }
}
