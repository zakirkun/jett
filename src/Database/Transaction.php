<?php

namespace Zakirkun\Jett\Database;

trait Transaction
{
    public static function beginTransaction(): bool
    {
        return Connection::beginTransaction();
    }

    public static function commit(): bool
    {
        return Connection::commit();
    }

    public static function rollBack(): bool
    {
        return Connection::rollBack();
    }

    public static function transaction(callable $callback)
    {
        return Connection::transaction($callback);
    }
}
