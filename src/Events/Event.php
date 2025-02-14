<?php

namespace Zakirkun\Jett\Events;

abstract class Event
{
    protected static array $listeners = [];
    protected static array $wildcards = [];

    public static function listen(string $event, callable $callback): void
    {
        if (str_contains($event, '*')) {
            static::$wildcards[$event][] = $callback;
        } else {
            static::$listeners[$event][] = $callback;
        }
    }

    public static function dispatch(string $event, array $payload = []): void
    {
        foreach (static::$listeners[$event] ?? [] as $listener) {
            $listener($payload);
        }

        foreach (static::$wildcards as $key => $listeners) {
            if (str_is($key, $event)) {
                foreach ($listeners as $listener) {
                    $listener($payload);
                }
            }
        }
    }

    public static function forget(string $event): void
    {
        unset(static::$listeners[$event]);
    }

    public static function forgetAll(): void
    {
        static::$listeners = [];
        static::$wildcards = [];
    }
}
