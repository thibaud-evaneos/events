<?php

namespace Aztech\Events\Plugins\Wamp;

use Aztech\Events\Core\Event;
use Aztech\Events\Serializer;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Ratchet\ConnectionInterface;
use Ratchet\Wamp\WampServerInterface;
use Aztech\Events\Core\Subscriber\CallbackSubscriber;
use Aztech\Events\Core\Dispatcher;

class Publisher implements \Aztech\Events\Publisher, WampServerInterface, LoggerAwareInterface
{

    private $logger;

    private $serializer;

    private $internalDispatcher;

    private $transport;

    public function __construct(Serializer $serializer)
    {
        $this->serializer = $serializer;
        $this->logger = new NullLogger();
        $this->transport = new Transport();
        $this->internalDispatcher = new Dispatcher();
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function publish(Event $event)
    {
        return $this->transport->write($event, $this->serializer->serialize($event));
    }

    public function on($event, $callback)
    {
        $this->internalDispatcher->addListener($event, new CallbackSubscriber($callback));
    }

    public function onSubscribe(ConnectionInterface $conn, $topic)
    {
        $this->transport->onSubscribe($conn, $topic);

        $event = new Event('subscribe', array(
            'connection' => $conn,
            'topic' => $topic
        ));

        $this->internalDispatcher->dispatch($event);
    }

    public function onUnSubscribe(ConnectionInterface $conn, $topic)
    {
        $this->transport->onUnSubscribe($conn, $topic);

        $event = new Event('unsubscribe', array(
            'connection' => $conn,
            'topic' => $topic
        ));

        $this->internalDispatcher->dispatch($event);
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->transport->onOpen($conn);

        $event = new SimpleEvent('open', array(
            'connection' => $conn
        ));

        $this->internalDispatcher->dispatch($event);
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->transport->onClose($conn);

        $event = new Event('close', array(
            'connection' => $conn
        ));

        $this->internalDispatcher->dispatch($event);
    }

    public function onCall(ConnectionInterface $conn, $id, $topic, array $params)
    {
        $this->transport->onCall($conn, $id, $topic, $params);

        $event = new Event('call', array(
            'connection' => $conn,
            'id' => $id,
            'topic' => $topic,
            'params' => $params
        ));

        $this->internalDispatcher->dispatch($event);
    }

    public function onPublish(ConnectionInterface $conn, $topic, $event, array $exclude, array $eligible)
    {
        $this->transport->onPublish($conn, $topic, $event, $exclude, $eligible);

        $event = new Event('publish', array(
            'connection' => $conn,
            'id' => $id,
            'topic' => $topic,
            'params' => $params
        ));

        $this->internalDispatcher->dispatch($event);
    }

    public function onError(ConnectionInterface $conn,\Exception $e)
    {
        $event = new Event('error', array(
            'connection' => $conn,
            'error' => $ex
        ));

        $this->internalDispatcher->dispatch($event);
        $this->logger->error($e->getMessage() . PHP_EOL . $e->getTraceAsString());
    }
}