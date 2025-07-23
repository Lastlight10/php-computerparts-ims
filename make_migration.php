<?php
// make_migration.php

if ($argc < 2) {
    echo "Usage: php make_migration.php <migration_name>\n";
    echo "Example: php make_migration.php CreateProductsTable\n";
    exit(1);
}

$migrationName = $argv[1];
$timestamp = date('Y_m_d_His');
$fileName = "{$timestamp}_" . strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $migrationName)) . ".php";
$migrationPath = "database/migrations";

// Derive a snake_case table name from the migration name
// Example: CreateUsersTable -> users, AddColumnToProductsTable -> products
// This is a common convention, but you might need to adjust for specific migration types
$baseTableName = strtolower(
    preg_replace(
        ['/^(create|add|alter|drop)_/', '/_table$/', '/(?<!^)[A-Z]/'],
        ['', '', '_$0'],
        $migrationName
    )
);
$baseTableName = str_replace('__', '_', $baseTableName); // Remove double underscores if any

// If the migration name implies creation (e.g., "CreateUsersTable")
if (str_starts_with(strtolower($migrationName), 'create')) {
    $tableName = str_replace('create_', '', $baseTableName);
    $tableOperation = "create";
} else if (str_starts_with(strtolower($migrationName), 'add')) {
    // This is for adding columns, so we just get the base name without 'add'
    $tableName = str_replace('add_', '', $baseTableName);
    $tableOperation = "table"; // use Capsule::schema()->table
}
// You can add more conditions for 'alter', 'drop', etc.
else {
    $tableName = $baseTableName;
    $tableOperation = "table"; // Default to table for general changes
}


if (!is_dir($migrationPath)) {
    mkdir($migrationPath, 0755, true);
}

// Use Heredoc syntax for multi-line string content
$fileContent = <<<EOT
<?php

// {$fileName}

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

// IMPORTANT: Make sure your Logger class is available here.
// If it's autoloaded by Composer (recommended), you don't need a direct require_once here.
// If not, you might need: require_once __DIR__ . '/../app/Logger.php'; // Adjust path

require 'core/Logger.php'

function up()
{
    try {
        echo "Attempting to {$tableOperation} '{$tableName}' table..." . PHP_EOL;
        Capsule::schema()->{$tableOperation}('{$tableName}', function (Blueprint \$table) {
            // Add your table columns here
            // This is a placeholder. You'll fill this in after generation.
            \$table->id(); // Example for 'create' operation
            // \$table->string('name')->nullable();
            // \$table->integer('age')->default(0);
            \$table->timestamps(); // Example for 'create' operation
        });
        echo "'{$tableName}' table {$tableOperation}d successfully!" . PHP_EOL;
        Logger::log("MIGRATION SUCCESS: {$tableOperation}d '{$tableName}' table.");

    } catch (\Exception \$e) { // Catch any general PHP Exception
        // Log the detailed error message
        \$errorMessage = "Error {$tableOperation}ing '{$tableName}' table: " . \$e->getMessage();
        echo \$errorMessage . PHP_EOL;
        Logger::log("MIGRATION FAILED: " . \$errorMessage);
        // It's good practice to re-throw the exception so the runner script knows it failed,
        // and doesn't incorrectly report overall success.
        throw \$e;
    }
}

function down()
{
    try {
        echo "Attempting to drop '{$tableName}' table..." . PHP_EOL;
        Capsule::schema()->dropIfExists('{$tableName}');
        echo "'{$tableName}' table dropped successfully!" . PHP_EOL;
        Logger::log("MIGRATION SUCCESS: Dropped '{$tableName}' table.");

    } catch (\Exception \$e) {
        \$errorMessage = "Error dropping '{$tableName}' table: " . \$e->getMessage();
        echo \$errorMessage . PHP_EOL;
        Logger::log("MIGRATION FAILED: " . \$errorMessage);
        throw \$e;
    }
}
EOT;

file_put_contents("{$migrationPath}/{$fileName}", $fileContent);

echo "Migration file created: {$migrationPath}/{$fileName}\n";
echo "Remember to fill in your specific table schema in the 'up' and 'down' methods.\n";