<?php

namespace Zakirkun\Jett\Console\Commands;

use Zakirkun\Jett\Console\Command;
use Zakirkun\Jett\Schema\SchemaManager;

class SchemaCommand extends Command
{
    protected string $signature = 'schema';
    protected string $description = 'Schema management commands';

    public function handle(array $args): void
    {
        if (empty($args)) {
            $this->showHelp();
            return;
        }

        switch ($args[0]) {
            case 'show':
                $this->show($args[1] ?? null);
                break;
            case 'compare':
                $this->compare($args[1] ?? null, $args[2] ?? null);
                break;
            case 'export':
                $this->export($args[1] ?? null);
                break;
            case 'import':
                $this->import($args[1] ?? null);
                break;
            case 'analyze':
                $this->analyze($args[1] ?? null);
                break;
            default:
                $this->error("Unknown command: {$args[0]}");
                $this->showHelp();
        }
    }

    protected function show(?string $table = null): void
    {
        $schema = new SchemaManager();

        if ($table) {
            if (!$schema->hasTable($table)) {
                $this->error("Table not found: {$table}");
                return;
            }

            $this->showTableSchema($schema, $table);
        } else {
            $tables = $this->getTables();
            foreach ($tables as $table) {
                $this->showTableSchema($schema, $table);
                $this->info('');
            }
        }
    }

    protected function showTableSchema(SchemaManager $schema, string $table): void
    {
        $tableSchema = $schema->getTableSchema($table);

        $this->info("Table: {$table}");
        $this->info('Columns:');
        foreach ($tableSchema['columns'] as $column) {
            $this->info("  {$column['Field']} ({$column['Type']})");
            if ($column['Null'] === 'NO') $this->info('    NOT NULL');
            if ($column['Default'] !== null) $this->info("    DEFAULT '{$column['Default']}'");
            if ($column['Extra']) $this->info("    {$column['Extra']}");
        }

        if (!empty($tableSchema['indexes'])) {
            $this->info('Indexes:');
            foreach ($tableSchema['indexes'] as $index) {
                $this->info("  {$index['Key_name']} ({$index['Column_name']})");
                if ($index['Non_unique'] == 0) $this->info('    UNIQUE');
            }
        }

        if (!empty($tableSchema['foreign_keys'])) {
            $this->info('Foreign Keys:');
            foreach ($tableSchema['foreign_keys'] as $fk) {
                $this->info("  {$fk['CONSTRAINT_NAME']}:");
                $this->info("    {$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}");
            }
        }
    }

    protected function compare(?string $source, ?string $target): void
    {
        if (!$source || !$target) {
            $this->error('Both source and target tables are required');
            return;
        }

        $schema = new SchemaManager();
        
        if (!$schema->hasTable($source)) {
            $this->error("Source table not found: {$source}");
            return;
        }

        if (!$schema->hasTable($target)) {
            $this->error("Target table not found: {$target}");
            return;
        }

        $sourceSchema = $schema->getTableSchema($source);
        $targetSchema = $schema->getTableSchema($target);
        
        $differences = $schema->compareSchema($target, $sourceSchema);

        if (empty($differences)) {
            $this->info('No differences found');
            return;
        }

        $this->info('Schema Differences:');
        foreach ($differences as $type => $items) {
            $this->info(ucfirst(str_replace('_', ' ', $type)) . ':');
            foreach ($items as $item) {
                $this->info("  - {$item}");
            }
        }
    }

    protected function export(?string $filename = null): void
    {
        $schema = new SchemaManager();
        $tables = $this->getTables();
        $export = [];

        foreach ($tables as $table) {
            $export[$table] = $schema->getTableSchema($table);
        }

        $filename = $filename ?? 'schema_' . date('Y-m-d_His') . '.json';
        file_put_contents($filename, json_encode($export, JSON_PRETTY_PRINT));
        
        $this->info("Schema exported to: {$filename}");
    }

    protected function import(string $filename): void
    {
        if (!file_exists($filename)) {
            $this->error("File not found: {$filename}");
            return;
        }

        $schema = new SchemaManager();
        $import = json_decode(file_put_contents($filename), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid schema file');
            return;
        }

        foreach ($import as $table => $tableSchema) {
            if ($schema->hasTable($table)) {
                if (!$this->confirm("Table {$table} already exists. Update it?")) {
                    continue;
                }
            }

            // Implementation of table creation/modification would go here
            $this->info("Processing table: {$table}");
        }
    }

    protected function analyze(?string $table = null): void
    {
        $schema = new SchemaManager();
        $tables = $table ? [$table] : $this->getTables();

        foreach ($tables as $table) {
            $this->info("Analyzing table: {$table}");
            
            // Get table statistics
            $stats = $schema->analyze($table);
            
            // Show analysis results
            foreach ($stats as $stat) {
                $this->info("  {$stat['Table']}: {$stat['Msg_type']} - {$stat['Msg_text']}");
            }
        }
    }

    protected function getTables(): array
    {
        $connection = ConnectionPool::getConnection();
        return $connection->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
    }

    protected function showHelp(): void
    {
        $this->info('Schema Management Commands:');
        $this->info('-------------------------');
        $this->info('show [table] : Show schema for all tables or specific table');
        $this->info('compare <source> <target> : Compare schemas of two tables');
        $this->info('export [filename] : Export schema to JSON file');
        $this->info('import <filename> : Import schema from JSON file');
        $this->info('analyze [table] : Analyze table structure and indexes');
    }

    protected function confirm(string $message): bool
    {
        $this->output($message . ' [y/N] ');
        $answer = trim(fgets(STDIN));
        return strtolower($answer) === 'y';
    }
}
