<?php

// 2025_07_23_151609_test_migrate.php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

// IMPORTANT: Make sure your Logger class is available here.
// If it's autoloaded by Composer (recommended), you don't need a direct require_once here.
// If not, you might need: require_once __DIR__ . '/../app/Logger.php'; // Adjust path

require 'core/Logger.php';

function up()
{
    try {
        echo "Attempting to table 'test_migrate' table..." . PHP_EOL;
        Capsule::schema()->table('test_migrate', function (Blueprint $table) {
            // Add your table columns here
            // This is a placeholder. You'll fill this in after generation.
            $table->id(); // Example for 'create' operation
            // $table->string('name')->nullable();
            // $table->integer('age')->default(0);
            $table->timestamps(); // Example for 'create' operation
        });
        echo "'test_migrate' table tabled successfully!" . PHP_EOL;
        Logger::log("MIGRATION SUCCESS: tabled 'test_migrate' table.");

    } catch (\Exception $e) { // Catch any general PHP Exception
        // Log the detailed error message
        $errorMessage = "Error tableing 'test_migrate' table: " . $e->getMessage();
        echo $errorMessage . PHP_EOL;
        Logger::log("MIGRATION FAILED: " . $errorMessage);
        // It's good practice to re-throw the exception so the runner script knows it failed,
        // and doesn't incorrectly report overall success.
        throw $e;
    }
}

function down()
{
    try {
        echo "Attempting to drop 'test_migrate' table..." . PHP_EOL;
        Capsule::schema()->dropIfExists('test_migrate');
        echo "'test_migrate' table dropped successfully!" . PHP_EOL;
        Logger::log("MIGRATION SUCCESS: Dropped 'test_migrate' table.");

    } catch (\Exception $e) {
        $errorMessage = "Error dropping 'test_migrate' table: " . $e->getMessage();
        echo $errorMessage . PHP_EOL;
        Logger::log("MIGRATION FAILED: " . $errorMessage);
        throw $e;
    }
}