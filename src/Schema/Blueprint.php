<?php

namespace Zakirkun\Jett\Schema;

class Blueprint
{
    protected string $table;
    protected array $columns = [];
    protected array $indexes = [];
    protected array $foreignKeys = [];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function id(): self
    {
        return $this->integer('id')->primaryKey()->autoIncrement();
    }

    public function string(string $name, int $length = 255): Column
    {
        return $this->addColumn('VARCHAR', $name, $length);
    }

    public function integer(string $name): Column
    {
        return $this->addColumn('INTEGER', $name);
    }

    public function bigInteger(string $name): Column
    {
        return $this->addColumn('BIGINT', $name);
    }

    public function text(string $name): Column
    {
        return $this->addColumn('TEXT', $name);
    }

    public function boolean(string $name): Column
    {
        return $this->addColumn('BOOLEAN', $name);
    }

    public function datetime(string $name): Column
    {
        return $this->addColumn('DATETIME', $name);
    }

    public function timestamp(string $name): Column
    {
        return $this->addColumn('TIMESTAMP', $name);
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    public function softDeletes(): void
    {
        $this->timestamp('deleted_at')->nullable();
    }

    protected function addColumn(string $type, string $name, ?int $length = null): Column
    {
        $column = new Column($type, $name, $length);
        $this->columns[] = $column;
        return $column;
    }

    public function foreignKey(string $column): ForeignKey
    {
        $foreign = new ForeignKey($column);
        $this->foreignKeys[] = $foreign;
        return $foreign;
    }

    public function index(string|array $columns, ?string $name = null): self
    {
        $columns = (array) $columns;
        $name = $name ?? $this->table . '_' . implode('_', $columns) . '_index';
        $this->indexes[] = compact('name', 'columns');
        return $this;
    }

    public function unique(string|array $columns, ?string $name = null): self
    {
        $columns = (array) $columns;
        $name = $name ?? $this->table . '_' . implode('_', $columns) . '_unique';
        $this->indexes[] = [
            'name' => $name,
            'columns' => $columns,
            'unique' => true
        ];
        return $this;
    }

    public function toSql(): string
    {
        $parts = [];
        
        // Columns
        foreach ($this->columns as $column) {
            $parts[] = "  " . $column->toSql();
        }

        // Primary Key
        foreach ($this->columns as $column) {
            if ($column->isPrimary()) {
                $parts[] = "  PRIMARY KEY ({$column->getName()})";
            }
        }

        // Indexes
        foreach ($this->indexes as $index) {
            $columns = implode(', ', $index['columns']);
            $type = isset($index['unique']) ? 'UNIQUE' : '';
            $parts[] = "  {$type} INDEX {$index['name']} ({$columns})";
        }

        // Foreign Keys
        foreach ($this->foreignKeys as $foreign) {
            $parts[] = "  " . $foreign->toSql();
        }

        return sprintf(
            "CREATE TABLE %s (\n%s\n);",
            $this->table,
            implode(",\n", $parts)
        );
    }
}
