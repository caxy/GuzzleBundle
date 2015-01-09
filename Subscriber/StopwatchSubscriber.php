<?php

namespace Playbloom\Bundle\GuzzleBundle\Subscriber;

use GuzzleHttp\Event\EndEvent;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Event\SubscriberInterface;

class StopwatchSubscriber implements SubscriberInterface
{
    /**
     * @var \SplObjectStorage
     */
    private $storage;

    public function __construct(\SplObjectStorage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * {@inheritdocs}
     */
    public function getEvents()
    {
        return [
          'end' => ['onEnd', RequestEvents::EARLY],
        ];
    }

    public function onEnd(EndEvent $event)
    {
        $response = $event->getResponse();
        $data = array(
          'total'      => $event->getTransferInfo('total_time'),
          'connection' => $event->getTransferInfo('connect_time'),
        );
        $this->storage->attach($response, $data);
    }
}
