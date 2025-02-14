<?php

namespace Zakirkun\Jett\Models\Relations;

class HasOne extends Relation
{
    public function getResults()
    {
        return $this->query
            ->where($this->foreignKey, '=', $this->parent->{$this->localKey})
            ->first();
    }
}
