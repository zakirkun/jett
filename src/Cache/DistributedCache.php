<?php

namespace Zakirkun\Jett\Cache;

use Redis;
use RuntimeException;

class DistributedCache
{
    protected static ?Redis $redis = null;
    protected static array $config = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'timeout' => 0.0,
        'retry_interval' => 100,
        'read_timeout' => 0.0,
        'prefix' => 'jett:'
    ];

    public static function configure(array $config): void
    {
        self::$config = array_merge(self::$config, $config);
    }

    protected static function connect(): void
    {
        if (self::$redis !== null) {
            return;
        }

        self::$redis = new Redis();
        
        $connected = self::$redis->connect(
            self::$config['host'],
            self::$config['port'],
            self::$config['timeout'],
            null,
            self::$config['retry_interval'],
            self::$config['read_timeout']
        );

        if (!$connected) {
            throw new RuntimeException('Failed to connect to Redis server');
        }

        if (isset(self::$config['password'])) {
            self::$redis->auth(self::$config['password']);
        }

        self::$redis->setOption(Redis::OPT_PREFIX, self::$config['prefix']);
    }

    public static function set(string $key, $value, ?int $ttl = null): bool
    {
        self::connect();
        
        if ($ttl !== null) {
            return self::$redis->setex($key, $ttl, serialize($value));
        }
        
        return self::$redis->set($key, serialize($value));
    }

    public static function get(string $key, $default = null)
    {
        self::connect();
        
        $value = self::$redis->get($key);
        return $value !== false ? unserialize($value) : $default;
    }

    public static function remember(string $key, int $ttl, callable $callback)
    {
        if ($value = self::get($key)) {
            return $value;
        }

        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }

    public static function tags(array $tags): TaggedCache
    {
        return new TaggedCache(self::$redis, $tags);
    }

    public static function increment(string $key, int $value = 1): int
    {
        self::connect();
        return self::$redis->incrBy($key, $value);
    }

    public static function decrement(string $key, int $value = 1): int
    {
        self::connect();
        return self::$redis->decrBy($key, $value);
    }

    public static function delete(string $key): bool
    {
        self::connect();
        return (bool) self::$redis->del($key);
    }

    public static function clear(): bool
    {
        self::connect();
        return self::$redis->flushDB();
    }

    public static function has(string $key): bool
    {
        self::connect();
        return (bool) self::$redis->exists($key);
    }

    public static function many(array $keys): array
    {
        self::connect();
        
        $values = self::$redis->mget($keys);
        return array_map(function ($value) {
            return $value !== false ? unserialize($value) : null;
        }, array_combine($keys, $values));
    }

    public static function putMany(array $values, ?int $ttl = null): bool
    {
        self::connect();
        
        if ($ttl !== null) {
            $success = true;
            foreach ($values as $key => $value) {
                $success = $success && self::set($key, $value, $ttl);
            }
            return $success;
        }

        $serialized = [];
        foreach ($values as $key => $value) {
            $serialized[$key] = serialize($value);
        }

        return self::$redis->mset($serialized);
    }

    public static function lock(string $key, int $ttl = 60): bool
    {
        self::connect();
        return (bool) self::$redis->set(
            "lock:{$key}",
            1,
            ['NX', 'EX' => $ttl]
        );
    }

    public static function unlock(string $key): bool
    {
        self::connect();
        return (bool) self::$redis->del("lock:{$key}");
    }

    public static function stats(): array
    {
        self::connect();
        return self::$redis->info();
    }
}
