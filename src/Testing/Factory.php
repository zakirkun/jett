<?php

namespace Zakirkun\Jett\Testing;

use Faker\Factory as FakerFactory;

abstract class Factory
{
    protected static $faker;
    protected string $model;
    protected array $states = [];
    protected array $afterMaking = [];
    protected array $afterCreating = [];

    public function __construct()
    {
        static::$faker = static::$faker ?? FakerFactory::create();
    }

    abstract public function definition(): array;

    public function make(array $attributes = []): object
    {
        $instance = $this->makeInstance($attributes);
        
        foreach ($this->afterMaking as $callback) {
            $callback($instance);
        }
        
        return $instance;
    }

    public function create(array $attributes = []): object
    {
        $instance = $this->make($attributes);
        $instance->save();
        
        foreach ($this->afterCreating as $callback) {
            $callback($instance);
        }
        
        return $instance;
    }

    protected function makeInstance(array $attributes = []): object
    {
        $class = $this->model;
        $definition = $this->definition();
        
        return new $class(array_merge($definition, $attributes));
    }

    public function state(string $state): self
    {
        if (isset($this->states[$state])) {
            $this->definition = array_merge(
                $this->definition(),
                $this->states[$state]()
            );
        }
        
        return $this;
    }

    public function afterMaking(callable $callback): self
    {
        $this->afterMaking[] = $callback;
        return $this;
    }

    public function afterCreating(callable $callback): self
    {
        $this->afterCreating[] = $callback;
        return $this;
    }
}
