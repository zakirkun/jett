<?php

namespace Zakirkun\Jett\Cache;

class TaggedCache
{
    protected array $tags;

    public function __construct(array $tags)
    {
        $this->tags = $tags;
    }

    public function set(string $key, $value, ?int $ttl = null): void
    {
        Cache::set($key, $value, $ttl, $this->tags);
    }

    public function get(string $key, $default = null)
    {
        return Cache::get($key, $default);
    }

    public function flush(): void
    {
        foreach ($this->tags as $tag) {
            if (isset(Cache::$tags[$tag])) {
                foreach (Cache::$tags[$tag] as $key) {
                    Cache::forget($key);
                }
                unset(Cache::$tags[$tag]);
            }
        }
    }
}
