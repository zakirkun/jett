<?php

namespace Zakirkun\Jett\Events;

use Closure;
use RuntimeException;

class EventManager
{
    protected static array $listeners = [];
    protected static array $wildcards = [];
    protected static array $cached = [];
    protected static array $queuedEvents = [];
    protected static bool $isQueueing = false;

    public static function listen(string $event, $listener, int $priority = 0): void
    {
        if (strpos($event, '*') !== false) {
            self::$wildcards[$event][$priority][] = $listener;
        } else {
            self::$listeners[$event][$priority][] = $listener;
            unset(self::$cached[$event]);
        }
    }

    public static function subscribe(string $subscriber): void
    {
        $instance = new $subscriber();

        if (!method_exists($instance, 'subscribe')) {
            throw new RuntimeException("Event subscriber must have 'subscribe' method");
        }

        $instance->subscribe();
    }

    public static function dispatch($event, array $payload = [], bool $halt = false)
    {
        if (self::$isQueueing) {
            return self::queue($event, $payload);
        }

        $responses = [];

        foreach (self::getListeners($event) as $listener) {
            $response = self::callListener($listener, $payload);

            if ($halt && !is_null($response)) {
                return $response;
            }

            if ($response === false) {
                break;
            }

            $responses[] = $response;
        }

        return $halt ? null : $responses;
    }

    protected static function getListeners(string $event): array
    {
        if (isset(self::$cached[$event])) {
            return self::$cached[$event];
        }

        $listeners = self::$listeners[$event] ?? [];

        foreach (self::$wildcards as $pattern => $wildcardListeners) {
            if (str_is($pattern, $event)) {
                $listeners = array_merge_recursive($listeners, $wildcardListeners);
            }
        }

        if (!empty($listeners)) {
            ksort($listeners);
            $listeners = array_merge(...$listeners);
        }

        return self::$cached[$event] = $listeners;
    }

    protected static function callListener($listener, array $payload)
    {
        if ($listener instanceof Closure) {
            return $listener(...$payload);
        }

        if (is_string($listener)) {
            if (strpos($listener, '@') === false) {
                return $listener::handle(...$payload);
            }

            [$class, $method] = explode('@', $listener);
            return (new $class)->$method(...$payload);
        }

        return $listener(...$payload);
    }

    public static function queue($event, array $payload = []): void
    {
        self::$queuedEvents[] = ['event' => $event, 'payload' => $payload];
    }

    public static function flush(): array
    {
        $responses = [];
        self::$isQueueing = true;

        foreach (self::$queuedEvents as $queued) {
            $responses[] = self::dispatch($queued['event'], $queued['payload']);
        }

        self::$queuedEvents = [];
        self::$isQueueing = false;

        return $responses;
    }

    public static function forget(string $event): void
    {
        unset(
            self::$listeners[$event],
            self::$cached[$event]
        );
    }

    public static function forgetAll(): void
    {
        self::$listeners = [];
        self::$wildcards = [];
        self::$cached = [];
    }

    public static function hasListeners(string $event): bool
    {
        return !empty(self::getListeners($event));
    }

    public static function until($event, array $payload = [])
    {
        return self::dispatch($event, $payload, true);
    }

    protected static function str_is(string $pattern, string $value): bool
    {
        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace('\*', '.*', $pattern);
        return (bool) preg_match('#^'.$pattern.'\z#u', $value);
    }
}
