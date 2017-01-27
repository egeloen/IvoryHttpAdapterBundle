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

use Ivory\HttpAdapter\ConfigurationInterface;
use Ivory\HttpAdapter\Event\Formatter\FormatterInterface;
use Ivory\HttpAdapter\Event\MultiRequestCreatedEvent;
use Ivory\HttpAdapter\Event\MultiRequestErroredEvent;
use Ivory\HttpAdapter\Event\MultiRequestSentEvent;
use Ivory\HttpAdapter\Event\RequestCreatedEvent;
use Ivory\HttpAdapter\Event\RequestErroredEvent;
use Ivory\HttpAdapter\Event\RequestSentEvent;
use Ivory\HttpAdapter\Event\Timer\TimerInterface;
use Ivory\HttpAdapter\HttpAdapterException;
use Ivory\HttpAdapter\HttpAdapterInterface;
use Ivory\HttpAdapter\Message\InternalRequestInterface;
use Ivory\HttpAdapter\Message\ResponseInterface;
use Ivory\HttpAdapterBundle\DataCollector\IvoryHttpAdapterDataCollector;
use Ivory\HttpAdapterBundle\Tests\AbstractTestCase;

/**
 * @author GeLo <geloen.eric@gmail.com>
 */
class IvoryHttpAdapterDataCollectorTest extends AbstractTestCase
{
    /**
     * @var IvoryHttpAdapterDataCollector
     */
    private $dataCollector;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|FormatterInterface
     */
    private $formatter;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|TimerInterface
     */
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
            $this->createMock('Symfony\Component\HttpFoundation\Request'),
            $this->createMock('Symfony\Component\HttpFoundation\Response')
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
            ->will($this->returnValue($formattedRequest = ['request']));

        $this->formatter
            ->expects($this->once())
            ->method('formatResponse')
            ->with($this->identicalTo($response = $this->createResponseMock()))
            ->will($this->returnValue($formattedResponse = ['response']));

        $this->dataCollector->onRequestCreated($preSendEvent = $this->createRequestCreatedEvent($httpAdapter, $request));
        $this->dataCollector->onRequestSent($this->createRequestSentEvent($httpAdapter, $startedRequest, $response));

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
            ->will($this->returnValue($formattedRequest = ['request']));

        $exception = $this->createExceptionMock($startedRequest);
        $exception
            ->expects($this->once())
            ->method('setRequest')
            ->with($this->identicalTo($stoppedRequest));

        $this->formatter
            ->expects($this->once())
            ->method('formatException')
            ->with($this->identicalTo($exception))
            ->will($this->returnValue($formattedException = ['exception']));

        $this->dataCollector->onRequestCreated($preSendEvent = $this->createRequestCreatedEvent($httpAdapter, $request));
        $this->dataCollector->onRequestErrored($this->createRequestErroredEvent($httpAdapter, $exception));

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

        $this->dataCollector->onMultiRequestCreated($this->createMultiRequestCreatedEvent($httpAdapter, $requests));
        $this->dataCollector->onMultiRequestSent($this->createMultiRequestSentEvent($httpAdapter, $responses));

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

        $exceptions = [
            $exception1 = $this->createExceptionMock($startedRequest1),
            $exception2 = $this->createExceptionMock($startedRequest2),
        ];

        $this->formatter
            ->expects($this->exactly(2))
            ->method('formatException')
            ->will($this->returnValueMap([
                [$exception1, $formattedException1 = ['exception1']],
                [$exception2, $formattedException2 = ['exception2']],
            ]));

        $this->dataCollector->onMultiRequestCreated($this->createMultiRequestCreatedEvent($httpAdapter, $requests));
        $this->dataCollector->onMultiRequestErrored($this->createMultiRequestErroredEvent($httpAdapter, $exceptions));

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

        $this->dataCollector->onRequestCreated($preSendEvent = $this->createRequestCreatedEvent($httpAdapter, $request));

