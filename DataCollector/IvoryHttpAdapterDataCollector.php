<?php

/*
 * This file is part of the Ivory Http Adapter bundle package.
 *
 * (c) Eric GELOEN <geloen.eric@gmail.com>
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 */

namespace Ivory\HttpAdapterBundle\DataCollector;

use Ivory\HttpAdapter\Event\Events;
use Ivory\HttpAdapter\Event\MultiRequestCreatedEvent;
use Ivory\HttpAdapter\Event\MultiRequestErroredEvent;
use Ivory\HttpAdapter\Event\MultiRequestSentEvent;
use Ivory\HttpAdapter\Event\RequestCreatedEvent;
use Ivory\HttpAdapter\Event\RequestErroredEvent;
use Ivory\HttpAdapter\Event\RequestSentEvent;
use Ivory\HttpAdapter\Event\Subscriber\AbstractFormatterSubscriber;
use Ivory\HttpAdapter\HttpAdapterException;
use Ivory\HttpAdapter\HttpAdapterInterface;
use Ivory\HttpAdapter\Message\InternalRequestInterface;
use Ivory\HttpAdapter\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

/**
 * @author GeLo <geloen.eric@gmail.com>
 */
class IvoryHttpAdapterDataCollector extends AbstractFormatterSubscriber implements DataCollectorInterface, \Countable, \Serializable
{
    /**
     * @var array
     */
    private $datas = [
        'responses'  => [],
        'exceptions' => [],
    ];

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        // Nothing to do
    }

    /**
     * @return array
     */
    public function getResponses()
    {
        return $this->datas['responses'];
    }

    /**
     * @return array
     */
    public function getExceptions()
    {
        return $this->datas['exceptions'];
    }

    /**
     * @return float
     */
    public function getTime()
    {
        $time = 0;

        foreach (array_merge($this->datas['responses'], $this->datas['exceptions']) as $datas) {
            $time += $datas['request']['parameters']['time'];
        }

        return $time;
    }

    /**
     * @param RequestCreatedEvent $event
     */
    public function onRequestCreated(RequestCreatedEvent $event)
    {
        $event->setRequest($this->getTimer()->start($event->getRequest()));
    }

    /**
     * @param RequestSentEvent $event
     */
    public function onRequestSent(RequestSentEvent $event)
    {
        $event->setRequest($this->collectResponse(
            $event->getHttpAdapter(),
            $event->getRequest(),
            $event->getResponse()
        ));
    }

    /**
     * @param RequestErroredEvent $event
     */
    public function onRequestErrored(RequestErroredEvent $event)
    {
        $event->getException()->setRequest($this->collectException($event->getHttpAdapter(), $event->getException()));
    }

    /**
     * @param MultiRequestCreatedEvent $event
     */
    public function onMultiRequestCreated(MultiRequestCreatedEvent $event)
    {
        $requests = [];

        foreach ($event->getRequests() as $request) {
            $requests[] = $this->getTimer()->start($request);
        }

        $event->setRequests($requests);
    }

    /**
     * @param MultiRequestSentEvent $event
     */
    public function onMultiRequestSent(MultiRequestSentEvent $event)
    {
        $responses = [];

        foreach ($event->getResponses() as $response) {
            $responses[] = $response->withParameter(
                'request',
                $this->collectResponse($event->getHttpAdapter(), $response->getParameter('request'), $response)
            );
        }

        $event->setResponses($responses);
    }

    /**
     * @param MultiRequestErroredEvent $event
     */
    public function onMultiRequestErrored(MultiRequestErroredEvent $event)
    {
        foreach ($event->getExceptions() as $exception) {
            $exception->setRequest($this->collectException($event->getHttpAdapter(), $exception));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'ivory.http_adapter';
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->datas['responses']) + count($this->datas['exceptions']);
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize($this->datas);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        $this->datas = unserialize($serialized);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            Events::REQUEST_CREATED       => ['onRequestCreated', 100],
            Events::REQUEST_SENT          => ['onRequestSent', 100],
            Events::REQUEST_ERRORED       => ['onRequestErrored', 100],
            Events::MULTI_REQUEST_CREATED => ['onMultiRequestCreated', 100],
            Events::MULTI_REQUEST_SENT    => ['onMultiRequestSent', 100],
            Events::MULTI_REQUEST_ERRORED => ['onMultiRequestErrored', 100],
        ];
    }

    /**
     * @param HttpAdapterInterface     $httpAdapter
     * @param InternalRequestInterface $request
     * @param ResponseInterface        $response
     *
     * @return InternalRequestInterface
     */
    private function collectResponse(
        HttpAdapterInterface $httpAdapter,
        InternalRequestInterface $request,
        ResponseInterface $response
    ) {
        $request = $this->getTimer()->stop($request);

        $this->datas['responses'][] = [
            'adapter'  => $httpAdapter->getName(),
            'request'  => $this->getFormatter()->formatRequest($request),
            'response' => $this->getFormatter()->formatResponse($response),
        ];

        return $request;
    }

    /**
     * @param HttpAdapterInterface $httpAdapter
     * @param HttpAdapterException $exception
     *
     * @return InternalRequestInterface
     */
    private function collectException(HttpAdapterInterface $httpAdapter, HttpAdapterException $exception)
    {
        $request = $this->getTimer()->stop($exception->getRequest());

        $this->datas['exceptions'][] = [
            'adapter'   => $httpAdapter->getName(),
            'exception' => $this->getFormatter()->formatException($exception),
            'request'   => $this->getFormatter()->formatRequest($request),
            'response'  => $exception->hasResponse()
                ? $this->getFormatter()->formatResponse($exception->getResponse())
                : null,
        ];

        return $request;
    }
}
