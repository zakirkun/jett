<?php

namespace Zakirkun\Jett\Database;

use PDO;
use RuntimeException;

class ConnectionPool
{
    protected static array $connections = [];
    protected static array $configs = [];
    protected static int $maxConnections = 10;
    protected static int $minConnections = 2;
    protected static int $idleTimeout = 300; // 5 minutes
    protected static array $lastUsed = [];

    public static function setConfig(array $config): void
    {
        self::$configs = $config;
        self::initializePool();
    }

    public static function setPoolLimits(int $min, int $max): void
    {
        self::$minConnections = $min;
        self::$maxConnections = $max;
    }

    protected static function initializePool(): void
    {
        for ($i = 0; $i < self::$minConnections; $i++) {
            self::createConnection();
        }
    }

    public static function getConnection(): PDO
    {
        self::cleanIdleConnections();

        foreach (self::$connections as $key => $connection) {
            if (!isset(self::$lastUsed[$key]) || self::$lastUsed[$key] === null) {
                self::$lastUsed[$key] = time();
                return $connection;
            }
        }

        if (count(self::$connections) < self::$maxConnections) {
            return self::createConnection();
        }

        throw new RuntimeException('No available connections in the pool');
    }

    protected static function createConnection(): PDO
    {
        $dsn = sprintf(
            "%s:host=%s;port=%s;dbname=%s;charset=utf8mb4",
            self::$configs['driver'],
            self::$configs['host'],
            self::$configs['port'],
            self::$configs['database']
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true
        ];

        $connection = new PDO(
            $dsn,
            self::$configs['username'],
            self::$configs['password'],
            $options
        );

        $key = spl_object_hash($connection);
        self::$connections[$key] = $connection;
        self::$lastUsed[$key] = time();

        return $connection;
    }

    protected static function cleanIdleConnections(): void
    {
        $now = time();
        foreach (self::$lastUsed as $key => $lastUsed) {
            if ($lastUsed && ($now - $lastUsed) > self::$idleTimeout) {
                if (count(self::$connections) > self::$minConnections) {
                    unset(self::$connections[$key], self::$lastUsed[$key]);
                }
            }
        }
    }

    public static function releaseConnection(PDO $connection): void
    {
        $key = spl_object_hash($connection);
        self::$lastUsed[$key] = null;
    }

    public static function closeAll(): void
    {
        foreach (self::$connections as $key => $connection) {
            unset(self::$connections[$key], self::$lastUsed[$key]);
        }
    }
}
