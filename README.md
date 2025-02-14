# Jett ORM

Jett adalah ORM (Object-Relational Mapping) sederhana untuk PHP yang memungkinkan Anda berinteraksi dengan database menggunakan objek PHP.

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

## Penggunaan

### Membuat Model

```php
use Zakirkun\Jett\Models\Model;

class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = ['name', 'email', 'password'];
}
```

### Operasi Dasar

```php
// Mengambil semua data
$users = User::all();

// Mencari berdasarkan ID
$user = User::find(1);

// Query Builder
$activeUsers = User::query()
    ->where('status', '=', 'active')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();

// Membuat data baru
$user = new User([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => 'secret'
]);
$user->save();

// Update data
$user = User::find(1);
$user->name = 'Jane Doe';
$user->save();

// Hapus data
$user = User::find(1);
$user->delete();
```

## Fitur

- Koneksi database menggunakan PDO
- Query Builder
- Model CRUD operations
- Method chaining untuk query
- Fillable attributes untuk keamanan mass assignment
- Auto-increment primary key handling

## Kontribusi

Kontribusi selalu diterima. Silakan buat pull request untuk berkontribusi.
