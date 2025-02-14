<?php

namespace Zakirkun\Jett\Schema;

class ForeignKey
{
    protected string $column;
    protected string $references = 'id';
    protected string $on;
    protected string $onDelete = 'CASCADE';
    protected string $onUpdate = 'CASCADE';

    public function __construct(string $column)
    {
        $this->column = $column;
    }

    public function references(string $column): self
    {
        $this->references = $column;
        return $this;
    }

    public function on(string $table): self
    {
        $this->on = $table;
        return $this;
    }

    public function onDelete(string $action): self
    {
        $this->onDelete = $action;
        return $this;
    }

    public function onUpdate(string $action): self
    {
        $this->onUpdate = $action;
        return $this;
    }

    public function toSql(): string
    {
        return sprintf(
            "FOREIGN KEY (%s) REFERENCES %s(%s) ON DELETE %s ON UPDATE %s",
            $this->column,
            $this->on,
            $this->references,
            $this->onDelete,
            $this->onUpdate
        );
    }
}
