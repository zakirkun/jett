<?php

namespace Zakirkun\Jett\Console\Commands;

use Zakirkun\Jett\Console\Command;
use Zakirkun\Jett\Cache\DistributedCache;

class CacheCommand extends Command
{
    protected string $signature = 'cache';
    protected string $description = 'Cache management commands';

    public function handle(array $args): void
    {
        if (empty($args)) {
            $this->showHelp();
            return;
        }

        switch ($args[0]) {
            case 'clear':
                $this->clear($args[1] ?? null);
                break;
            case 'list':
                $this->list();
                break;
            case 'get':
                $this->get($args[1] ?? null);
                break;
            case 'remove':
                $this->remove($args[1] ?? null);
                break;
            case 'stats':
                $this->stats();
                break;
            default:
                $this->error("Unknown command: {$args[0]}");
                $this->showHelp();
        }
    }

    protected function clear(?string $pattern = null): void
    {
        if ($pattern) {
            // Clear specific pattern
            $keys = DistributedCache::keys($pattern);
            foreach ($keys as $key) {
                DistributedCache::delete($key);
            }
            $this->info("Cleared cache keys matching: {$pattern}");
        } else {
            // Clear all cache
            DistributedCache::clear();
            $this->info('Cleared all cache');
        }
    }

    protected function list(): void
    {
        $keys = DistributedCache::keys('*');
        
        if (empty($keys)) {
            $this->info('No cached items found.');
            return;
        }

        $this->info('Cached Keys:');
        $this->info('------------');
        
        foreach ($keys as $key) {
            $ttl = DistributedCache::ttl($key);
            $size = strlen(serialize(DistributedCache::get($key)));
            
            $this->info(sprintf(
                '%-40s TTL: %s, Size: %s',
                $key,
                $ttl > 0 ? "{$ttl}s" : 'No expiry',
                $this->formatBytes($size)
            ));
        }
    }

    protected function get(?string $key): void
    {
        if (!$key) {
            $this->error('Key is required.');
            return;
        }

        $value = DistributedCache::get($key);
        
        if ($value === null) {
            $this->info("No value found for key: {$key}");
            return;
        }

        $this->info("Value for key '{$key}':");
        $this->info('------------------------');
        $this->info(print_r($value, true));
    }

    protected function remove(?string $key): void
    {
        if (!$key) {
            $this->error('Key is required.');
            return;
        }

        if (DistributedCache::delete($key)) {
            $this->info("Removed cache key: {$key}");
        } else {
            $this->error("Failed to remove cache key: {$key}");
        }
    }

    protected function stats(): void
    {
        $stats = DistributedCache::stats();

        $this->info('Cache Statistics:');
        $this->info('----------------');
        
        // Memory stats
        $this->info('Memory:');
        $this->info(sprintf(
            '  Used Memory: %s',
            $this->formatBytes($stats['used_memory'])
        ));
        $this->info(sprintf(
            '  Peak Memory: %s',
            $this->formatBytes($stats['used_memory_peak'])
        ));

        // Keys stats
        $this->info('Keys:');
        $this->info(sprintf('  Total Keys: %d', $stats['db0']['keys']));
        $this->info(sprintf('  Expires: %d', $stats['db0']['expires']));

        // Stats
        $this->info('Operations:');
        $this->info(sprintf('  Total Connections: %d', $stats['total_connections_received']));
        $this->info(sprintf('  Total Commands: %d', $stats['total_commands_processed']));
        $this->info(sprintf('  Operations/Second: %d', $stats['instantaneous_ops_per_sec']));

        // Keyspace stats
        $this->info('Keyspace:');
        $this->info(sprintf('  Hits: %d', $stats['keyspace_hits']));
        $this->info(sprintf('  Misses: %d', $stats['keyspace_misses']));
        $hitRate = $stats['keyspace_hits'] + $stats['keyspace_misses'] > 0
            ? ($stats['keyspace_hits'] / ($stats['keyspace_hits'] + $stats['keyspace_misses'])) * 100
            : 0;
        $this->info(sprintf('  Hit Rate: %.2f%%', $hitRate));
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    protected function showHelp(): void
    {
        $this->info('Cache Commands:');
        $this->info('--------------');
        $this->info('cache clear [pattern] : Clear all cache or by pattern');
        $this->info('cache list : List all cached keys');
        $this->info('cache get <key> : Get value for specific key');
        $this->info('cache remove <key> : Remove specific cache key');
        $this->info('cache stats : Show cache statistics');
    }
}
