<?php
// make_migration.php

if ($argc < 2) {
    echo "Usage: php make_migration.php <MigrationName>\n";
    echo "Example: php make_migration.php CreateUsersTable\n";
    exit(1);
}

$migrationName = $argv[1];
$timestamp = date('Y_m_d_His');
$snakeCase = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $migrationName));
$fileName = "{$timestamp}_{$snakeCase}.php";
$migrationPath = "database/migrations";

// Infer table name
$baseTableName = strtolower(
    preg_replace(
        ['/^(create|add|alter|drop)/i', '/table$/i', '/(?<!^)[A-Z]/'],
        ['', '', '_$0'],
        $migrationName
    )
);
$baseTableName = str_replace('__', '_', $baseTableName);

// Infer operation
if (str_starts_with(strtolower($migrationName), 'create')) {
    $tableOperation = "create";
} elseif (str_starts_with(strtolower($migrationName), 'add')) {
    $tableOperation = "table";
} else {
    $tableOperation = "table"; // fallback/default
}
$tableName = $baseTableName;

if (!is_dir($migrationPath)) {
    mkdir($migrationPath, 0755, true);
}

// Heredoc to write the actual migration file
$fileContent = <<<EOT
<?php

// {$fileName}

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

function up()
{
    try {
        \$tableName = '{$tableName}';

        if (!Capsule::schema()->hasTable(\$tableName)) {
            echo "Creating '\$tableName' table..." . PHP_EOL;

            Capsule::schema()->create(\$tableName, function (Blueprint \$table) {
                \$table->id();
                // Add more fields as needed
                \$table->timestamps();
            });

            echo "Table '\$tableName' created successfully!" . PHP_EOL;
        } else {
            echo "Modifying '\$tableName' table..." . PHP_EOL;

            Capsule::schema()->table(\$tableName, function (Blueprint \$table) {
                if (!Capsule::schema()->hasColumn('{$tableName}', 'example_column')) {
                    \$table->string('example_column')->nullable();
                }
            });

            echo "Table '\$tableName' modified successfully!" . PHP_EOL;
        }

    } catch (\\Exception \$e) {
        \$errorMessage = "Error updating '\$tableName' table: " . \$e->getMessage();
        echo \$errorMessage . PHP_EOL;
        if (function_exists('Logger::log')) {
            Logger::log("MIGRATION FAILED: " . \$errorMessage);
        }
        throw \$e;
    }
}

function down()
{
    try {
        \$tableName = '{$tableName}';
        echo "Dropping '\$tableName' table..." . PHP_EOL;
        Capsule::schema()->dropIfExists(\$tableName);
        echo "Table '\$tableName' dropped successfully!" . PHP_EOL;
        if (function_exists('Logger::log')) {
            Logger::log("MIGRATION SUCCESS: Dropped '\$tableName' table.");
        }

    } catch (\\Exception \$e) {
        \$errorMessage = "Error dropping '\$tableName' table: " . \$e->getMessage();
        echo \$errorMessage . PHP_EOL;
        if (function_exists('Logger::log')) {
            Logger::log("MIGRATION FAILED: " . \$errorMessage);
        }
        throw \$e;
    }
}
EOT;

file_put_contents("{$migrationPath}/{$fileName}", $fileContent);

echo "✅ Migration created: {$migrationPath}/{$fileName}\n";
echo "➡️  You can now customize the schema in the 'up' and 'down' methods.\n";
