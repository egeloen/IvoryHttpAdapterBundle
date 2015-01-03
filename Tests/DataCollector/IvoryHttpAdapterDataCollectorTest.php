<?php

/*
 * This file is part of the Ivory Http Adapter bundle package.
 *
 * (c) Eric GELOEN <geloen.eric@gmail.com>
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 */

namespace Ivory\HttpAdapterBundle\Tests\DataCollector;

use Ivory\HttpAdapter\Event\ExceptionEvent;
use Ivory\HttpAdapter\Event\MultiExceptionEvent;
use Ivory\HttpAdapter\Event\MultiPostSendEvent;
use Ivory\HttpAdapter\Event\MultiPreSendEvent;
use Ivory\HttpAdapter\Event\PostSendEvent;
use Ivory\HttpAdapter\Event\PreSendEvent;
use Ivory\HttpAdapter\HttpAdapterInterface;
use Ivory\HttpAdapter\HttpAdapterException;
use Ivory\HttpAdapter\Message\InternalRequestInterface;
use Ivory\HttpAdapter\Message\ResponseInterface;
use Ivory\HttpAdapterBundle\DataCollector\IvoryHttpAdapterDataCollector;

/**
 * Ivory http adapter data collector test.
 *
 * @author GeLo <geloen.eric@gmail.com>
 */
class IvoryHttpAdapterDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    /** @var \Ivory\HttpAdapterBundle\DataCollector\IvoryHttpAdapterDataCollector */
    private $dataCollector;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->dataCollector = new IvoryHttpAdapterDataCollector();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        unset($this->dataCollector);
    }

    public function testDefaultState()
    {
        $this->assertEmpty($this->dataCollector->getResponses());
        $this->assertEmpty($this->dataCollector->getExceptions());
        $this->assertCount(0, $this->dataCollector);
        $this->assertSame(0, $this->dataCollector->getTime());
        $this->assertSame('ivory.http_adapter', $this->dataCollector->getName());
    }

    public function testCollect()
    {
        $this->dataCollector->collect(
            $this->getMock('Symfony\Component\HttpFoundation\Request'),
            $this->getMock('Symfony\Component\HttpFoundation\Response')
        );
    }

    public function testPostSendEvent()
    {
        $httpAdapter = $this->createHttpAdapterMock();
        $request = $this->createRequestMock();
        $response = $this->createResponseMock();

        $this->dataCollector->onPreSend($this->createPreSendEvent($httpAdapter, $request));
        $this->dataCollector->onPostSend($this->createPostSendEvent($httpAdapter, $request, $response));

        $this->assertCount(1, $this->dataCollector);
        $this->assertCount(1, $this->dataCollector->getResponses());
        $this->assertEmpty($this->dataCollector->getExceptions());

        foreach ($this->dataCollector->getResponses() as $debug) {
            $this->assertArrayHasKey('adapter', $debug);
            $this->assertSame('name', $debug['adapter']);

            $this->assertArrayHasKey('request', $debug);
            $this->assertSame(
                array(
                    'protocol_version' => $request->getProtocolVersion(),
                    'url'              => $request->getUrl(),
                    'method'           => $request->getMethod(),
                    'headers'          => array('foo' => 'bar'),
                    'raw_datas'        => 'foo=bar',
                    'datas'            => array('baz' => 'bat'),
                    'files'            => array('bit' => __FILE__),
                    'parameters'       => array('time' => 0.1),
                ),
                $debug['request']
            );

            $this->assertArrayHasKey('response', $debug);
            $this->assertSame(
                array(
                    'protocol_version' => $response->getProtocolVersion(),
                    'status_code'      => $response->getStatusCode(),
                    'reason_phrase'    => $response->getReasonPhrase(),
                    'headers'          => array('bal' => 'bol'),
                    'body'             => 'body',
                    'parameters'       => array('bil' => 'bob'),
                ),
                $debug['response']
            );
        }
    }

    public function testExceptionEvent()
    {
        $httpAdapter = $this->createHttpAdapterMock();
        $request = $this->createRequestMock();
        $exception = $this->createExceptionMock($request);

        $this->dataCollector->onPreSend($this->createPreSendEvent($httpAdapter, $request));
        $this->dataCollector->onException($this->createExceptionEvent($httpAdapter, $exception));

        $this->assertCount(1, $this->dataCollector);
        $this->assertCount(1, $this->dataCollector->getExceptions());
        $this->assertEmpty($this->dataCollector->getResponses());

        foreach ($this->dataCollector->getExceptions() as $debug) {
            $this->assertArrayHasKey('adapter', $debug);
            $this->assertSame('name', $debug['adapter']);

            $this->assertArrayHasKey('request', $debug);
            $this->assertSame(
                array(
                    'protocol_version' => $request->getProtocolVersion(),
                    'url'              => $request->getUrl(),
                    'method'           => $request->getMethod(),
                    'headers'          => array('foo' => 'bar'),
                    'raw_datas'        => 'foo=bar',
                    'datas'            => array('baz' => 'bat'),
                    'files'            => array('bit' => __FILE__),
                    'parameters'       => array('time' => 0.1),
                ),
                $debug['request']
            );

            $this->assertArrayHasKey('exception', $debug);
            $this->assertSame(
                array(
                    'code'    => $exception->getCode(),
                    'message' => $exception->getMessage(),
                    'line'    => $exception->getLine(),
                    'file'    => $exception->getFile(),
                ),
                $debug['exception']
            );
        }
    }

    public function testMultiPostSendEvent()
    {
        $httpAdapter = $this->createHttpAdapterMock();

        $requests = array(
            $request1 = $this->createRequestMock(),
            $request2 = $this->createRequestMock(),
        );

        $responses = array(
            $response1 = $this->createResponseMock($request1),
            $this->createResponseMock($request2),
        );

        $this->dataCollector->onMultiPreSend($this->createMultiPreSendEvent($httpAdapter, $requests));
        $this->dataCollector->onMultiPostSend($this->createMultiPostSendEvent($httpAdapter, $responses));

        $this->assertCount(count($responses), $this->dataCollector);
        $this->assertCount(count($responses), $this->dataCollector->getResponses());
        $this->assertEmpty($this->dataCollector->getExceptions());

        foreach ($this->dataCollector->getResponses() as $debug) {
            $this->assertArrayHasKey('adapter', $debug);
            $this->assertSame('name', $debug['adapter']);

            $this->assertArrayHasKey('request', $debug);
            $this->assertSame(
                array(
                    'protocol_version' => $request1->getProtocolVersion(),
                    'url'              => $request1->getUrl(),
                    'method'           => $request1->getMethod(),
                    'headers'          => array('foo' => 'bar'),
                    'raw_datas'        => 'foo=bar',
                    'datas'            => array('baz' => 'bat'),
                    'files'            => array('bit' => __FILE__),
                    'parameters'       => array('time' => 0.1),
                ),
                $debug['request']
            );

            $this->assertArrayHasKey('response', $debug);
            $this->assertSame(
                array(
                    'protocol_version' => $response1->getProtocolVersion(),
                    'status_code'      => $response1->getStatusCode(),
                    'reason_phrase'    => $response1->getReasonPhrase(),
                    'headers'          => array('bal' => 'bol'),
                    'body'             => 'body',
                    'parameters'       => array('bil' => 'bob'),
                ),
                $debug['response']
            );
        }
    }

    public function testMultiExceptionEvent()
    {
        $httpAdapter = $this->createHttpAdapterMock();

        $requests = array(
            $request1 = $this->createRequestMock(),
            $request2 = $this->createRequestMock(),
        );

        $exceptions = array(
            $exception1 = $this->createExceptionMock($request1),
            $this->createExceptionMock($request2),
        );

        $this->dataCollector->onMultiPreSend($this->createMultiPreSendEvent($httpAdapter, $requests));
        $this->dataCollector->onMultiException($this->createMultiExceptionEvent($httpAdapter, $exceptions));

        $this->assertCount(count($exceptions), $this->dataCollector);
        $this->assertCount(count($exceptions), $this->dataCollector->getExceptions());
        $this->assertEmpty($this->dataCollector->getResponses());

        foreach ($this->dataCollector->getExceptions() as $debug) {
            $this->assertArrayHasKey('adapter', $debug);
            $this->assertSame('name', $debug['adapter']);

            $this->assertArrayHasKey('request', $debug);
            $this->assertSame(
                array(
                    'protocol_version' => $request1->getProtocolVersion(),
                    'url'              => $request1->getUrl(),
                    'method'           => $request1->getMethod(),
                    'headers'          => array('foo' => 'bar'),
                    'raw_datas'        => 'foo=bar',
                    'datas'            => array('baz' => 'bat'),
                    'files'            => array('bit' => __FILE__),
                    'parameters'       => array('time' => 0.1),
                ),
                $debug['request']
            );

            $this->assertArrayHasKey('exception', $debug);
            $this->assertSame(
                array(
                    'code'    => $exception1->getCode(),
                    'message' => $exception1->getMessage(),
                    'line'    => $exception1->getLine(),
                    'file'    => $exception1->getFile(),
                ),
                $debug['exception']
            );
        }
    }

    public function testSerialize()
    {
        $this->dataCollector->onPreSend($this->createPreSendEvent());
        $this->dataCollector->onPostSend($this->createPostSendEvent());

        $this->dataCollector->onPreSend($this->createPreSendEvent());
        $this->dataCollector->onPostSend($this->createPostSendEvent());

        $dataCollector = unserialize(serialize($this->dataCollector));

        $this->assertSame(count($this->dataCollector), count($dataCollector));
        $this->assertSame($this->dataCollector->getResponses(), $dataCollector->getResponses());
        $this->assertSame($this->dataCollector->getExceptions(), $dataCollector->getExceptions());
        $this->assertSame($this->dataCollector->getTime(), $dataCollector->getTime());
    }

    /**
     * Creates a pre send event.
     *
     * @param \Ivory\HttpAdapter\HttpAdapterInterface|null             $httpAdapter The http adapter.
     * @param \Ivory\HttpAdapter\Message\InternalRequestInterface|null $request     The request.
     *
     * @return \Ivory\HttpAdapter\Event\PreSendEvent The pre send event.
     */
    private function createPreSendEvent(
        HttpAdapterInterface $httpAdapter = null,
        InternalRequestInterface $request = null
    ) {
        return new PreSendEvent(
            $httpAdapter ?: $this->createHttpAdapterMock(),
            $request ?: $this->createRequestMock()
        );
    }

    /**
     * Creates a post send event.
     *
     * @param \Ivory\HttpAdapter\HttpAdapterInterface|null             $httpAdapter The http adapter.
     * @param \Ivory\HttpAdapter\Message\InternalRequestInterface|null $request     The request.
     * @param \Ivory\HttpAdapter\Message\ResponseInterface|null        $response    The response.
     *
     * @return \Ivory\HttpAdapter\Event\PostSendEvent The post send event.
     */
    private function createPostSendEvent(
        HttpAdapterInterface $httpAdapter = null,
        InternalRequestInterface $request = null,
        ResponseInterface $response = null
    ) {
        return new PostSendEvent(
            $httpAdapter ?: $this->createHttpAdapterMock(),
            $request ?: $this->createRequestMock(),
            $response ?: $this->createResponseMock()
        );
    }

    /**
     * Creates an exception event.
     *
     * @param \Ivory\HttpAdapter\HttpAdapterInterface|null $httpAdapter The http adapter.
     * @param \Ivory\HttpAdapter\HttpAdapterException|null $exception   The exception.
     *
     * @return \Ivory\HttpAdapter\Event\ExceptionEvent The exception event.
     */
    private function createExceptionEvent(
        HttpAdapterInterface $httpAdapter = null,
        HttpAdapterException $exception = null
    ) {
        return new ExceptionEvent(
            $httpAdapter ?: $this->createHttpAdapterMock(),
            $exception ?: $this->createExceptionMock()
        );
    }

    /**
     * Creates a multi pre send event.
     *
     * @param \Ivory\HttpAdapter\HttpAdapterInterface|null $httpAdapter The http adapter.
     * @param array                                        $requests    The requests.
     *
     * @return \Ivory\HttpAdapter\Event\MultiPreSendEvent The multi pre send event.
     */
    private function createMultiPreSendEvent(HttpAdapterInterface $httpAdapter = null, array $requests = array())
    {
        return new MultiPreSendEvent($httpAdapter ?: $this->createHttpAdapterMock(), $requests);
    }

    /**
     * Creates a multi post send event.
     *
     * @param \Ivory\HttpAdapter\HttpAdapterInterface|null $httpAdapter The http adapter.
     * @param array                                        $responses   The responses.
     *
     * @return \Ivory\HttpAdapter\Event\MultiPostSendEvent The multi post send event.
     */
    private function createMultiPostSendEvent(HttpAdapterInterface $httpAdapter = null, array $responses = array())
    {
        return new MultiPostSendEvent($httpAdapter ?: $this->createHttpAdapterMock(), $responses);
    }

    /**
     * Creates a multi exception event.
     *
     * @param \Ivory\HttpAdapter\HttpAdapterInterface|null $httpAdapter The http adapter.
     * @param array                                        $exceptions  The exceptions.
     *
     * @return \Ivory\HttpAdapter\Event\MultiExceptionEvent The multi exception event.
     */
    private function createMultiExceptionEvent(HttpAdapterInterface $httpAdapter = null, array $exceptions = array())
    {
        return new MultiExceptionEvent($httpAdapter ?: $this->createHttpAdapterMock(), $exceptions);
    }

    /**
     * Creates an http adapter mock.
     *
     * @return \Ivory\HttpAdapter\HttpAdapterInterface|\PHPUnit_Framework_MockObject_MockObject The http adapter mock.
     */
    private function createHttpAdapterMock()
    {
        $httpAdapter = $this->getMock('Ivory\HttpAdapter\HttpAdapterInterface');
        $httpAdapter
            ->expects($this->any())
            ->method('getConfiguration')
            ->will($this->returnValue($this->createConfigurationMock()));

        $httpAdapter
            ->expects($this->any())
            ->method('getName')
            ->will($this->returnValue('name'));

        return $httpAdapter;
    }

    /**
     * Creates a configuration mock.
     *
     * @return \Ivory\HttpAdapter\ConfigurationInterface|\PHPUnit_Framework_MockObject_MockObject The configuration mock.
     */
    private function createConfigurationMock()
    {
        return $this->getMock('Ivory\HttpAdapter\ConfigurationInterface');
    }

    /**
     * Creates a request mock.
     *
     * @return \Ivory\HttpAdapter\Message\InternalRequestInterface|\PHPUnit_Framework_MockObject_MockObject The request mock.
     */
    private function createRequestMock()
    {
        $request = $this->getMock('Ivory\HttpAdapter\Message\InternalRequestInterface');
        $request
            ->expects($this->any())
            ->method('getProtocolVersion')
            ->will($this->returnValue('1.1'));

        $request
            ->expects($this->any())
            ->method('getUrl')
            ->will($this->returnValue('http://egeloen.fr'));

        $request
            ->expects($this->any())
            ->method('getMethod')
            ->will($this->returnValue('GET'));

        $request
            ->expects($this->any())
            ->method('getHeaders')
            ->will($this->returnValue(array('foo' => 'bar')));

        $request
            ->expects($this->any())
            ->method('getRawDatas')
            ->will($this->returnValue('foo=bar'));

        $request
            ->expects($this->any())
            ->method('getDatas')
            ->will($this->returnValue(array('baz' => 'bat')));

        $request
            ->expects($this->any())
            ->method('getFiles')
            ->will($this->returnValue(array('bit' => __FILE__)));

        $request
            ->expects($this->any())
            ->method('getParameters')
            ->will($this->returnValue(array('time' => 0.1)));

        return $request;
    }

    /**
     * Creates a response mock.
     *
     * @param \Ivory\HttpAdapter\Message\InternalRequestInterface|null $request The request.
     *
     * @return \Ivory\HttpAdapter\Message\ResponseInterface|\PHPUnit_Framework_MockObject_MockObject The response mock.
     */
    private function createResponseMock(InternalRequestInterface $request = null)
    {
        $response = $this->getMock('Ivory\HttpAdapter\Message\ResponseInterface');
        $response
            ->expects($this->any())
            ->method('getProtocolVersion')
            ->will($this->returnValue('1.1'));

        $response
            ->expects($this->any())
            ->method('getStatusCode')
            ->will($this->returnValue(200));

        $response
            ->expects($this->any())
            ->method('getReasonPhrase')
            ->will($this->returnValue('OK'));

        $response
            ->expects($this->any())
            ->method('getHeaders')
            ->will($this->returnValue(array('bal' => 'bol')));

        $response
            ->expects($this->any())
            ->method('getBody')
            ->will($this->returnValue('body'));

        $response
            ->expects($this->any())
            ->method('getParameters')
            ->will($this->returnValue(array('bil' => 'bob')));

        if ($request !== null) {
            $response
                ->expects($this->any())
                ->method('hasParameter')
                ->with($this->identicalTo('request'))
                ->will($this->returnValue(true));

            $response
                ->expects($this->any())
                ->method('getParameter')
                ->with($this->identicalTo('request'))
                ->will($this->returnValue($request));
        }

        return $response;
    }

    /**
     * Creates an exception mock.
     *
     * @param \Ivory\HttpAdapter\Message\InternalRequestInterface|null $request  The request.
     * @param \Ivory\HttpAdapter\Message\ResponseInterface|null        $response The response.
     *
     * @return \Ivory\HttpAdapter\HttpAdapterException|\PHPUnit_Framework_MockObject_MockObject The exception mock.
     */
    private function createExceptionMock(InternalRequestInterface $request = null, ResponseInterface $response = null)
    {
        $exception = $this->getMock('Ivory\HttpAdapter\HttpAdapterException');
        $exception
            ->expects($this->any())
            ->method('getCode')
            ->will($this->returnValue(123));

        $exception
            ->expects($this->any())
            ->method('getMessage')
            ->will($this->returnValue('message'));

        $exception
            ->expects($this->any())
            ->method('getLine')
            ->will($this->returnValue(234));

        $exception
            ->expects($this->any())
            ->method('getFile')
            ->will($this->returnValue(__FILE__));

        if ($request !== null) {
            $exception
                ->expects($this->any())
                ->method('hasRequest')
                ->will($this->returnValue(true));

            $exception
                ->expects($this->any())
                ->method('getRequest')
                ->will($this->returnValue($request));
        }

        if ($response !== null) {
            $exception
                ->expects($this->any())
                ->method('hasRsponse')
                ->will($this->returnValue(true));

            $exception
                ->expects($this->any())
                ->method('getResponse')
                ->will($this->returnValue($response));
        }

        return $exception;
    }
}
