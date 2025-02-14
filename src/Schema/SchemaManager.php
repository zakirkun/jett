<?php

namespace Zakirkun\Jett\Schema;

use PDO;
use Zakirkun\Jett\Database\ConnectionPool;

class SchemaManager
{
    protected PDO $connection;
    protected array $cachedSchema = [];

    public function __construct()
    {
        $this->connection = ConnectionPool::getConnection();
    }

    public function hasTable(string $table): bool
    {
        $sql = "SHOW TABLES LIKE ?";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$table]);
        return (bool) $stmt->fetch();
    }

    public function getTableSchema(string $table): array
    {
        if (isset($this->cachedSchema[$table])) {
            return $this->cachedSchema[$table];
        }

        $columns = $this->getColumns($table);
        $indexes = $this->getIndexes($table);
        $foreignKeys = $this->getForeignKeys($table);

        $this->cachedSchema[$table] = [
            'columns' => $columns,
            'indexes' => $indexes,
            'foreign_keys' => $foreignKeys
        ];

        return $this->cachedSchema[$table];
    }

    public function getColumns(string $table): array
    {
        $sql = "SHOW FULL COLUMNS FROM {$table}";
        $stmt = $this->connection->query($sql);
        return $stmt->fetchAll();
    }

    public function getIndexes(string $table): array
    {
        $sql = "SHOW INDEXES FROM {$table}";
        $stmt = $this->connection->query($sql);
        return $stmt->fetchAll();
    }

    public function getForeignKeys(string $table): array
    {
        $sql = "SELECT 
                    CONSTRAINT_NAME,
                    COLUMN_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND REFERENCED_TABLE_NAME IS NOT NULL";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$table]);
        return $stmt->fetchAll();
    }

    public function createTable(string $table, array $columns, array $indexes = [], array $foreignKeys = []): bool
    {
        $columnDefinitions = [];
        foreach ($columns as $name => $definition) {
            $columnDefinitions[] = "{$name} {$definition}";
        }

        $indexDefinitions = [];
        foreach ($indexes as $name => $definition) {
            $indexDefinitions[] = $definition;
        }

        $foreignKeyDefinitions = [];
        foreach ($foreignKeys as $name => $definition) {
            $foreignKeyDefinitions[] = "CONSTRAINT {$name} FOREIGN KEY {$definition}";
        }

        $sql = sprintf(
            "CREATE TABLE %s (\n%s%s%s\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            $table,
            implode(",\n", $columnDefinitions),
            $indexDefinitions ? ",\n" . implode(",\n", $indexDefinitions) : '',
            $foreignKeyDefinitions ? ",\n" . implode(",\n", $foreignKeyDefinitions) : ''
        );

        return (bool) $this->connection->exec($sql);
    }

    public function dropTable(string $table): bool
    {
        $sql = "DROP TABLE IF EXISTS {$table}";
        return (bool) $this->connection->exec($sql);
    }

    public function addColumn(string $table, string $column, string $definition): bool
    {
        $sql = "ALTER TABLE {$table} ADD COLUMN {$column} {$definition}";
        return (bool) $this->connection->exec($sql);
    }

    public function modifyColumn(string $table, string $column, string $definition): bool
    {
        $sql = "ALTER TABLE {$table} MODIFY COLUMN {$column} {$definition}";
        return (bool) $this->connection->exec($sql);
    }

    public function dropColumn(string $table, string $column): bool
    {
        $sql = "ALTER TABLE {$table} DROP COLUMN {$column}";
        return (bool) $this->connection->exec($sql);
    }

    public function addIndex(string $table, string $name, string $definition): bool
    {
        $sql = "ALTER TABLE {$table} ADD {$definition}";
        return (bool) $this->connection->exec($sql);
    }

    public function dropIndex(string $table, string $name): bool
    {
        $sql = "ALTER TABLE {$table} DROP INDEX {$name}";
        return (bool) $this->connection->exec($sql);
    }

    public function addForeignKey(string $table, string $name, string $definition): bool
    {
        $sql = "ALTER TABLE {$table} ADD CONSTRAINT {$name} FOREIGN KEY {$definition}";
        return (bool) $this->connection->exec($sql);
    }

    public function dropForeignKey(string $table, string $name): bool
    {
        $sql = "ALTER TABLE {$table} DROP FOREIGN KEY {$name}";
        return (bool) $this->connection->exec($sql);
    }

    public function compareSchema(string $table, array $desiredSchema): array
    {
        $currentSchema = $this->getTableSchema($table);
        $differences = [];

        // Compare columns
        foreach ($desiredSchema['columns'] as $name => $definition) {
            if (!isset($currentSchema['columns'][$name])) {
                $differences['missing_columns'][] = $name;
            } elseif ($this->compareColumnDefinitions($currentSchema['columns'][$name], $definition)) {
                $differences['modified_columns'][] = $name;
            }
        }

        foreach ($currentSchema['columns'] as $name => $definition) {
            if (!isset($desiredSchema['columns'][$name])) {
                $differences['extra_columns'][] = $name;
            }
        }

        // Compare indexes
        foreach ($desiredSchema['indexes'] as $name => $definition) {
            if (!isset($currentSchema['indexes'][$name])) {
                $differences['missing_indexes'][] = $name;
            } elseif ($currentSchema['indexes'][$name] !== $definition) {
                $differences['modified_indexes'][] = $name;
            }
        }

        foreach ($currentSchema['indexes'] as $name => $definition) {
            if (!isset($desiredSchema['indexes'][$name])) {
                $differences['extra_indexes'][] = $name;
            }
        }

        // Compare foreign keys
        foreach ($desiredSchema['foreign_keys'] as $name => $definition) {
            if (!isset($currentSchema['foreign_keys'][$name])) {
                $differences['missing_foreign_keys'][] = $name;
            } elseif ($currentSchema['foreign_keys'][$name] !== $definition) {
                $differences['modified_foreign_keys'][] = $name;
            }
        }

        foreach ($currentSchema['foreign_keys'] as $name => $definition) {
            if (!isset($desiredSchema['foreign_keys'][$name])) {
                $differences['extra_foreign_keys'][] = $name;
            }
        }

        return $differences;
    }

    protected function compareColumnDefinitions(array $current, array $desired): bool
    {
        $relevantKeys = ['Type', 'Null', 'Default', 'Extra'];
        foreach ($relevantKeys as $key) {
            if (($current[$key] ?? null) !== ($desired[$key] ?? null)) {
                return true;
            }
        }
        return false;
    }

    public function optimize(string $table): bool
    {
        $sql = "OPTIMIZE TABLE {$table}";
        return (bool) $this->connection->exec($sql);
    }

    public function analyze(string $table): bool
    {
        $sql = "ANALYZE TABLE {$table}";
        return (bool) $this->connection->exec($sql);
    }

    public function truncate(string $table): bool
    {
        $sql = "TRUNCATE TABLE {$table}";
        return (bool) $this->connection->exec($sql);
    }

    public function getDatabaseSize(): array
    {
        $sql = "SELECT 
                    table_schema as 'database',
                    SUM(data_length + index_length) as 'size',
                    SUM(data_length) as 'data_size',
                    SUM(index_length) as 'index_size',
                    COUNT(*) as 'tables',
                    SUM(table_rows) as 'rows'
                FROM information_schema.TABLES
                WHERE table_schema = DATABASE()
                GROUP BY table_schema";

        $stmt = $this->connection->query($sql);
        return $stmt->fetch();
    }
}
