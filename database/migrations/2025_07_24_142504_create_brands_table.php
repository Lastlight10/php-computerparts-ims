<?php

// 2025_07_24_XXXXXX_create_brands_table.php (Adjust timestamp in filename)

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

// Assuming Logger is available globally or via a use statement if in a namespace
// use YourApp\Core\Logger;

function up()
{
    $tableName = 'brands'; // Correct table name

    try {
        if (!Capsule::schema()->hasTable($tableName)) {
            echo "Creating '$tableName' table..." . PHP_EOL;

            Capsule::schema()->create($tableName, function (Blueprint $table) {
                $table->id();
                // Name should be unique and not nullable for a brand, length 100 as per schema
                $table->string("name", 100)->unique()->nullable(false);
                // Website and contact_email as VARCHAR(255) as per schema
                $table->string("website", 255)->nullable();
                $table->string("contact_email", 255)->nullable();
                $table->timestamps();
            });

            echo "Table '$tableName' created successfully!" . PHP_EOL;
        } else {
            echo "Modifying '$tableName' table..." . PHP_EOL;

            Capsule::schema()->table($tableName, function (Blueprint $table) use ($tableName) {
                // Ensure 'name' column is present, unique, and correct length/nullability (VARCHAR 100)
                if (!Capsule::schema()->hasColumn($tableName, 'name')) {
                    $table->string('name', 100)->unique()->nullable(false)->after('id');
                    echo " - Added 'name' column (VARCHAR 100, UNIQUE, NOT NULL)." . PHP_EOL;
                } else {
                    // If 'name' exists but its definition might be wrong (e.g., wrong length or not unique)
                    // This requires 'doctrine/dbal'
                    $table->string('name', 100)->unique()->nullable(false)->change();
                    echo " - Ensured 'name' column is VARCHAR 100, UNIQUE, NOT NULL." . PHP_EOL;
                }

                // Ensure 'website' column is present and correct type/nullability (VARCHAR 255)
                if (!Capsule::schema()->hasColumn($tableName, 'website')) {
                    $table->string('website', 255)->nullable()->after('name');
                    echo " - Added 'website' column (VARCHAR 255, NULLABLE)." . PHP_EOL;
                } else {
                    // This requires 'doctrine/dbal'
                    $table->string('website', 255)->nullable()->change();
                    echo " - Ensured 'website' column is VARCHAR 255, NULLABLE." . PHP_EOL;
                }

                // Ensure 'contact_email' column is present and correct type/nullability (VARCHAR 255)
                if (!Capsule::schema()->hasColumn($tableName, 'contact_email')) {
                    $table->string('contact_email', 255)->nullable()->after('website');
                    echo " - Added 'contact_email' column (VARCHAR 255, NULLABLE)." . PHP_EOL;
                } else {
                    // This requires 'doctrine/dbal'
                    $table->string('contact_email', 255)->nullable()->change();
                    echo " - Ensured 'contact_email' column is VARCHAR 255, NULLABLE." . PHP_EOL;
                }
            });

            echo "Table '$tableName' modified successfully!" . PHP_EOL;
        }

    } catch (\Exception $e) {
        $errorMessage = "Error updating '$tableName' table: " . $e->getMessage();
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

function down()
{
    $tableName = 'brands'; // CRITICAL FIX: Changed from 'categories' to 'brands'

    try {
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