<?php

namespace Evaneos\Events\Processors\RabbitMQ;

use Evaneos\Events\Event;
use Evaneos\Events\EventSerializer;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Evaneos\Events\EventSubscriber;
use Evaneos\Events\EventDispatcher;
use Evaneos\Events\EventProcessor;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Evaneos\Events\Processors\AbstractProcessor;
use Rhumsaa\Uuid\Uuid;

class RabbitMQEventProcessor extends AbstractProcessor
{

    /**
     *
     * @var \PhpAmqpLib\Channel\AMQPChannel
     */
    private $channel;

    /**
     *
     * @var \Evaneos\Events\EventSerializer
     */
    private $serializer;

    /**
     *
     * @var string
     */
    private $queue;


    /**
     *
     * @param \PhpAmqpLib\Channel\AMQPChannel $channel
     * @param string $queue
     * @param \Evaneos\Events\EventSerializer $serializer
     */
    public function __construct(AMQPChannel $channel, $queue, EventSerializer $serializer)
    {
        parent::__construct();

        $this->channel = $channel;
        $this->queue = $queue;
        $this->serializer = $serializer;
    }

    public function processNext(EventDispatcher $dispatcher)
    {
        $self = $this;
        $serializer = $this->serializer;
        $channel = $this->channel;
        $logger = $this->logger;

        $callback = $this->buildHandleMessageCallback($dispatcher);

        $this->channel->basic_consume($this->queue, '', false, false, false, false, $callback);
        $this->channel->wait();
    }

    private function buildHandleMessageCallback($dispatcher)
    {
        $self = $this;

        return function ($message) use($self, $dispatcher)
        {
            $self->handleMessage($message, $dispatcher);
        };
    }

    public function handleMessage(AMQPMessage $message, $dispatcher)
    {
        try {
            if ($message->has('correlation_id')) {
                $correlationId = $message->get('correlation_id');
            }
            else {
                $key = $message->has('routing_key') ? $message->get('routing_key') : Uuid::uuid4();
                $correlationId = 'local-' . Uuid::uuid5(Uuid::uuid4(), $key);
            }

            $this->logger->debug('[ "' . $correlationId . '" ] Handling message payload', array('payload' => $message->body));

            if ($message->body == 'QUIT') {
                return $this->handleQuitMessage($correlationId, $message);
            }

            $event = $this->serializer->deserialize($message->body);

            if (! $event) {
                $this->handleInvalidMessage($correlationId, $message);
            }
            else {
                $this->handleValidMessage($correlationId, $message, $event, $dispatcher);
            }


        }
        catch (\Exception $ex) {
            $this->onError($event, $ex);
        }

        $this->onProcessed($event);
    }

    public function handleQuitMessage($correlationId, $message)
    {
        $this->logger->debug('[ "' . $correlationId . '" ] Received shutdown message.');

        $this->onShutdown();

        $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
    }

    public function handleInvalidMessage($correlationId, $message)
    {
        $this->logger->warning('[ "' . $correlationId . '" ] Received invalid payload, ignoring message.', array('payload' => $message->body));
        $this->logger->warning('[ "' . $correlationId . '" ] Acknowledging invalid message to avoid redelivery.');

        return $this->doMessageAck($correlationId, $message);
    }

    public function handleValidMessage($correlationId, $message, Event $event, $dispatcher)
    {
        $this->onProcessing($event);

        $this->logger->info('[ "' . $correlationId . '" ] Dispatching event : ' . $event->getCategory());

        $dispatcher->dispatch($event);

        $this->doMessageAck($correlationId, $message);
    }

    public function doMessageAck($correlationId, $message)
    {
        $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);

        $this->logger->info('[ "' . $correlationId . '" ] Acknowledged.');
    }
}
