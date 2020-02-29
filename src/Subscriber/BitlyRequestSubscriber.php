<?php
/*
 * Copyright (c) Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Dilbot\Subscriber;

use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tebru\Retrofit\Event\BeforeSendEvent;

/**
 * Class BitlyRequestSubscriber
 *
 * @author Nate Brunette <n@tebru.net>
 */
class BitlyRequestSubscriber implements EventSubscriberInterface
{
    /**
     * @var string
     */
    private $accessToken;

    /**
     * Constructor
     *
     * @param string $accessToken
     */
    public function __construct(string $accessToken)
    {
        $this->accessToken = $accessToken;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return [
            'retrofit.beforeSend' => 'beforeSend',
        ];
    }

    /**
     * Add query parameter before send
     *
     * @param BeforeSendEvent $event
     * @throws InvalidArgumentException
     */
    public function beforeSend(BeforeSendEvent $event)
    {
        $request = $event->getRequest();

        /** @var RequestInterface $request */
        $request = $request->withHeader('Authorization', 'Bearer '.$this->accessToken);

        $event->setRequest($request);
    }
}
