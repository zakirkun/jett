<?php

namespace Zakirkun\Jett\Console;

abstract class Command
{
    protected string $signature;
    protected string $description;
    protected array $arguments = [];
    protected array $options = [];

    abstract public function handle(): int;

    public function getSignature(): string
    {
        return $this->signature;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    protected function argument(string $key)
    {
        return $this->arguments[$key] ?? null;
    }

    protected function option(string $key)
    {
        return $this->options[$key] ?? null;
    }

    public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }
}
