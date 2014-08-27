<?php

namespace Aztech\Events\Bus;

use Aztech\Events\Bus\Processor;
use Aztech\Events\Callback;
use Aztech\Events\Subscriber;

class GenericConsumer implements \Aztech\Events\Bus\Consumer
{

    private $processor;

    private $dispatcher;

    public function __construct(Processor $processor,\Aztech\Events\Dispatcher $dispatcher)
    {
        $this->processor = $processor;
        $this->dispatcher = $dispatcher;
    }

    public function on($filter, $subscriber)
    {
        if (is_callable($subscriber) && ! ($subscriber instanceof Subscriber)) {
            $subscriber = new CallbackSubscriber($subscriber);
        }
        elseif (! ($subscriber instanceof Subscriber)) {
            throw new \InvalidArgumentException('Subscriber must be a callable or a Subscriber instance.');
        }

        $this->dispatcher->addListener($filter, $subscriber);
    }

    public function onProcessorEvent($filter, $subscriber)
    {
        if (is_callable($subscriber) && ! ($subscriber instanceof Subscriber)) {
            $subscriber = new CallbackSubscriber($subscriber);
        }
        elseif (! ($subscriber instanceof Subscriber)) {
            throw new \InvalidArgumentException('Subscriber must be a callable or a Subscriber instance.');
        }

        $this->processor->on($filter, $subscriber);
    }

    public function consumeAll()
    {
        while (true) {
            $this->consumeNext();
        }
    }

    public function consumeNext()
    {
        $this->processor->processNext($this->dispatcher);
    }
}