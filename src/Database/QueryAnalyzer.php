<?php

namespace Zakirkun\Jett\Database;

class QueryAnalyzer
{
    protected static array $queries = [];
    protected static array $slowQueries = [];
    protected static float $slowThreshold = 1.0; // seconds
    protected static bool $enabled = false;

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public static function setSlowThreshold(float $seconds): void
    {
        self::$slowThreshold = $seconds;
    }

    public static function startQuery(string $sql, array $bindings = []): array
    {
        if (!self::$enabled) {
            return ['start' => 0, 'id' => null];
        }

        $id = uniqid('query_', true);
        $start = microtime(true);

        self::$queries[$id] = [
            'sql' => $sql,
            'bindings' => $bindings,
            'start' => $start,
            'end' => null,
            'duration' => null,
            'memory_start' => memory_get_usage(),
            'memory_end' => null,
            'memory_peak' => null,
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
        ];

        return ['start' => $start, 'id' => $id];
    }

    public static function endQuery(string $id, $result = null): void
    {
        if (!self::$enabled || !isset(self::$queries[$id])) {
            return;
        }

        $end = microtime(true);
        $query = &self::$queries[$id];
        
        $query['end'] = $end;
        $query['duration'] = $end - $query['start'];
        $query['memory_end'] = memory_get_usage();
        $query['memory_peak'] = memory_get_peak_usage();
        $query['result_count'] = is_array($result) ? count($result) : null;

        if ($query['duration'] >= self::$slowThreshold) {
            self::$slowQueries[$id] = $query;
        }
    }

    public static function getQueries(): array
    {
        return self::$queries;
    }

    public static function getSlowQueries(): array
    {
        return self::$slowQueries;
    }

    public static function getQueryStats(): array
    {
        $totalQueries = count(self::$queries);
        $totalDuration = 0;
        $totalMemory = 0;
        $slowQueries = count(self::$slowQueries);

        foreach (self::$queries as $query) {
            if ($query['duration'] !== null) {
                $totalDuration += $query['duration'];
            }
            if ($query['memory_end'] !== null && $query['memory_start'] !== null) {
                $totalMemory += ($query['memory_end'] - $query['memory_start']);
            }
        }

        return [
            'total_queries' => $totalQueries,
            'total_duration' => $totalDuration,
            'avg_duration' => $totalQueries > 0 ? $totalDuration / $totalQueries : 0,
            'total_memory' => $totalMemory,
            'slow_queries' => $slowQueries,
            'peak_memory' => memory_get_peak_usage(),
        ];
    }

    public static function explainQuery(string $sql, array $bindings = []): array
    {
        $connection = ConnectionPool::getConnection();
        $stmt = $connection->prepare("EXPLAIN " . $sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll();
    }

    public static function reset(): void
    {
        self::$queries = [];
        self::$slowQueries = [];
    }

    public static function getQueryPlan(string $sql, array $bindings = []): array
    {
        $connection = ConnectionPool::getConnection();
        
        // Get basic EXPLAIN
        $explain = self::explainQuery($sql, $bindings);
        
        // Get visual execution plan if available
        $visualPlan = [];
        if ($connection->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $stmt = $connection->prepare("EXPLAIN FORMAT=JSON " . $sql);
            $stmt->execute($bindings);
            $visualPlan = json_decode($stmt->fetchColumn(), true);
        }

        return [
            'basic_plan' => $explain,
            'visual_plan' => $visualPlan,
            'suggestions' => self::analyzePlan($explain)
        ];
    }

    protected static function analyzePlan(array $explain): array
    {
        $suggestions = [];

        foreach ($explain as $step) {
            // Check for full table scans
            if (isset($step['type']) && $step['type'] === 'ALL') {
                $suggestions[] = "Consider adding an index to avoid full table scan on table '{$step['table']}'";
            }

            // Check for missing indexes
            if (isset($step['possible_keys']) && $step['possible_keys'] === null && isset($step['key']) && $step['key'] === null) {
                $suggestions[] = "No indexes are being used for table '{$step['table']}'";
            }

            // Check for low key cardinality
            if (isset($step['rows']) && isset($step['filtered']) && $step['filtered'] < 20) {
                $suggestions[] = "Low selectivity on table '{$step['table']}'. Consider reviewing indexes or query conditions";
            }
        }

        return $suggestions;
    }
}
