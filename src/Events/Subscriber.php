<?php

namespace Zakirkun\Jett\Events;

abstract class Subscriber
{
    /**
     * Register the listeners for the subscriber.
     * 
     * @return void
     */
    abstract public function subscribe(): void;

    /**
     * Register an event listener with the dispatcher.
     *
     * @param string $event
     * @param string|callable $listener
     * @param int $priority
     * @return void
     */
    protected function listen(string $event, $listener, int $priority = 0): void
    {
        EventManager::listen($event, $listener, $priority);
    }
}
