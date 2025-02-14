# Jett ORM

Jett adalah ORM (Object-Relational Mapping) sederhana untuk PHP yang memungkinkan Anda berinteraksi dengan database menggunakan objek PHP. ORM ini menyediakan fitur-fitur lengkap untuk memudahkan pengembangan aplikasi modern.

## Instalasi

```bash
composer require zakirkun/jett
```

## Konfigurasi

```php
use Zakirkun\Jett\Jett;

// Konfigurasi database
$config = [
    'driver' => 'mysql',
    'host' => 'localhost',
    'port' => '3306',
    'database' => 'nama_database',
    'username' => 'root',
    'password' => ''
];

// Inisialisasi Jett
Jett::configure($config);
```

## Fitur Utama

### 1. Query Builder yang Powerful

```php
// Basic Queries
$users = User::query()
    ->where('status', '=', 'active')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();

// Nested Queries
$users = User::query()
    ->where('status', 'active')
    ->where(function($query) {
        $query->where('age', '>=', 18)
              ->orWhere('has_parental_consent', true);
    })
    ->get();

// Complex Nested Conditions
$posts = Post::query()
    ->where(function($query) {
        $query->where('status', 'published')
              ->where(function($q) {
                  $q->where('author_id', 1)
                    ->orWhere('is_featured', true);
              });
    })
    ->orWhere(function($query) {
        $query->where('user_id', auth()->id())
              ->where('status', 'draft');
    })
    ->get();

// Advanced Where Clauses
$users = User::query()
    ->whereIn('id', [1, 2, 3])
    ->whereNotIn('status', ['banned', 'inactive'])
    ->whereBetween('age', [18, 65])
    ->whereNull('deleted_at')
    ->whereExists(function($query) {
        $query->select('id')
              ->from('posts')
              ->whereColumn('posts.user_id', 'users.id');
    })
    ->get();

// Raw Queries
$users = User::query()
    ->whereRaw('YEAR(birthday) = ?', [1990])
    ->get();

// Joins
$posts = Post::query()
    ->select(['posts.*', 'users.name as author'])
    ->join('users', 'posts.user_id', '=', 'users.id')
    ->where('posts.status', '=', 'published')
    ->get();

// Aggregates
$totalUsers = User::query()->count();
$averageAge = User::query()->avg('age');
$maxSalary = Employee::query()->max('salary');
```

### 2. Bulk Operations

```php
// Bulk Insert
User::query()->insert([
    ['name' => 'John', 'email' => 'john@example.com'],
    ['name' => 'Jane', 'email' => 'jane@example.com']
]);

// Bulk Update
User::query()->bulkUpdate([
    ['id' => 1, 'status' => 'active'],
    ['id' => 2, 'status' => 'inactive']
]);

// Bulk Delete
User::query()->bulkDelete([1, 2, 3]);

// Upsert
User::query()->upsert(
    ['email' => 'john@example.com', 'name' => 'John'],
    ['email'], // unique by
    ['name']   // update columns
);
```

### 3. Event System

```php
use Zakirkun\Jett\Events\Event;

// Register event listener
Event::listen('user.created', function($user) {
    // Send welcome email
});

// Dispatch event
Event::dispatch('user.created', ['user' => $user]);
```

### 4. Validation

```php
use Zakirkun\Jett\Validation\Validator;

class User extends Model
{
    protected array $rules = [
        'name' => 'required|min:3',
        'email' => 'required|email|unique:users',
        'age' => 'integer|min:18'
    ];

    public function validate(): bool
    {
        $validator = new Validator($this->attributes, $this->rules);
        return $validator->validate();
    }
}
```

### 5. Caching

```php
use Zakirkun\Jett\Cache\Cache;

// Basic caching
Cache::set('key', 'value', 3600); // Cache for 1 hour
$value = Cache::get('key', 'default');

// Tagged cache
Cache::tags(['users', 'api'])->set('user:1', $user, 3600);
Cache::tags(['users'])->flush(); // Flush all users cache

// Remember pattern
$value = Cache::remember('key', 3600, function() {
    return expensive_operation();
});
```

### 6. Security Features

```php
use Zakirkun\Jett\Security\Security;

// XSS Protection
$safeHtml = Security::sanitize($userInput);

// Password Hashing
$hash = Security::hashPassword($password);
if (Security::verifyPassword($password, $hash)) {
    // Password matches
}

// Rate Limiting
if (Security::rateLimit('api:' . $userId, 60, 1)) {
    // Process request
} else {
    // Too many requests
}

// CSRF Protection
$token = Security::generateToken();
if (Security::verifyToken($userToken, $storedToken)) {
    // Token valid
}
```

### 7. Testing

```php
use Zakirkun\Jett\Testing\TestCase;

class UserTest extends TestCase
{
    public function testCreateUser()
    {
        $user = User::factory()->create([
            'name' => 'Test User'
        ]);

        $this->assertModelExists($user);
        $this->assertDatabaseHas('users', [
            'name' => 'Test User'
        ]);
    }
}
```

### 8. Model Factories

```php
use Zakirkun\Jett\Testing\Factory;

class UserFactory extends Factory
{
    protected string $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail
        ];
    }

    public function admin(): self
    {
        return $this->state('admin');
    }
}

// Usage
$user = User::factory()->create();
$admin = User::factory()->admin()->create();
```

### 9. CLI Commands

```bash
# Generate model
php jett make:model User --migration

# Generate migration
php jett make:migration create_users_table
```

## Fitur Lainnya

- Transaction support dengan nested transactions
- Soft deletes
- Timestamps handling
- Model relationships (hasOne, hasMany, belongsTo)
- Query logging dan debugging
- Connection pooling
- Database seeding
- Migration system

## Best Practices

1. **Validasi Data**
   - Selalu validasi input sebelum menyimpan ke database
   - Gunakan fitur validasi bawaan

2. **Security**
   - Gunakan prepared statements (sudah otomatis)
   - Sanitize semua user input
   - Implementasi rate limiting untuk API
   - Gunakan CSRF protection untuk forms

3. **Performance**
   - Gunakan bulk operations untuk operasi massal
   - Manfaatkan fitur caching
   - Optimalkan query dengan select kolom yang diperlukan saja

4. **Testing**
   - Buat unit test untuk setiap model
   - Gunakan factories untuk test data
   - Manfaatkan database transactions dalam testing

## Kontribusi

Kontribusi selalu diterima. Silakan buat pull request untuk berkontribusi.

## License

MIT License