        $this->dataCollector->onRequestSent($this->createRequestSentEvent(
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
        $this->assertSame([
            'ivory.http_adapter.request_created'       => ['onRequestCreated', 100],
            'ivory.http_adapter.request_sent'          => ['onRequestSent', 100],
            'ivory.http_adapter.request_errored'       => ['onRequestErrored', 100],
            'ivory.http_adapter.multi_request_created' => ['onMultiRequestCreated', 100],
            'ivory.http_adapter.multi_request_sent'    => ['onMultiRequestSent', 100],
            'ivory.http_adapter.multi_request_errored' => ['onMultiRequestErrored', 100],
        ], $this->dataCollector->getSubscribedEvents());
    }

    /**
     * @param HttpAdapterInterface|null     $httpAdapter
     * @param InternalRequestInterface|null $request
     *
     * @return RequestCreatedEvent
     */
    private function createRequestCreatedEvent(
        HttpAdapterInterface $httpAdapter = null,
        InternalRequestInterface $request = null
    ) {
        return new RequestCreatedEvent(
            $httpAdapter ?: $this->createHttpAdapterMock(),
            $request ?: $this->createRequestMock()
        );
    }

    /**
     * @param HttpAdapterInterface|null     $httpAdapter
     * @param InternalRequestInterface|null $request
     * @param ResponseInterface|null        $response
     *
     * @return RequestSentEvent
     */
    private function createRequestSentEvent(
        HttpAdapterInterface $httpAdapter = null,
        InternalRequestInterface $request = null,
        ResponseInterface $response = null
    ) {
        return new RequestSentEvent(
            $httpAdapter ?: $this->createHttpAdapterMock(),
            $request ?: $this->createRequestMock(),
            $response ?: $this->createResponseMock()
        );
    }

    /**
     * @param HttpAdapterInterface|null $httpAdapter
     * @param HttpAdapterException|null $exception
     *
     * @return RequestErroredEvent
     */
    private function createRequestErroredEvent(
        HttpAdapterInterface $httpAdapter = null,
        HttpAdapterException $exception = null
    ) {
        return new RequestErroredEvent(
            $httpAdapter ?: $this->createHttpAdapterMock(),
            $exception ?: $this->createExceptionMock()
        );
    }

    /**
     * @param HttpAdapterInterface|null $httpAdapter
     * @param array                     $requests
     *
     * @return MultiRequestCreatedEvent
     */
    private function createMultiRequestCreatedEvent(HttpAdapterInterface $httpAdapter = null, array $requests = [])
    {
        return new MultiRequestCreatedEvent($httpAdapter ?: $this->createHttpAdapterMock(), $requests);
    }

    /**
     * @param HttpAdapterInterface|null $httpAdapter
     * @param array                     $responses
     *
     * @return MultiRequestSentEvent
     */
    private function createMultiRequestSentEvent(HttpAdapterInterface $httpAdapter = null, array $responses = [])
    {
        return new MultiRequestSentEvent($httpAdapter ?: $this->createHttpAdapterMock(), $responses);
    }

    /**
     * @param HttpAdapterInterface|null $httpAdapter
     * @param array                     $exceptions
     *
     * @return MultiRequestErroredEvent
     */
    private function createMultiRequestErroredEvent(HttpAdapterInterface $httpAdapter = null, array $exceptions = [])
    {
        return new MultiRequestErroredEvent($httpAdapter ?: $this->createHttpAdapterMock(), $exceptions);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|FormatterInterface
     */
    private function createFormatterMock()
    {
        return $this->createMock('Ivory\HttpAdapter\Event\Formatter\FormatterInterface');
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|TimerInterface
     */
    private function createTimerMock()
    {
        return $this->createMock('Ivory\HttpAdapter\Event\Timer\TimerInterface');
    }

    /**
     * @return HttpAdapterInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createHttpAdapterMock()
    {
        $httpAdapter = $this->createMock('Ivory\HttpAdapter\HttpAdapterInterface');
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
     * @return ConfigurationInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createConfigurationMock()
    {
        return $this->createMock('Ivory\HttpAdapter\ConfigurationInterface');
    }

    /**
     * @return InternalRequestInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createRequestMock()
    {
        $request = $this->createMock('Ivory\HttpAdapter\Message\InternalRequestInterface');
        $request
            ->expects($this->any())
            ->method('getProtocolVersion')
            ->will($this->returnValue('1.1'));

        $request
            ->expects($this->any())
            ->method('getUri')
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
            ->method('getBody')
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
     * @param InternalRequestInterface|null $request
     *
     * @return ResponseInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createResponseMock(InternalRequestInterface $request = null)
    {
        $response = $this->createMock('Ivory\HttpAdapter\Message\ResponseInterface');
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
     * @param InternalRequestInterface|null $request
     * @param ResponseInterface|null        $response
     *
     * @return HttpAdapterException|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createExceptionMock(InternalRequestInterface $request = null, ResponseInterface $response = null)
    {
        $exception = $this->createMock('Ivory\HttpAdapter\HttpAdapterException');
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
