<?php

namespace Zakirkun\Jett\Testing;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Zakirkun\Jett\Database\Connection;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
        $this->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->rollbackTransaction();
        parent::tearDown();
    }

    protected function setUpDatabase(): void
    {
        // Override this method to set up your test database
        Connection::setConfig([
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => '3306',
            'database' => 'test_database',
            'username' => 'test_user',
            'password' => 'test_password'
        ]);
    }

    protected function beginTransaction(): void
    {
        Connection::beginTransaction();
    }

    protected function rollbackTransaction(): void
    {
        Connection::rollBack();
    }

    protected function assertDatabaseHas(string $table, array $data): void
    {
        $query = "SELECT COUNT(*) as count FROM {$table} WHERE ";
        $conditions = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $conditions[] = "{$column} = ?";
            $bindings[] = $value;
        }

        $query .= implode(' AND ', $conditions);
        $stmt = Connection::getInstance()->prepare($query);
        $stmt->execute($bindings);
        $result = $stmt->fetch();

        $this->assertGreaterThan(0, $result['count'], "Failed asserting that the database table [{$table}] contains the expected data.");
    }

    protected function assertDatabaseMissing(string $table, array $data): void
    {
        $query = "SELECT COUNT(*) as count FROM {$table} WHERE ";
        $conditions = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $conditions[] = "{$column} = ?";
            $bindings[] = $value;
        }

        $query .= implode(' AND ', $conditions);
        $stmt = Connection::getInstance()->prepare($query);
        $stmt->execute($bindings);
        $result = $stmt->fetch();

        $this->assertEquals(0, $result['count'], "Failed asserting that the database table [{$table}] does not contain the expected data.");
    }

    protected function assertModelExists($model): void
    {
        $this->assertTrue(
            $model !== null && isset($model->{$model->primaryKey}),
            "Failed asserting that the model exists in the database."
        );
    }

    protected function assertModelMissing($model): void
    {
        $this->assertTrue(
            $model === null || !isset($model->{$model->primaryKey}),
            "Failed asserting that the model does not exist in the database."
        );
    }
}
