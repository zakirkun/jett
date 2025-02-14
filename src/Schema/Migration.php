<?php

namespace Zakirkun\Jett\Schema;

use Zakirkun\Jett\Database\Connection;

abstract class Migration
{
    abstract public function up(): void;
    abstract public function down(): void;

    protected function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $sql = $blueprint->toSql();
        Connection::getInstance()->exec($sql);
    }

    protected function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        // Implement ALTER TABLE logic here
    }

    protected function drop(string $table): void
    {
        $sql = "DROP TABLE IF EXISTS {$table}";
        Connection::getInstance()->exec($sql);
    }
}
