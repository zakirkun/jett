<?php

namespace Zakirkun\Jett\Events;

class ModelSubscriber extends Subscriber
{
    public function subscribe(): void
    {
        // Model lifecycle events
        $this->listen('model.creating', [$this, 'onCreating']);
        $this->listen('model.created', [$this, 'onCreated']);
        $this->listen('model.updating', [$this, 'onUpdating']);
        $this->listen('model.updated', [$this, 'onUpdated']);
        $this->listen('model.deleting', [$this, 'onDeleting']);
        $this->listen('model.deleted', [$this, 'onDeleted']);
        $this->listen('model.saving', [$this, 'onSaving']);
        $this->listen('model.saved', [$this, 'onSaved']);
        
        // Cache events
        $this->listen('model.cached', [$this, 'onCached']);
        $this->listen('model.cache.cleared', [$this, 'onCacheCleared']);
    }

    public function onCreating($model): void
    {
        // Set default values, generate slugs, etc.
        if (method_exists($model, 'beforeCreate')) {
            $model->beforeCreate();
        }
    }

    public function onCreated($model): void
    {
        // Clear related caches, notify observers, etc.
        DistributedCache::tags(["model:{$model->getTable()}"])->flush();
        
        if (method_exists($model, 'afterCreate')) {
            $model->afterCreate();
        }
    }

    public function onUpdating($model): void
    {
        // Validate changes, log modifications, etc.
        if (method_exists($model, 'beforeUpdate')) {
            $model->beforeUpdate();
        }
    }

    public function onUpdated($model): void
    {
        // Clear caches, update search indexes, etc.
        DistributedCache::tags(["model:{$model->getTable()}"])->flush();
        
        if (method_exists($model, 'afterUpdate')) {
            $model->afterUpdate();
        }
    }

    public function onDeleting($model): void
    {
        // Check dependencies, backup data, etc.
        if (method_exists($model, 'beforeDelete')) {
            $model->beforeDelete();
        }
    }

    public function onDeleted($model): void
    {
        // Clean up related data, notify observers, etc.
        DistributedCache::tags(["model:{$model->getTable()}"])->flush();
        
        if (method_exists($model, 'afterDelete')) {
            $model->afterDelete();
        }
    }

    public function onSaving($model): void
    {
        // Common validation, timestamp updates, etc.
        if (method_exists($model, 'beforeSave')) {
            $model->beforeSave();
        }
    }

    public function onSaved($model): void
    {
        // Update caches, trigger webhooks, etc.
        if (method_exists($model, 'afterSave')) {
            $model->afterSave();
        }
    }

    public function onCached($model, $key): void
    {
        // Log cache operations, update stats, etc.
        EventManager::dispatch('cache.hit', [
            'model' => get_class($model),
            'key' => $key,
            'timestamp' => time()
        ]);
    }

    public function onCacheCleared($model): void
    {
        // Log cache clearing, notify services, etc.
        EventManager::dispatch('cache.cleared', [
            'model' => get_class($model),
            'timestamp' => time()
        ]);
    }
}
