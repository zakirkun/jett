<?php

namespace Zakirkun\Jett\Console\Commands;

use Zakirkun\Jett\Console\Command;
use Zakirkun\Jett\Database\ConnectionPool;
use Zakirkun\Jett\Schema\SchemaManager;

class MigrationCommand extends Command
{
    protected string $signature = 'migrate';
    protected string $description = 'Database migration commands';
    protected string $migrationPath = 'database/migrations';

    public function handle(array $args): void
    {
        if (empty($args)) {
            $this->migrate();
            return;
        }

        switch ($args[0]) {
            case 'make':
                $this->makeMigration($args[1] ?? null);
                break;
            case 'rollback':
                $this->rollback($args[1] ?? 1);
                break;
            case 'reset':
                $this->reset();
                break;
            case 'refresh':
                $this->refresh();
                break;
            case 'status':
                $this->status();
                break;
            default:
                $this->error("Unknown command: {$args[0]}");
                $this->showHelp();
        }
    }

    protected function migrate(): void
    {
        $this->ensureMigrationTableExists();
        $migrations = $this->getPendingMigrations();

        if (empty($migrations)) {
            $this->info('Nothing to migrate.');
            return;
        }

        foreach ($migrations as $migration) {
            $this->runMigration($migration);
        }

        $this->info('Migration completed successfully.');
    }

    protected function makeMigration(?string $name): void
    {
        if (!$name) {
            $this->error('Migration name is required.');
            return;
        }

        $timestamp = date('Y_m_d_His');
        $className = $this->getClassName($name);
        $filename = "{$timestamp}_{$name}.php";
        $path = $this->getMigrationPath($filename);

        $stub = $this->getMigrationStub();
        $stub = str_replace('{{className}}', $className, $stub);

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $stub);
        $this->info("Created Migration: {$filename}");
    }

    protected function rollback(int $steps = 1): void
    {
        $migrations = $this->getExecutedMigrations();
        $migrations = array_slice($migrations, -$steps);

        if (empty($migrations)) {
            $this->info('Nothing to rollback.');
            return;
        }

        foreach (array_reverse($migrations) as $migration) {
            $this->rollbackMigration($migration);
        }

        $this->info('Rollback completed successfully.');
    }

    protected function reset(): void
    {
        if (!$this->confirm('This will delete all tables. Are you sure?')) {
            return;
        }

        $schema = new SchemaManager();
        $tables = $schema->getTables();

        foreach ($tables as $table) {
            $schema->dropTable($table);
        }

        $this->info('Database reset completed.');
    }

    protected function refresh(): void
    {
        $this->reset();
        $this->migrate();
    }

    protected function status(): void
    {
        $migrations = $this->getAllMigrations();
        $executed = $this->getExecutedMigrations();

        $this->info('Migration Status:');
        $this->info('----------------');

        foreach ($migrations as $migration) {
            $status = in_array($migration, $executed) ? 'Executed' : 'Pending';
            $this->info(sprintf('%-50s %s', $migration, $status));
        }
    }

    protected function ensureMigrationTableExists(): void
    {
        $schema = new SchemaManager();
        
        if (!$schema->hasTable('migrations')) {
            $schema->createTable('migrations', [
                'id' => ['type' => 'INT', 'auto_increment' => true],
                'migration' => ['type' => 'VARCHAR', 'length' => 255],
                'batch' => ['type' => 'INT'],
                'executed_at' => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP']
            ], ['PRIMARY KEY (id)']);
        }
    }

    protected function getPendingMigrations(): array
    {
        $allMigrations = $this->getAllMigrations();
        $executedMigrations = $this->getExecutedMigrations();
        return array_diff($allMigrations, $executedMigrations);
    }

    protected function getAllMigrations(): array
    {
        $path = $this->getMigrationPath();
        if (!is_dir($path)) {
            return [];
        }

        $files = scandir($path);
        return array_filter($files, function ($file) {
            return pathinfo($file, PATHINFO_EXTENSION) === 'php';
        });
    }

    protected function getExecutedMigrations(): array
    {
        $connection = ConnectionPool::getConnection();
        $stmt = $connection->query('SELECT migration FROM migrations ORDER BY id ASC');
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    protected function runMigration(string $migration): void
    {
        require_once $this->getMigrationPath($migration);
        $class = $this->getClassName(basename($migration, '.php'));
        
        $instance = new $class();
        $instance->up();

        $connection = ConnectionPool::getConnection();
        $batch = $this->getNextBatchNumber();
        
        $stmt = $connection->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?)');
        $stmt->execute([$migration, $batch]);

        $this->info("Migrated: {$migration}");
    }

    protected function rollbackMigration(string $migration): void
    {
        require_once $this->getMigrationPath($migration);
        $class = $this->getClassName(basename($migration, '.php'));
        
        $instance = new $class();
        $instance->down();

        $connection = ConnectionPool::getConnection();
        $stmt = $connection->prepare('DELETE FROM migrations WHERE migration = ?');
        $stmt->execute([$migration]);

        $this->info("Rolled back: {$migration}");
    }

    protected function getNextBatchNumber(): int
    {
        $connection = ConnectionPool::getConnection();
        $stmt = $connection->query('SELECT MAX(batch) FROM migrations');
        return (int)$stmt->fetchColumn() + 1;
    }

    protected function getMigrationPath(string $filename = ''): string
    {
        return rtrim($this->migrationPath, '/') . '/' . $filename;
    }

    protected function getClassName(string $name): string
    {
        $name = implode('', array_map('ucfirst', explode('_', $name)));
        if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_(.+)$/', $name, $matches)) {
            $name = $matches[1];
        }
        return $name;
    }

    protected function getMigrationStub(): string
    {
        return <<<'EOF'
<?php

use Zakirkun\Jett\Schema\SchemaManager;

class {{className}}
{
    public function up(): void
    {
        $schema = new SchemaManager();
        
        // Define your migration here
    }

    public function down(): void
    {
        $schema = new SchemaManager();
        
        // Define how to reverse the migration here
    }
}
EOF;
    }

    protected function showHelp(): void
    {
        $this->info('Migration Commands:');
        $this->info('------------------');
        $this->info('migrate : Run all pending migrations');
        $this->info('migrate make <name> : Create a new migration');
        $this->info('migrate rollback [steps] : Rollback the last migration or specified number of migrations');
        $this->info('migrate reset : Reset the entire database');
        $this->info('migrate refresh : Reset and re-run all migrations');
        $this->info('migrate status : Show migration status');
    }
}
