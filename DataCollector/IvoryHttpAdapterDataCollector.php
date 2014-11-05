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
use Ivory\HttpAdapter\Event\PostSendEvent;
use Ivory\HttpAdapter\Event\Subscriber\AbstractDebuggerSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

/**
 * Ivory http adapter data collector.
 *
 * @author GeLo <geloen.eric@gmail.com>
 */
class IvoryHttpAdapterDataCollector
    extends AbstractDebuggerSubscriber
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
            $time += $datas['time'];
        }

        return $time;
    }

    /**
     * On post send event.
     *
     * @param \Ivory\HttpAdapter\Event\PostSendEvent $event The post send event.
     */
    public function onPostSend(PostSendEvent $event)
    {
        $this->datas['responses'][] = parent::onPostSend($event);
    }

    /**
     * On exception event.
     *
     * @param \Ivory\HttpAdapter\Event\ExceptionEvent $event The exception event.
     */
    public function onException(ExceptionEvent $event)
    {
        $this->datas['exceptions'][] = parent::onException($event);
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
            Events::PRE_SEND  => array('onPreSend', 100),
            Events::POST_SEND => array('onPostSend', 100),
            Events::EXCEPTION => array('onException', 100),
        );
    }
}
