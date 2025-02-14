<?php

namespace Zakirkun\Jett\Query;

use PDO;
use Zakirkun\Jett\Database\Connection;

class Builder
{
    protected string $table;
    protected array $wheres = [];
    protected array $joins = [];
    protected array $selects = ['*'];
    protected array $orderBy = [];
    protected array $groupBy = [];
    protected array $having = [];
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

    public function whereIn(string $column, array $values): self
    {
        $placeholders = array_fill(0, count($values), '?');
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'sql' => "{$column} IN (" . implode(', ', $placeholders) . ")"
        ];
        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = compact('table', 'first', 'operator', 'second', 'type');
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    public function groupBy(string|array $columns): self
    {
        $this->groupBy = array_merge($this->groupBy, (array) $columns);
        return $this;
    }

    public function having(string $column, string $operator, $value): self
    {
        $this->having[] = compact('column', 'operator', 'value');
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

    public function count(string $column = '*'): int
    {
        $query = $this->aggregate('COUNT', $column);
        return (int) $query;
    }

    public function sum(string $column): float
    {
        return (float) $this->aggregate('SUM', $column);
    }

    public function avg(string $column): float
    {
        return (float) $this->aggregate('AVG', $column);
    }

    public function min(string $column)
    {
        return $this->aggregate('MIN', $column);
    }

    public function max(string $column)
    {
        return $this->aggregate('MAX', $column);
    }

    protected function aggregate(string $function, string $column)
    {
        $previousSelects = $this->selects;
        $this->selects = ["{$function}({$column}) as aggregate"];
        $result = $this->get()[0] ?? null;
        $this->selects = $previousSelects;
        return $result['aggregate'] ?? null;
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

        // Add joins
        foreach ($this->joins as $join) {
            $query .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }

        if (!empty($this->wheres)) {
            $query .= " WHERE ";
            $conditions = [];
            foreach ($this->wheres as $where) {
                if (isset($where['type']) && $where['type'] === 'in') {
                    $conditions[] = $where['sql'];
                } else {
                    $conditions[] = "{$where['column']} {$where['operator']} ?";
                    $this->bindings[] = $where['value'];
                }
            }
            $query .= implode(' AND ', $conditions);
        }

        if (!empty($this->groupBy)) {
            $query .= " GROUP BY " . implode(', ', $this->groupBy);
        }

        if (!empty($this->having)) {
            $query .= " HAVING ";
            $conditions = [];
            foreach ($this->having as $having) {
                $conditions[] = "{$having['column']} {$having['operator']} ?";
                $this->bindings[] = $having['value'];
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

    public function raw(string $query, array $bindings = []): array
    {
        $stmt = Connection::getInstance()->prepare($query);
        $stmt->execute($bindings);
        return $stmt->fetchAll();
    }

    public function insert(array $values): bool
    {
        if (empty($values)) {
            return false;
        }

        // Handle multiple inserts
        if (!isset($values[0])) {
            $values = [$values];
        }

        $columns = array_keys($values[0]);
        $bindings = [];
        $placeholders = [];

        foreach ($values as $row) {
            $rowPlaceholders = [];
            foreach ($columns as $column) {
                $bindings[] = $row[$column] ?? null;
                $rowPlaceholders[] = '?';
            }
            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
        }

        $query = sprintf(
            "INSERT INTO %s (%s) VALUES %s",
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = Connection::getInstance()->prepare($query);
        return $stmt->execute($bindings);
    }

    public function bulkUpdate(array $values, string $index = 'id'): bool
    {
        if (empty($values)) {
            return false;
        }

        $cases = [];
        $bindings = [];
        $ids = [];

        $columns = array_keys(reset($values));
        $columns = array_filter($columns, fn($col) => $col !== $index);

        foreach ($columns as $column) {
            $cases[$column] = "$column = CASE";
            foreach ($values as $row) {
                $id = $row[$index];
                $ids[] = $id;
                $cases[$column] .= " WHEN $index = ? THEN ?";
                $bindings[] = $id;
                $bindings[] = $row[$column];
            }
            $cases[$column] .= " ELSE $column END";
        }

        $query = sprintf(
            "UPDATE %s SET %s WHERE %s IN (%s)",
            $this->table,
            implode(', ', $cases),
            $index,
            implode(', ', array_fill(0, count(array_unique($ids)), '?'))
        );

        $bindings = array_merge($bindings, array_unique($ids));
        $stmt = Connection::getInstance()->prepare($query);
        return $stmt->execute($bindings);
    }

    public function bulkDelete(array $ids, string $column = 'id'): bool
    {
        if (empty($ids)) {
            return false;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $query = "DELETE FROM {$this->table} WHERE {$column} IN ({$placeholders})";
        
        $stmt = Connection::getInstance()->prepare($query);
        return $stmt->execute($ids);
    }

    public function upsert(array $values, array $uniqueBy, array $update = null): bool
    {
        if (empty($values)) {
            return false;
        }

        // Handle single insert
        if (!isset($values[0])) {
            $values = [$values];
        }

        $columns = array_keys($values[0]);
        $bindings = [];
        $placeholders = [];

        foreach ($values as $row) {
            $rowPlaceholders = [];
            foreach ($columns as $column) {
                $bindings[] = $row[$column] ?? null;
                $rowPlaceholders[] = '?';
            }
            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
        }

        $update = $update ?? array_diff($columns, $uniqueBy);
        $updateStr = implode(', ', array_map(fn($col) => "$col = VALUES($col)", $update));

        $query = sprintf(
            "INSERT INTO %s (%s) VALUES %s ON DUPLICATE KEY UPDATE %s",
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders),
            $updateStr
        );

        $stmt = Connection::getInstance()->prepare($query);
        return $stmt->execute($bindings);
    }
}
