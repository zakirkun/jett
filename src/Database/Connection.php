<?php

namespace Zakirkun\Jett\Database;

use PDO;
use PDOException;

class Connection
{
    private static ?PDO $instance = null;
    private static array $config = [];

    public static function setConfig(array $config): void
    {
        self::$config = $config;
    }

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    "%s:host=%s;port=%s;dbname=%s",
                    self::$config['driver'] ?? 'mysql',
                    self::$config['host'] ?? 'localhost',
                    self::$config['port'] ?? '3306',
                    self::$config['database'] ?? ''
                );

                self::$instance = new PDO(
                    $dsn,
                    self::$config['username'] ?? 'root',
                    self::$config['password'] ?? '',
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch (PDOException $e) {
                throw new PDOException("Connection failed: " . $e->getMessage());
            }
        }

        return self::$instance;
    }
}
