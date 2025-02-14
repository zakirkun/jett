<?php

namespace Zakirkun\Jett\Schema;

class Column
{
    protected string $type;
    protected string $name;
    protected ?int $length;
    protected bool $nullable = false;
    protected bool $unsigned = false;
    protected bool $primary = false;
    protected bool $autoIncrement = false;
    protected $default = null;

    public function __construct(string $type, string $name, ?int $length = null)
    {
        $this->type = $type;
        $this->name = $name;
        $this->length = $length;
    }

    public function nullable(): self
    {
        $this->nullable = true;
        return $this;
    }

    public function unsigned(): self
    {
        $this->unsigned = true;
        return $this;
    }

    public function primaryKey(): self
    {
        $this->primary = true;
        return $this;
    }

    public function autoIncrement(): self
    {
        $this->autoIncrement = true;
        return $this;
    }

    public function default($value): self
    {
        $this->default = $value;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isPrimary(): bool
    {
        return $this->primary;
    }

    public function toSql(): string
    {
        $parts = [$this->name];

        if ($this->length !== null) {
            $parts[] = "{$this->type}({$this->length})";
        } else {
            $parts[] = $this->type;
        }

        if ($this->unsigned) {
            $parts[] = "UNSIGNED";
        }

        if (!$this->nullable) {
            $parts[] = "NOT NULL";
        }

        if ($this->autoIncrement) {
            $parts[] = "AUTO_INCREMENT";
        }

        if ($this->default !== null) {
            $parts[] = "DEFAULT " . (is_string($this->default) ? "'{$this->default}'" : $this->default);
        }

        return implode(" ", $parts);
    }
}
