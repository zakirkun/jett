<?php

namespace Zakirkun\Jett\Cache;

class Cache
{
    protected static array $store = [];
    protected static array $tags = [];
    protected static ?string $defaultDriver = null;
    protected static array $drivers = [];

    public static function set(string $key, $value, ?int $ttl = null, array $tags = []): void
    {
        $expiration = $ttl ? time() + $ttl : null;
        
        self::$store[$key] = [
            'value' => $value,
            'expiration' => $expiration
        ];

        if (!empty($tags)) {
            foreach ($tags as $tag) {
                self::$tags[$tag][] = $key;
            }
        }
    }

    public static function get(string $key, $default = null)
    {
        if (!isset(self::$store[$key])) {
            return $default;
        }

        $item = self::$store[$key];
        
        if ($item['expiration'] !== null && $item['expiration'] < time()) {
            self::forget($key);
            return $default;
        }

        return $item['value'];
    }

    public static function has(string $key): bool
    {
        return isset(self::$store[$key]) && 
               (self::$store[$key]['expiration'] === null || 
                self::$store[$key]['expiration'] >= time());
    }

    public static function forget(string $key): void
    {
        unset(self::$store[$key]);
    }

    public static function flush(): void
    {
        self::$store = [];
        self::$tags = [];
    }

    public static function tags(array $tags): TaggedCache
    {
        return new TaggedCache($tags);
    }

    public static function remember(string $key, int $ttl, callable $callback)
    {
        if (self::has($key)) {
            return self::get($key);
        }

        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }

    public static function rememberForever(string $key, callable $callback)
    {
        return self::remember($key, null, $callback);
    }

    public static function pull(string $key, $default = null)
    {
        $value = self::get($key, $default);
        self::forget($key);
        return $value;
    }

    public static function forever(string $key, $value): void
    {
        self::set($key, $value);
    }

    public static function increment(string $key, int $value = 1): int
    {
        $current = (int) self::get($key, 0);
        $new = $current + $value;
        self::set($key, $new);
        return $new;
    }

    public static function decrement(string $key, int $value = 1): int
    {
        return self::increment($key, -$value);
    }
}
