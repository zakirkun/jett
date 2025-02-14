<?php

namespace Zakirkun\Jett\Console;

class Application
{
    protected array $commands = [];
    protected array $defaultCommands = [
        \Zakirkun\Jett\Console\Commands\MakeModel::class,
        \Zakirkun\Jett\Console\Commands\DatabaseCommand::class,
        \Zakirkun\Jett\Console\Commands\SchemaCommand::class,
        \Zakirkun\Jett\Console\Commands\MigrationCommand::class,
        \Zakirkun\Jett\Console\Commands\SeederCommand::class,
        \Zakirkun\Jett\Console\Commands\CacheCommand::class,
    ];

    public function __construct()
    {
        $this->registerDefaultCommands();
    }

    protected function registerDefaultCommands(): void
    {
        foreach ($this->defaultCommands as $command) {
            $this->add(new $command());
        }
    }

    public function add(Command $command): void
    {
        $this->commands[$command->getName()] = $command;
    }

    public function run(array $argv = []): int
    {
        try {
            array_shift($argv); // Remove script name
            $commandName = $argv[0] ?? 'list';
            $args = array_slice($argv, 1);

            if ($commandName === 'list') {
                return $this->listCommands();
            }

            if (!isset($this->commands[$commandName])) {
                $this->error("Command not found: {$commandName}");
                return 1;
            }

            return $this->commands[$commandName]->execute($args);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            if (getenv('JETT_DEBUG')) {
                $this->error($e->getTraceAsString());
            }
            return 1;
        }
    }

    protected function listCommands(): int
    {
        $this->output("Jett ORM Command Line Tool\n");
        $this->output("Available commands:\n");

        $maxLength = 0;
        foreach ($this->commands as $name => $command) {
            $maxLength = max($maxLength, strlen($name));
        }

        foreach ($this->commands as $name => $command) {
            $padding = str_repeat(' ', $maxLength - strlen($name) + 2);
            $this->output("  {$name}{$padding}{$command->getDescription()}\n");
        }

        return 0;
    }

    protected function error(string $message): void
    {
        fwrite(STDERR, "\033[31m{$message}\033[0m\n");
    }

    protected function output(string $message): void
    {
        fwrite(STDOUT, $message);
    }
}
