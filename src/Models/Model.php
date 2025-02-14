<?php

namespace Zakirkun\Jett\Models;

use Zakirkun\Jett\Query\Builder;
use Zakirkun\Jett\Database\Connection;

abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $attributes = [];
    protected array $fillable = [];
    protected array $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected array $casts = [];
    protected array $relations = [];
    protected bool $timestamps = true;
    protected bool $softDeletes = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    public function fill(array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            if (in_array($key, $this->fillable)) {
                $this->attributes[$key] = $value;
            }
        }
    }

    public function __get(string $name)
    {
        if (array_key_exists($name, $this->attributes)) {
            return $this->castAttribute($name, $this->attributes[$name]);
        }

        if (method_exists($this, $name)) {
            return $this->getRelationValue($name);
        }

        return null;
    }

    public function __set(string $name, $value): void
    {
        if (in_array($name, $this->fillable)) {
            $this->attributes[$name] = $value;
        }
    }

    protected function castAttribute(string $key, $value)
    {
        if (!isset($this->casts[$key])) {
            return $value;
        }

        switch ($this->casts[$key]) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'array':
                return json_decode($value, true);
            case 'object':
                return json_decode($value);
            case 'datetime':
                return new \DateTime($value);
            default:
                return $value;
        }
    }

    protected function getRelationValue(string $method)
    {
        if (isset($this->relations[$method])) {
            return $this->relations[$method];
        }

        $relation = $this->$method();
        return $this->relations[$method] = $relation;
    }

    public function hasOne(string $related, string $foreignKey = null, string $localKey = null): HasOne
    {
        $foreignKey = $foreignKey ?? $this->getForeignKey();
        $localKey = $localKey ?? $this->primaryKey;
        
        return new HasOne($this, new $related(), $foreignKey, $localKey);
    }

    public function hasMany(string $related, string $foreignKey = null, string $localKey = null): HasMany
    {
        $foreignKey = $foreignKey ?? $this->getForeignKey();
        $localKey = $localKey ?? $this->primaryKey;
        
        return new HasMany($this, new $related(), $foreignKey, $localKey);
    }

    public function belongsTo(string $related, string $foreignKey = null, string $ownerKey = null): BelongsTo
    {
        $foreignKey = $foreignKey ?? $this->getForeignKey();
        $ownerKey = $ownerKey ?? 'id';
        
        return new BelongsTo($this, new $related(), $foreignKey, $ownerKey);
    }

    protected function getForeignKey(): string
    {
        return strtolower(class_basename($this)) . '_id';
    }

    public static function query(): Builder
    {
        $instance = new static();
        return new Builder($instance->getTable());
    }

    public static function all(): array
    {
        return static::query()->get();
    }

    public static function find($id)
    {
        $instance = new static();
        return static::query()->where($instance->primaryKey, '=', $id)->first();
    }

    public function save(): bool
    {
        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            $this->attributes['updated_at'] = $now;
            
            if (!isset($this->attributes['created_at'])) {
                $this->attributes['created_at'] = $now;
            }
        }

        $pdo = Connection::getInstance();
        
        if (isset($this->attributes[$this->primaryKey])) {
            // Update
            $sets = [];
            $values = [];
            foreach ($this->attributes as $key => $value) {
                if ($key !== $this->primaryKey) {
                    $sets[] = "{$key} = ?";
                    $values[] = $value;
                }
            }
            $values[] = $this->attributes[$this->primaryKey];
            
            $sql = "UPDATE {$this->getTable()} SET " . implode(', ', $sets) . 
                   " WHERE {$this->primaryKey} = ?";
            
            $stmt = $pdo->prepare($sql);
            return $stmt->execute($values);
        } else {
            // Insert
            $columns = array_keys($this->attributes);
            $values = array_values($this->attributes);
            $placeholders = array_fill(0, count($values), '?');
            
            $sql = "INSERT INTO {$this->getTable()} (" . implode(', ', $columns) . 
                   ") VALUES (" . implode(', ', $placeholders) . ")";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($values);
            
            if ($result) {
                $this->attributes[$this->primaryKey] = $pdo->lastInsertId();
            }
            
            return $result;
        }
    }

    public function delete(): bool
    {
        if ($this->softDeletes) {
            return $this->softDelete();
        }

        if (!isset($this->attributes[$this->primaryKey])) {
            return false;
        }

        $pdo = Connection::getInstance();
        $sql = "DELETE FROM {$this->getTable()} WHERE {$this->primaryKey} = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$this->attributes[$this->primaryKey]]);
    }

    protected function softDelete(): bool
    {
        $this->attributes['deleted_at'] = date('Y-m-d H:i:s');
        return $this->save();
    }

    public static function withTrashed(): Builder
    {
        return static::query();
    }

    public static function onlyTrashed(): Builder
    {
        return static::query()->where('deleted_at', 'IS NOT', null);
    }

    protected function getTable(): string
    {
        return $this->table ?? strtolower(class_basename(get_class($this))) . 's';
    }
}
