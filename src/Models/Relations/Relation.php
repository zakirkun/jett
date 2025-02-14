<?php

namespace Zakirkun\Jett\Models\Relations;

use Zakirkun\Jett\Models\Model;
use Zakirkun\Jett\Query\Builder;

abstract class Relation
{
    protected Model $parent;
    protected Model $related;
    protected string $foreignKey;
    protected string $localKey;
    protected Builder $query;

    public function __construct(Model $parent, Model $related, string $foreignKey, string $localKey)
    {
        $this->parent = $parent;
        $this->related = $related;
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
        $this->query = $related::query();
    }

    abstract public function getResults();
}
