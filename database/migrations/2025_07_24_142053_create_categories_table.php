<?php

// 2025_07_24_142053_create_categories_table.php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

// Assuming Logger is available globally or via a use statement if in a namespace
// use YourApp\Core\Logger;

function up()
{
    $tableName = 'categories';

    try {
        if (!Capsule::schema()->hasTable($tableName)) {
            echo "Creating '$tableName' table..." . PHP_EOL;

            Capsule::schema()->create($tableName, function (Blueprint $table) {
                $table->id();
                // Name should be unique and not nullable for a category
                $table->string("name", 50)->unique()->nullable(false);
                // Use text for description if it can be longer than 255 chars
                $table->text("description")->nullable();
                $table->timestamps();
            });

            echo "Table '$tableName' created successfully!" . PHP_EOL;
        } else {
            echo "Modifying '$tableName' table..." . PHP_EOL;

            Capsule::schema()->table($tableName, function (Blueprint $table) use ($tableName) {
                // Ensure 'name' column is present, unique, and correct length/nullability
                if (!Capsule::schema()->hasColumn($tableName, 'name')) {
                    $table->string('name', 50)->unique()->nullable(false)->after('id');
                    echo " - Added 'name' column (VARCHAR 50, UNIQUE, NOT NULL)." . PHP_EOL;
                } else {
                    // If 'name' exists but its definition might be wrong (e.g., wrong length or not unique)
                    // This requires 'doctrine/dbal'
                    $table->string('name', 50)->unique()->nullable(false)->change();
                    echo " - Ensured 'name' column is VARCHAR 50, UNIQUE, NOT NULL." . PHP_EOL;
                }

                // Ensure 'description' column is present and correct type/nullability
                if (!Capsule::schema()->hasColumn($tableName, 'description')) {
                    $table->text('description')->nullable()->after('name');
                    echo " - Added 'description' column (TEXT, NULLABLE)." . PHP_EOL;
                } else {
                    // If 'description' exists but its definition might be wrong (e.g., was string, now text)
                    // This requires 'doctrine/dbal'
                    $table->text('description')->nullable()->change();
                    echo " - Ensured 'description' column is TEXT, NULLABLE." . PHP_EOL;
                }
            });

            echo "Table '$tableName' modified successfully!" . PHP_EOL;
        }

    } catch (\Exception $e) {
        $errorMessage = "Error updating '$tableName' table: " . $e->getMessage();
        echo $errorMessage . PHP_EOL;
        if (function_exists('Logger::log')) {
            Logger::log("MIGRATION FAILED: " . $errorMessage);
        } elseif (class_exists('Logger') && method_exists('Logger', 'log')) { // More robust check
            Logger::log("MIGRATION FAILED: " . $errorMessage);
        } else {
            error_log("MIGRATION FAILED: " . $errorMessage); // Fallback to PHP error log
        }
        throw $e;
    }
}

function down()
{
    try {
        $tableName = 'categories';
        echo "Dropping '$tableName' table..." . PHP_EOL;
        Capsule::schema()->dropIfExists($tableName);
        echo "Table '$tableName' dropped successfully!" . PHP_EOL;
        if (function_exists('Logger::log')) {
            Logger::log("MIGRATION SUCCESS: Dropped '$tableName' table.");
        } elseif (class_exists('Logger') && method_exists('Logger', 'log')) {
            Logger::log("MIGRATION SUCCESS: Dropped '$tableName' table.");
        } else {
            error_log("MIGRATION SUCCESS: Dropped '$tableName' table.");
        }

    } catch (\Exception $e) {
        $errorMessage = "Error dropping '$tableName' table: " . $e->getMessage();
        echo $errorMessage . PHP_EOL;
        if (function_exists('Logger::log')) {
            Logger::log("MIGRATION FAILED: " . $errorMessage);
        } elseif (class_exists('Logger') && method_exists('Logger', 'log')) {
            Logger::log("MIGRATION FAILED: " . $errorMessage);
        } else {
            error_log("MIGRATION FAILED: " . $errorMessage);
        }
        throw $e;
    }
}