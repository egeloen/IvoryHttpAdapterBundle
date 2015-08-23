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
use Ivory\HttpAdapter\Event\ExceptionEvent;
use Ivory\HttpAdapter\Event\MultiExceptionEvent;
use Ivory\HttpAdapter\Event\MultiPostSendEvent;
use Ivory\HttpAdapter\Event\MultiPreSendEvent;
use Ivory\HttpAdapter\Event\PostSendEvent;
use Ivory\HttpAdapter\Event\PreSendEvent;
use Ivory\HttpAdapter\Event\Subscriber\AbstractFormatterSubscriber;
use Ivory\HttpAdapter\HttpAdapterException;
use Ivory\HttpAdapter\HttpAdapterInterface;
use Ivory\HttpAdapter\Message\InternalRequestInterface;
use Ivory\HttpAdapter\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

/**
 * Ivory http adapter data collector.
 *
 * @author GeLo <geloen.eric@gmail.com>
 */
class IvoryHttpAdapterDataCollector
    extends AbstractFormatterSubscriber
    implements DataCollectorInterface, \Countable, \Serializable
{
    /** @var array */
    private $datas = array(
        'responses'  => array(),
        'exceptions' => array(),
    );

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        // Nothing to do
    }

    /**
     * Gets the responses.
     *
     * @return array The responses.
     */
    public function getResponses()
    {
        return $this->datas['responses'];
    }

    /**
     * Gets the exceptions.
     *
     * @return array The exceptions.
     */
    public function getExceptions()
    {
        return $this->datas['exceptions'];
    }

    /**
     * Gets the time.
     *
     * @return float The time.
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
     * On pre send event.
     *
     * @param \Ivory\HttpAdapter\Event\PreSendEvent $event The pre send event.
     */
    public function onPreSend(PreSendEvent $event)
    {
        $event->setRequest($this->getTimer()->start($event->getRequest()));
    }

    /**
     * On post send event.
     *
     * @param \Ivory\HttpAdapter\Event\PostSendEvent $event The post send event.
     */
    public function onPostSend(PostSendEvent $event)
    {
        $event->setRequest($this->collectResponse(
            $event->getHttpAdapter(),
            $event->getRequest(),
            $event->getResponse()
        ));
    }

    /**
     * On exception event.
     *
     * @param \Ivory\HttpAdapter\Event\ExceptionEvent $event The exception event.
     */
    public function onException(ExceptionEvent $event)
    {
        $event->getException()->setRequest($this->collectException($event->getHttpAdapter(), $event->getException()));
    }

    /**
     * On multi pre send event.
     *
     * @param \Ivory\HttpAdapter\Event\MultiPreSendEvent $event The multi pre send event.
     */
    public function onMultiPreSend(MultiPreSendEvent $event)
    {
        $requests = [];

        foreach ($event->getRequests() as $request) {
            $requests[] = $this->getTimer()->start($request);
        }

        $event->setRequests($requests);
    }

    /**
     * On multi post send event.
     *
     * @param \Ivory\HttpAdapter\Event\MultiPostSendEvent $event The mutli post send event.
     */
    public function onMultiPostSend(MultiPostSendEvent $event)
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
     * On multi exception event.
     *
     * @param \Ivory\HttpAdapter\Event\MultiExceptionEvent $event The multi exception event.
     */
    public function onMultiException(MultiExceptionEvent $event)
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
        return array(
            Events::PRE_SEND        => array('onPreSend', 100),
            Events::POST_SEND       => array('onPostSend', 100),
            Events::EXCEPTION       => array('onException', 100),
            Events::MULTI_PRE_SEND  => array('onMultiPreSend', 100),
            Events::MULTI_POST_SEND => array('onMultiPostSend', 100),
            Events::MULTI_EXCEPTION => array('onMultiException', 100),
        );
    }

    /**
     * Collects a response.
     *
     * @param \Ivory\HttpAdapter\HttpAdapterInterface             $httpAdapter The http adapter.
     * @param \Ivory\HttpAdapter\Message\InternalRequestInterface $request     The request.
     * @param \Ivory\HttpAdapter\Message\ResponseInterface        $response    The response.
     *
     * @return \Ivory\HttpAdapter\Message\InternalRequestInterface The collected request.
     */
    private function collectResponse(
        HttpAdapterInterface $httpAdapter,
        InternalRequestInterface $request,
        ResponseInterface $response
    ) {
        $request = $this->getTimer()->stop($request);

        $this->datas['responses'][] = array(
            'adapter'  => $httpAdapter->getName(),
            'request'  => $this->getFormatter()->formatRequest($request),
            'response' => $this->getFormatter()->formatResponse($response),
        );

        return $request;
    }

    /**
     * Collects an exception.
     *
     * @param \Ivory\HttpAdapter\HttpAdapterInterface $httpAdapter The http adapter.
     * @param \Ivory\HttpAdapter\HttpAdapterException $exception   The exception.
     *
     * @return \Ivory\HttpAdapter\Message\InternalRequestInterface The collected request.
     */
    private function collectException(HttpAdapterInterface $httpAdapter, HttpAdapterException $exception)
    {
        $request = $this->getTimer()->stop($exception->getRequest());

        $this->datas['exceptions'][] = array(
            'adapter'   => $httpAdapter->getName(),
            'exception' => $this->getFormatter()->formatException($exception),
            'request'   => $this->getFormatter()->formatRequest($request),
            'response'  => $exception->hasResponse()
                ? $this->getFormatter()->formatResponse($exception->getResponse())
                : null,
        );

        return $request;
    }
}
