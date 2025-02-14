<?php

namespace Zakirkun\Jett\Console\Commands;

use Zakirkun\Jett\Console\Command;
use Zakirkun\Jett\Database\ConnectionPool;
use Zakirkun\Jett\Schema\SchemaManager;

class DatabaseCommand extends Command
{
    protected string $signature = 'db';
    protected string $description = 'Database management commands';

    public function handle(array $args): void
    {
        if (empty($args)) {
            $this->showHelp();
            return;
        }

        switch ($args[0]) {
            case 'backup':
                $this->backup($args[1] ?? null);
                break;
            case 'restore':
                $this->restore($args[1] ?? null);
                break;
            case 'optimize':
                $this->optimize();
                break;
            case 'status':
                $this->status();
                break;
            case 'clear':
                $this->clear();
                break;
            default:
                $this->error("Unknown command: {$args[0]}");
                $this->showHelp();
        }
    }

    protected function backup(?string $filename = null): void
    {
        $config = ConnectionPool::getConfig();
        $filename = $filename ?? 'backup_' . date('Y-m-d_His') . '.sql';

        $command = sprintf(
            'mysqldump -h %s -P %s -u %s %s %s > %s',
            escapeshellarg($config['host']),
            escapeshellarg($config['port']),
            escapeshellarg($config['username']),
            $config['password'] ? '-p' . escapeshellarg($config['password']) : '',
            escapeshellarg($config['database']),
            escapeshellarg($filename)
        );

        exec($command, $output, $returnVar);

        if ($returnVar === 0) {
            $this->info("Database backed up successfully to: {$filename}");
        } else {
            $this->error('Backup failed');
        }
    }

    protected function restore(string $filename): void
    {
        if (!file_exists($filename)) {
            $this->error("Backup file not found: {$filename}");
            return;
        }

        $config = ConnectionPool::getConfig();
        
        $command = sprintf(
            'mysql -h %s -P %s -u %s %s %s < %s',
            escapeshellarg($config['host']),
            escapeshellarg($config['port']),
            escapeshellarg($config['username']),
            $config['password'] ? '-p' . escapeshellarg($config['password']) : '',
            escapeshellarg($config['database']),
            escapeshellarg($filename)
        );

        exec($command, $output, $returnVar);

        if ($returnVar === 0) {
            $this->info('Database restored successfully');
        } else {
            $this->error('Restore failed');
        }
    }

    protected function optimize(): void
    {
        $schema = new SchemaManager();
        $tables = $this->getTables();

        foreach ($tables as $table) {
            $this->info("Optimizing table: {$table}");
            $schema->optimize($table);
            $schema->analyze($table);
        }

        $this->info('Database optimization completed');
    }

    protected function status(): void
    {
        $schema = new SchemaManager();
        $stats = $schema->getDatabaseSize();

        $this->info('Database Status:');
        $this->info('----------------');
        $this->info("Database: {$stats['database']}");
        $this->info("Total Size: " . $this->formatBytes($stats['size']));
        $this->info("Data Size: " . $this->formatBytes($stats['data_size']));
        $this->info("Index Size: " . $this->formatBytes($stats['index_size']));
        $this->info("Tables: {$stats['tables']}");
        $this->info("Total Rows: {$stats['rows']}");
    }

    protected function clear(): void
    {
        if (!$this->confirm('This will truncate all tables. Are you sure?')) {
            return;
        }

        $schema = new SchemaManager();
        $tables = $this->getTables();

        foreach ($tables as $table) {
            $this->info("Truncating table: {$table}");
            $schema->truncate($table);
        }

        $this->info('Database cleared successfully');
    }

    protected function getTables(): array
    {
        $connection = ConnectionPool::getConnection();
        $tables = $connection->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        return $tables;
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
        $this->info('Database Management Commands:');
        $this->info('----------------------------');
        $this->info('backup [filename] : Backup database to file');
        $this->info('restore <filename> : Restore database from backup');
        $this->info('optimize : Optimize database tables');
        $this->info('status : Show database status and statistics');
        $this->info('clear : Truncate all tables');
    }

    protected function confirm(string $message): bool
    {
        $this->output($message . ' [y/N] ');
        $answer = trim(fgets(STDIN));
        return strtolower($answer) === 'y';
    }
}
