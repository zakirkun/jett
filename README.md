# Jett ORM

A powerful and flexible PHP ORM with advanced features for modern web applications.

## Features

### 1. Query Builder
```php
// Basic query
$users = DB::table('users')
    ->where('active', true)
    ->orderBy('name')
    ->get();

// Complex nested queries
$users = DB::table('users')
    ->where(function($query) {
        $query->where('role', 'admin')
              ->orWhere('permissions', 'like', '%manage_users%');
    })
    ->whereExists(function($query) {
        $query->from('posts')
              ->whereColumn('posts.user_id', 'users.id');
    })
    ->get();

// Advanced relationships
$posts = Post::with(['author', 'comments.user'])
    ->withCount('likes')
    ->having('likes_count', '>', 10)
    ->get();
```

### 2. Connection Management
```php
// Configure connection pool
ConnectionPool::configure([
    'min_connections' => 5,
    'max_connections' => 20,
    'idle_timeout' => 300
]);

// Get connection from pool
$connection = ConnectionPool::getConnection();

// Transaction with automatic retry
TransactionManager::transaction(function() {
    // Your transaction logic here
}, $retries = 3);

// Nested transactions
TransactionManager::begin();
try {
    // First operation
    TransactionManager::begin();
    try {
        // Nested operation
        TransactionManager::commit();
    } catch (Exception $e) {
        TransactionManager::rollback();
        throw $e;
    }
    TransactionManager::commit();
} catch (Exception $e) {
    TransactionManager::rollback();
    throw $e;
}
```

### 3. Query Analysis and Optimization
```php
// Start query analysis
QueryAnalyzer::startQuery($sql, $bindings);

// Get query statistics
$stats = QueryAnalyzer::getStatistics();
foreach ($stats as $query => $info) {
    echo "Query: {$query}\n";
    echo "Execution Time: {$info['time']}ms\n";
    echo "Rows Affected: {$info['rows']}\n";
}

// Get slow queries
$slowQueries = QueryAnalyzer::getSlowQueries(1000); // queries taking > 1000ms
```

### 4. Schema Management
```php
// Create table
$schema = new SchemaManager();
$schema->createTable('users', [
    'id' => ['type' => 'INT', 'auto_increment' => true],
    'name' => ['type' => 'VARCHAR', 'length' => 255],
    'email' => ['type' => 'VARCHAR', 'length' => 255, 'unique' => true],
    'created_at' => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP']
]);

// Add foreign key
$schema->addForeignKey('posts', 'user_id', 'users', 'id', [
    'on_delete' => 'CASCADE',
    'on_update' => 'CASCADE'
]);

// Compare schemas
$differences = $schema->compareSchema('users', 'users_backup');
```

### 5. Distributed Cache
```php
// Configure Redis cache
DistributedCache::configure([
    'host' => '127.0.0.1',
    'port' => 6379,
    'prefix' => 'myapp:'
]);

// Basic cache operations
DistributedCache::set('user:1', $userData, 3600); // 1 hour TTL
$user = DistributedCache::get('user:1');

// Cache with tags
DistributedCache::tags(['users', 'active'])
    ->set('user:1', $userData, 3600);

// Atomic operations
$visits = DistributedCache::increment('page:visits');

// Cache with callback
$user = DistributedCache::remember('user:1', 3600, function() {
    return User::find(1);
});
```

### 6. Event System
```php
// Register event listener
EventManager::listen('user.created', function($user) {
    // Handle user created event
});

// Register event subscriber
class UserEventSubscriber extends Subscriber
{
    public function subscribe(): void
    {
        $this->listen('user.created', [$this, 'onUserCreated']);
        $this->listen('user.updated', [$this, 'onUserUpdated']);
    }

    public function onUserCreated($user): void
    {
        // Handle user created
    }

    public function onUserUpdated($user): void
    {
        // Handle user updated
    }
}

EventManager::subscribe(UserEventSubscriber::class);

// Dispatch event
EventManager::dispatch('user.created', [$user]);
```

### 7. CLI Tools
```bash
# Database migrations
./jett migrate make create_users_table
./jett migrate
./jett migrate rollback
./jett migrate reset
./jett migrate status

# Database seeding
./jett db:seed make UserSeeder
./jett db:seed
./jett db:seed run UserSeeder

# Cache management
./jett cache clear
./jett cache list
./jett cache stats

# Database management
./jett db backup
./jett db restore backup.sql
./jett db optimize
./jett db status

# Schema management
./jett schema show users
./jett schema compare users users_backup
./jett schema export schema.json
```

## Installation

```bash
composer require zakirkun/jett
```

## Configuration

Create a configuration file `config/database.php`:

```php
return [
    'default' => 'mysql',
    
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'your_database',
            'username' => 'your_username',
            'password' => 'your_password',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],
    ],
    
    'pool' => [
        'min_connections' => 5,
        'max_connections' => 20,
        'idle_timeout' => 300
    ],
    
    'cache' => [
        'driver' => 'redis',
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => null,
        'prefix' => 'jett:'
    ]
];
```

## Best Practices

1. **Connection Management**
   - Use connection pooling for better performance
   - Always use transactions for data consistency
   - Set appropriate pool sizes based on your application needs

2. **Query Optimization**
   - Use the QueryAnalyzer to identify slow queries
   - Implement proper indexes based on your query patterns
   - Use eager loading to prevent N+1 problems

3. **Caching Strategy**
   - Use tagged cache for easier cache management
   - Implement cache versioning for cache invalidation
   - Set appropriate TTLs for different types of data

4. **Event Handling**
   - Keep event listeners focused and lightweight
   - Use event subscribers for related events
   - Implement proper error handling in event listeners

5. **Schema Management**
   - Always use migrations for schema changes
   - Backup database before major schema changes
   - Use proper foreign key constraints

## Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct, and the process for submitting pull requests.

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.
