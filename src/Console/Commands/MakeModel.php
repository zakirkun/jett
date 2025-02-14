<?php

namespace Zakirkun\Jett\Console\Commands;

use Zakirkun\Jett\Console\Command;

class MakeModel extends Command
{
    protected string $signature = 'make:model {name} {--migration}';
    protected string $description = 'Create a new model class';

    public function handle(): int
    {
        $name = $this->argument('name');
        $withMigration = $this->option('migration');

        $modelTemplate = <<<PHP
<?php

namespace App\Models;

use Zakirkun\Jett\Models\Model;

class {$name} extends Model
{
    protected array \$fillable = [];
    
    // Define your model properties and relationships here
}
PHP;

        $modelPath = getcwd() . "/app/Models/{$name}.php";
        if (!is_dir(dirname($modelPath))) {
            mkdir(dirname($modelPath), 0777, true);
        }

        file_put_contents($modelPath, $modelTemplate);
        echo "Model created successfully: {$modelPath}\n";

        if ($withMigration) {
            $timestamp = date('Y_m_d_His');
            $table = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name)) . 's';
            
            $migrationTemplate = <<<PHP
<?php

use Zakirkun\Jett\Schema\Migration;
use Zakirkun\Jett\Schema\Blueprint;

class Create{$name}sTable extends Migration
{
    public function up(): void
    {
        \$this->create('{$table}', function (Blueprint \$table) {
            \$table->id();
            // Add your columns here
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        \$this->drop('{$table}');
    }
}
PHP;

            $migrationPath = getcwd() . "/database/migrations/{$timestamp}_create_{$table}_table.php";
            if (!is_dir(dirname($migrationPath))) {
                mkdir(dirname($migrationPath), 0777, true);
            }

            file_put_contents($migrationPath, $migrationTemplate);
            echo "Migration created successfully: {$migrationPath}\n";
        }

        return 0;
    }
}
