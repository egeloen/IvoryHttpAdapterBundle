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

    /** @var \PHPUnit_Framework_MockObject_MockObject|\Ivory\HttpAdapter\Event\Formatter\FormatterInterface */
    private $formatter;

    /** @var \PHPUnit_Framework_MockObject_MockObject|\Ivory\HttpAdapter\Event\Timer\TimerInterface */
    private $timer;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->formatter = $this->createFormatterMock();
        $this->timer = $this->createTimerMock();

        $this->dataCollector = new IvoryHttpAdapterDataCollector($this->formatter, $this->timer);
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

        $this->timer
            ->expects($this->once())
            ->method('start')
            ->with($this->identicalTo($request = $this->createRequestMock()))
            ->will($this->returnValue($startedRequest = $this->createRequestMock()));

        $this->timer
            ->expects($this->once())
            ->method('stop')
            ->with($this->identicalTo($startedRequest))
            ->will($this->returnValue($stoppedRequest = $this->createRequestMock()));

        $this->formatter
            ->expects($this->once())
            ->method('formatRequest')
            ->with($this->identicalTo($stoppedRequest))
            ->will($this->returnValue($formattedRequest = array('request')));

        $this->formatter
            ->expects($this->once())
            ->method('formatResponse')
            ->with($this->identicalTo($response = $this->createResponseMock()))
            ->will($this->returnValue($formattedResponse = array('response')));

        $this->dataCollector->onPreSend($preSendEvent = $this->createPreSendEvent($httpAdapter, $request));
        $this->dataCollector->onPostSend($this->createPostSendEvent($httpAdapter, $startedRequest, $response));

        $this->assertSame($startedRequest, $preSendEvent->getRequest());

        $this->assertCount(1, $this->dataCollector);
        $this->assertCount(1, $responses = $this->dataCollector->getResponses());
        $this->assertArrayHasKey(0, $responses);
        $this->assertEmpty($this->dataCollector->getExceptions());

        $response = $responses[0];

        $this->assertArrayHasKey('adapter', $response);
        $this->assertSame('name', $response['adapter']);

        $this->assertArrayHasKey('request', $response);
        $this->assertSame($formattedRequest, $response['request']);

        $this->assertArrayHasKey('response', $response);
        $this->assertSame($formattedResponse, $response['response']);
    }

    public function testExceptionEvent()
    {
        $httpAdapter = $this->createHttpAdapterMock();

        $this->timer
            ->expects($this->once())
            ->method('start')
            ->with($this->identicalTo($request = $this->createRequestMock()))
            ->will($this->returnValue($startedRequest = $this->createRequestMock()));

        $this->timer
            ->expects($this->once())
            ->method('stop')
            ->with($this->identicalTo($startedRequest))
            ->will($this->returnValue($stoppedRequest = $this->createRequestMock()));

        $this->formatter
            ->expects($this->once())
            ->method('formatRequest')
            ->with($this->identicalTo($stoppedRequest))
            ->will($this->returnValue($formattedRequest = array('request')));

        $exception = $this->createExceptionMock($startedRequest);
        $exception
            ->expects($this->once())
            ->method('setRequest')
            ->with($this->identicalTo($stoppedRequest));

        $this->formatter
            ->expects($this->once())
            ->method('formatException')
            ->with($this->identicalTo($exception))
            ->will($this->returnValue($formattedException = array('exception')));

        $this->dataCollector->onPreSend($preSendEvent = $this->createPreSendEvent($httpAdapter, $request));
        $this->dataCollector->onException($this->createExceptionEvent($httpAdapter, $exception));

        $this->assertSame($startedRequest, $preSendEvent->getRequest());

        $this->assertCount(1, $this->dataCollector);
        $this->assertCount(1, $exceptions = $this->dataCollector->getExceptions());
        $this->assertArrayHasKey(0, $exceptions);
        $this->assertEmpty($this->dataCollector->getResponses());

        $exception = $exceptions[0];

        $this->assertArrayHasKey('adapter', $exception);
        $this->assertSame('name', $exception['adapter']);

        $this->assertArrayHasKey('request', $exception);
        $this->assertSame($formattedRequest, $exception['request']);

        $this->assertArrayHasKey('exception', $exception);
        $this->assertSame($formattedException, $exception['exception']);
    }

    public function testMultiPostSendEvent()
    {
        $httpAdapter = $this->createHttpAdapterMock();

        $requests = [
            $request1 = $this->createRequestMock(),
            $request2 = $this->createRequestMock(),
        ];

        $this->timer
            ->expects($this->exactly(2))
            ->method('start')
            ->will($this->returnValueMap([
                [$request1, $startedRequest1 = $this->createRequestMock()],
                [$request2, $startedRequest2 = $this->createRequestMock()],
            ]));

        $this->timer
            ->expects($this->exactly(2))
            ->method('stop')
            ->will($this->returnValueMap([
                [$startedRequest1, $stoppedRequest1 = $this->createRequestMock()],
                [$startedRequest2, $stoppedRequest2 = $this->createRequestMock()],
            ]));

        $this->formatter
            ->expects($this->exactly(2))
            ->method('formatRequest')
            ->will($this->returnValueMap([
                [$stoppedRequest1, $formattedRequest1 = ['request1']],
                [$stoppedRequest2, $formattedRequest2 = ['request2']],
            ]));

        $responses = [
            $response1 = $this->createResponseMock($startedRequest1),
            $response2 = $this->createResponseMock($startedRequest2),
        ];

        $response1
            ->expects($this->once())
            ->method('withParameter')
            ->with($this->identicalTo('request'), $this->identicalTo($stoppedRequest1))
            ->will($this->returnValue($updatedResponse1 = $this->createResponseMock($stoppedRequest1)));

        $response2
            ->expects($this->once())
            ->method('withParameter')
            ->with($this->identicalTo('request'), $this->identicalTo($stoppedRequest2))
            ->will($this->returnValue($updatedResponse2 = $this->createResponseMock($stoppedRequest2)));

        $this->formatter
            ->expects($this->exactly(2))
            ->method('formatResponse')
            ->will($this->returnValueMap([
                [$response1, $formattedResponse1 = ['response1']],
                [$response2, $formattedResponse2 = ['response2']],
            ]));

        $this->dataCollector->onMultiPreSend($this->createMultiPreSendEvent($httpAdapter, $requests));
        $this->dataCollector->onMultiPostSend($this->createMultiPostSendEvent($httpAdapter, $responses));

        $this->assertCount(count($responses), $this->dataCollector);
        $this->assertCount(count($responses), $responses = $this->dataCollector->getResponses());
        $this->assertArrayHasKey(0, $responses);
        $this->assertArrayHasKey(1, $responses);
        $this->assertEmpty($this->dataCollector->getExceptions());

        $response1 = $responses[0];
        $response2 = $responses[1];

        $this->assertArrayHasKey('adapter', $response1);
        $this->assertSame('name', $response1['adapter']);

        $this->assertArrayHasKey('adapter', $response2);
        $this->assertSame('name', $response2['adapter']);

        $this->assertArrayHasKey('request', $response1);
        $this->assertSame($formattedRequest1, $response1['request']);

        $this->assertArrayHasKey('request', $response2);
        $this->assertSame($formattedRequest2, $response2['request']);

        $this->assertArrayHasKey('response', $response1);
        $this->assertSame($formattedResponse1, $response1['response']);

        $this->assertArrayHasKey('response', $response2);
        $this->assertSame($formattedResponse2, $response2['response']);
    }

    public function testMultiExceptionEvent()
    {
        $httpAdapter = $this->createHttpAdapterMock();

        $requests = array(
            $request1 = $this->createRequestMock(),
            $request2 = $this->createRequestMock(),
        );

        $this->timer
            ->expects($this->exactly(2))
            ->method('start')
            ->will($this->returnValueMap([
                [$request1, $startedRequest1 = $this->createRequestMock()],
                [$request2, $startedRequest2 = $this->createRequestMock()],
            ]));

        $this->timer
            ->expects($this->exactly(2))
            ->method('stop')
            ->will($this->returnValueMap([
                [$startedRequest1, $stoppedRequest1 = $this->createRequestMock()],
                [$startedRequest2, $stoppedRequest2 = $this->createRequestMock()],
            ]));

        $this->formatter
            ->expects($this->exactly(2))
            ->method('formatRequest')
            ->will($this->returnValueMap(array(
                array($stoppedRequest1, $formattedRequest1 = array('request1')),
                array($stoppedRequest2, $formattedRequest2 = array('request2')),
            )));

        $exceptions = array(
            $exception1 = $this->createExceptionMock($startedRequest1),
            $exception2 = $this->createExceptionMock($startedRequest2),
        );

        $this->formatter
            ->expects($this->exactly(2))
            ->method('formatException')
            ->will($this->returnValueMap(array(
                array($exception1, $formattedException1 = array('exception1')),
                array($exception2, $formattedException2 = array('exception2')),
            )));

        $this->dataCollector->onMultiPreSend($this->createMultiPreSendEvent($httpAdapter, $requests));
        $this->dataCollector->onMultiException($this->createMultiExceptionEvent($httpAdapter, $exceptions));

        $this->assertCount(2, $this->dataCollector);
        $this->assertCount(2, $exceptions = $this->dataCollector->getExceptions());
        $this->assertArrayHasKey(0, $exceptions);
        $this->assertArrayHasKey(1, $exceptions);
        $this->assertEmpty($this->dataCollector->getResponses());

        $exception1 = $exceptions[0];
        $exception2 = $exceptions[1];

        $this->assertArrayHasKey('adapter', $exception1);
        $this->assertSame('name', $exception1['adapter']);

        $this->assertArrayHasKey('adapter', $exception2);
        $this->assertSame('name', $exception2['adapter']);

        $this->assertArrayHasKey('request', $exception1);
        $this->assertSame($formattedRequest1, $exception1['request']);

        $this->assertArrayHasKey('request', $exception2);
        $this->assertSame($formattedRequest2, $exception2['request']);

        $this->assertArrayHasKey('exception', $exception1);
        $this->assertSame($formattedException1, $exception1['exception']);

        $this->assertArrayHasKey('exception', $exception2);
        $this->assertSame($formattedException2, $exception2['exception']);
    }

    public function testSerialize()
    {
        $httpAdapter = $this->createHttpAdapterMock();

        $this->timer
            ->expects($this->once())
            ->method('start')
            ->with($this->identicalTo($request = $this->createRequestMock()))
            ->will($this->returnValue($startedRequest = $this->createRequestMock()));

        $this->timer
            ->expects($this->once())
            ->method('stop')
            ->with($this->identicalTo($startedRequest))
            ->will($this->returnValue($stoppedRequest = $this->createRequestMock()));

        $this->dataCollector->onPreSend($preSendEvent = $this->createPreSendEvent($httpAdapter, $request));

        $this->dataCollector->onPostSend($this->createPostSendEvent(
            $httpAdapter,
            $preSendEvent->getRequest(),
            $this->createResponseMock($preSendEvent->getRequest())
        ));

        $dataCollector = unserialize(serialize($this->dataCollector));

        $this->assertSame(count($this->dataCollector), count($dataCollector));
        $this->assertSame($this->dataCollector->getResponses(), $dataCollector->getResponses());
        $this->assertSame($this->dataCollector->getExceptions(), $dataCollector->getExceptions());
        $this->assertSame($this->dataCollector->getTime(), $dataCollector->getTime());
    }

    public function testSubscribedEvents()
    {
        $this->assertSame(array(
            'ivory.http_adapter.pre_send'        => array('onPreSend', 100),
            'ivory.http_adapter.post_send'       => array('onPostSend', 100),
            'ivory.http_adapter.exception'       => array('onException', 100),
            'ivory.http_adapter.multi_pre_send'  => array('onMultiPreSend', 100),
            'ivory.http_adapter.multi_post_send' => array('onMultiPostSend', 100),
            'ivory.http_adapter.multi_exception' => array('onMultiException', 100),
        ), $this->dataCollector->getSubscribedEvents());
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
    private function createMultiPreSendEvent(HttpAdapterInterface $httpAdapter = null, array $requests = [])
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
    private function createMultiPostSendEvent(HttpAdapterInterface $httpAdapter = null, array $responses = [])
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
    private function createMultiExceptionEvent(HttpAdapterInterface $httpAdapter = null, array $exceptions = [])
    {
        return new MultiExceptionEvent($httpAdapter ?: $this->createHttpAdapterMock(), $exceptions);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|\Ivory\HttpAdapter\Event\Formatter\FormatterInterface
     */
    private function createFormatterMock()
    {
        return $this->getMock('Ivory\HttpAdapter\Event\Formatter\FormatterInterface');
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|\Ivory\HttpAdapter\Event\Timer\TimerInterface
     */
    private function createTimerMock()
    {
        return $this->getMock('Ivory\HttpAdapter\Event\Timer\TimerInterface');
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
            ->will($this->returnValue(['foo' => 'bar']));

        $request
            ->expects($this->any())
            ->method('getRawDatas')
            ->will($this->returnValue('foo=bar'));

        $request
            ->expects($this->any())
            ->method('getDatas')
            ->will($this->returnValue(['baz' => 'bat']));

        $request
            ->expects($this->any())
            ->method('getFiles')
            ->will($this->returnValue(['bit' => __FILE__]));

        $request
            ->expects($this->any())
            ->method('getParameters')
            ->will($this->returnValue(['time' => 0.1]));

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
            ->will($this->returnValue(['bal' => 'bol']));

        $response
            ->expects($this->any())
            ->method('getBody')
            ->will($this->returnValue('body'));

        $response
            ->expects($this->any())
            ->method('getParameters')
            ->will($this->returnValue(['bil' => 'bob']));

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
