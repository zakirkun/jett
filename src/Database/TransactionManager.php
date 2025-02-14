<?php

namespace Zakirkun\Jett\Database;

use Closure;
use PDO;
use RuntimeException;
use Throwable;

class TransactionManager
{
    protected static array $transactions = [];
    protected static array $savepoints = [];
    protected static array $deadlockRetries = [
        'attempts' => 3,
        'wait' => 100000 // microseconds
    ];

    public static function begin(): void
    {
        $connection = ConnectionPool::getConnection();
        $connectionId = spl_object_hash($connection);

        if (!isset(self::$transactions[$connectionId])) {
            self::$transactions[$connectionId] = 0;
        }

        if (self::$transactions[$connectionId] == 0) {
            $connection->beginTransaction();
        } else {
            $savepoint = 'SP' . self::$transactions[$connectionId];
            $connection->exec("SAVEPOINT {$savepoint}");
            self::$savepoints[$connectionId][] = $savepoint;
        }

        self::$transactions[$connectionId]++;
    }

    public static function commit(): void
    {
        $connection = ConnectionPool::getConnection();
        $connectionId = spl_object_hash($connection);

        if (!isset(self::$transactions[$connectionId])) {
            throw new RuntimeException('No active transaction');
        }

        self::$transactions[$connectionId]--;

        if (self::$transactions[$connectionId] == 0) {
            $connection->commit();
            unset(self::$transactions[$connectionId]);
        } elseif (self::$transactions[$connectionId] > 0) {
            $savepoint = array_pop(self::$savepoints[$connectionId]);
            $connection->exec("RELEASE SAVEPOINT {$savepoint}");
        }
    }

    public static function rollback(): void
    {
        $connection = ConnectionPool::getConnection();
        $connectionId = spl_object_hash($connection);

        if (!isset(self::$transactions[$connectionId])) {
            throw new RuntimeException('No active transaction');
        }

        if (self::$transactions[$connectionId] == 1) {
            $connection->rollBack();
            self::$transactions[$connectionId] = 0;
            unset(self::$transactions[$connectionId]);
        } elseif (self::$transactions[$connectionId] > 1) {
            $savepoint = array_pop(self::$savepoints[$connectionId]);
            $connection->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
            self::$transactions[$connectionId]--;
        }
    }

    public static function transaction(Closure $callback, int $attempts = null): mixed
    {
        $attempts = $attempts ?? self::$deadlockRetries['attempts'];

        for ($currentAttempt = 1; $currentAttempt <= $attempts; $currentAttempt++) {
            try {
                self::begin();

                $result = $callback();

                self::commit();

                return $result;
            } catch (Throwable $e) {
                self::rollback();

                // Check if it's a deadlock error
                if (self::isDeadlockError($e) && $currentAttempt < $attempts) {
                    usleep(self::$deadlockRetries['wait'] * $currentAttempt);
                    continue;
                }

                throw $e;
            }
        }
    }

    public static function setDeadlockRetries(int $attempts, int $waitMicroseconds): void
    {
        self::$deadlockRetries = [
            'attempts' => $attempts,
            'wait' => $waitMicroseconds
        ];
    }

    public static function setIsolationLevel(string $level): void
    {
        $connection = ConnectionPool::getConnection();
        
        $levels = [
            'READ UNCOMMITTED',
            'READ COMMITTED',
            'REPEATABLE READ',
            'SERIALIZABLE'
        ];

        if (!in_array($level, $levels)) {
            throw new RuntimeException('Invalid isolation level');
        }

        $connection->exec("SET TRANSACTION ISOLATION LEVEL {$level}");
    }

    protected static function isDeadlockError(Throwable $e): bool
    {
        $errorCodes = [
            1213, // Deadlock found when trying to get lock
            1205  // Lock wait timeout exceeded
        ];

        return in_array($e->getCode(), $errorCodes);
    }

    public static function getTransactionLevel(): int
    {
        $connection = ConnectionPool::getConnection();
        $connectionId = spl_object_hash($connection);

        return self::$transactions[$connectionId] ?? 0;
    }

    public static function isTransactionActive(): bool
    {
        return self::getTransactionLevel() > 0;
    }

    public static function getSavepoints(): array
    {
        $connection = ConnectionPool::getConnection();
        $connectionId = spl_object_hash($connection);

        return self::$savepoints[$connectionId] ?? [];
    }

    public static function monitorTransactions(): array
    {
        $connection = ConnectionPool::getConnection();
        $stmt = $connection->query("
            SELECT 
                trx_id,
                trx_state,
                trx_started,
                trx_requested_lock_id,
                trx_wait_started,
                trx_weight,
                trx_mysql_thread_id,
                trx_query
            FROM information_schema.INNODB_TRX
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
