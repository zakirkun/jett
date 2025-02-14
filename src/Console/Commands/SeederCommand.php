<?php

namespace Zakirkun\Jett\Console\Commands;

use Zakirkun\Jett\Console\Command;

class SeederCommand extends Command
{
    protected string $signature = 'db:seed';
    protected string $description = 'Database seeder commands';
    protected string $seederPath = 'database/seeders';

    public function handle(array $args): void
    {
        if (empty($args)) {
            $this->seed();
            return;
        }

        switch ($args[0]) {
            case 'make':
                $this->makeSeeder($args[1] ?? null);
                break;
            case 'run':
                $this->runSeeder($args[1] ?? null);
                break;
            default:
                $this->error("Unknown command: {$args[0]}");
                $this->showHelp();
        }
    }

    protected function seed(): void
    {
        $seeders = $this->getSeeders();

        if (empty($seeders)) {
            $this->info('No seeders found.');
            return;
        }

        foreach ($seeders as $seeder) {
            $this->runSeeder($seeder);
        }

        $this->info('Database seeding completed successfully.');
    }

    protected function makeSeeder(?string $name): void
    {
        if (!$name) {
            $this->error('Seeder name is required.');
            return;
        }

        if (!str_ends_with($name, 'Seeder')) {
            $name .= 'Seeder';
        }

        $path = $this->getSeederPath($name . '.php');
        
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $stub = $this->getSeederStub();
        $stub = str_replace('{{className}}', $name, $stub);

        file_put_contents($path, $stub);
        $this->info("Created Seeder: {$name}");
    }

    protected function runSeeder(?string $name): void
    {
        if (!$name) {
            $this->error('Seeder name is required.');
            return;
        }

        if (!str_ends_with($name, 'Seeder')) {
            $name .= 'Seeder';
        }

        $path = $this->getSeederPath($name . '.php');
        
        if (!file_exists($path)) {
            $this->error("Seeder not found: {$name}");
            return;
        }

        require_once $path;
        $instance = new $name();
        $instance->run();

        $this->info("Seeded: {$name}");
    }

    protected function getSeeders(): array
    {
        $path = $this->getSeederPath();
        if (!is_dir($path)) {
            return [];
        }

        $files = scandir($path);
        return array_filter($files, function ($file) {
            return pathinfo($file, PATHINFO_EXTENSION) === 'php';
        });
    }

    protected function getSeederPath(string $filename = ''): string
    {
        return rtrim($this->seederPath, '/') . '/' . $filename;
    }

    protected function getSeederStub(): string
    {
        return <<<'EOF'
<?php

use Zakirkun\Jett\Database\Seeder;

class {{className}} extends Seeder
{
    public function run(): void
    {
        // Define your seeder logic here
        // Example:
        // $this->table('users')->insert([
        //     'name' => 'John Doe',
        //     'email' => 'john@example.com',
        //     'password' => password_hash('password', PASSWORD_DEFAULT)
        // ]);
    }
}
EOF;
    }

    protected function showHelp(): void
    {
        $this->info('Seeder Commands:');
        $this->info('---------------');
        $this->info('db:seed : Run all seeders');
        $this->info('db:seed make <name> : Create a new seeder');
        $this->info('db:seed run <name> : Run specific seeder');
    }
}
