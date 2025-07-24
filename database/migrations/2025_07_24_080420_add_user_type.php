<?php

// 2025_07_24_080420_add_user_type.php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

function up()
{
    try {
        $tableName = 'users';

        if (!Capsule::schema()->hasTable($tableName)) {
            echo "Creating '$tableName' table..." . PHP_EOL;

            Capsule::schema()->create($tableName, function (Blueprint $table) {
                $table->id();
                // Add more fields as needed
                $table->timestamps();
            });

            echo "Table '$tableName' created successfully!" . PHP_EOL;
        } else {
            echo "Modifying '$tableName' table..." . PHP_EOL;

            Capsule::schema()->table($tableName, function (Blueprint $table) {
                if (!Capsule::schema()->hasColumn('users', 'type')) {
                    $table->enum('type',['Staff','Manager','Admin'])->default('Staff');
                }
            });

            echo "Table '$tableName' modified successfully!" . PHP_EOL;
        }

    } catch (\Exception $e) {
        $errorMessage = "Error updating '$tableName' table: " . $e->getMessage();
        echo $errorMessage . PHP_EOL;
        if (function_exists('Logger::log')) {
            Logger::log("MIGRATION FAILED: " . $errorMessage);
        }
        throw $e;
    }
}

function down()
{
    try {
        $tableName = '_user_type';
        echo "Dropping '$tableName' table..." . PHP_EOL;
        Capsule::schema()->dropIfExists($tableName);
        echo "Table '$tableName' dropped successfully!" . PHP_EOL;
        if (function_exists('Logger::log')) {
            Logger::log("MIGRATION SUCCESS: Dropped '$tableName' table.");
        }

    } catch (\Exception $e) {
        $errorMessage = "Error dropping '$tableName' table: " . $e->getMessage();
        echo $errorMessage . PHP_EOL;
        if (function_exists('Logger::log')) {
            Logger::log("MIGRATION FAILED: " . $errorMessage);
        }
        throw $e;
    }
}