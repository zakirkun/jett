<?php

namespace Zakirkun\Jett\Query;

use PDO;
use Zakirkun\Jett\Database\Connection;

class Builder
{
    protected string $table;
    protected array $wheres = [];
    protected array $selects = ['*'];
    protected array $orderBy = [];
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected array $bindings = [];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function select(array $columns = ['*']): self
    {
        $this->selects = $columns;
        return $this;
    }

    public function where(string $column, string $operator, $value): self
    {
        $this->wheres[] = compact('column', 'operator', 'value');
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = compact('column', 'direction');
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function get(): array
    {
        $query = $this->buildQuery();
        $stmt = Connection::getInstance()->prepare($query);
        $stmt->execute($this->bindings);
        return $stmt->fetchAll();
    }

    public function first()
    {
        $query = $this->buildQuery();
        $stmt = Connection::getInstance()->prepare($query);
        $stmt->execute($this->bindings);
        return $stmt->fetch();
    }

    protected function buildQuery(): string
    {
        $query = "SELECT " . implode(', ', $this->selects) . " FROM {$this->table}";

        if (!empty($this->wheres)) {
            $query .= " WHERE ";
            $conditions = [];
            foreach ($this->wheres as $where) {
                $conditions[] = "{$where['column']} {$where['operator']} ?";
                $this->bindings[] = $where['value'];
            }
            $query .= implode(' AND ', $conditions);
        }

        if (!empty($this->orderBy)) {
            $orders = [];
            foreach ($this->orderBy as $order) {
                $orders[] = "{$order['column']} {$order['direction']}";
            }
            $query .= " ORDER BY " . implode(', ', $orders);
        }

        if ($this->limit !== null) {
            $query .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $query .= " OFFSET {$this->offset}";
        }

        return $query;
    }
}
