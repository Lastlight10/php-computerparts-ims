<?php
// run_migration.php (in your project root)

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Starting migration runner..." . PHP_EOL;

// 1. Load Composer's autoloader
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    echo "Error: Composer autoload file not found at " . $autoloadPath . PHP_EOL;
    Logger::log("MIGRATION FAILED: Can't load autoloader? - " . $connectionPath);
    exit(1);
}
require_once $autoloadPath;
echo "Composer autoloader loaded." . PHP_EOL;


// 2. Set up the database connection (Capsule Manager)
$connectionPath = 'core/Connection.php';
if (!file_exists($connectionPath)) {
    echo "Error: Database connection file not found at " . $connectionPath . PHP_EOL;
    Logger::log("MIGRATION FAILED: Can't find or not exists? - " . $connectionPath);
    exit(1);
}
require $connectionPath;
echo "Database connection (Capsule) loaded." . PHP_EOL;


// --- Argument Handling ---
if ($argc < 2) {
    echo "Usage: php " . basename(__FILE__) . " <migration_filename_without_php_extension>" . PHP_EOL;
    echo "Example: php " . basename(__FILE__) . " 2025_07_23_CreateUserTable" . PHP_EOL;
    Logger::log("MIGRATION FAILED: Wrong arguments");
    exit(1);
}

$migrationFilename = $argv[1]; // Get the first argument

// Construct the full path to the migration file
$migrationFilePath = __DIR__ . '/database/migrations/' . $migrationFilename;

// Check if the migration file exists
echo "Attempting to include migration file: " . $migrationFilePath . PHP_EOL;
if (!file_exists($migrationFilePath)) {
    echo "Error: Migration file not found at " . $migrationFilePath . PHP_EOL;
    Logger::log("MIGRATION FAILED: Does this exists? - " . $migrationFilePath);
    exit(1);
}

// --- End Argument Handling ---


// 3. Include the specified migration file
// This is where the 'up' function *should* become available
require_once $migrationFilePath;
echo "Migration file '" . $migrationFilename . "' included successfully." . PHP_EOL;


// 4. Call the 'up' function from the migration
if (function_exists('up')) {
    echo "Executing 'up' function..." . PHP_EOL;
    up();
    echo "--- Migration '{$migrationFilename}' UP process finished ---" . PHP_EOL;
    Logger::log("MIGRATION SUCCESS: '{$migrationFilename}");
} else {
    echo "Error: 'up' function not found after including '{$migrationFilename}'. " .
         "Please check if 'up()' is defined globally in that file, or if there's a syntax error preventing it from being parsed." . PHP_EOL;
    Logger::log("MIGRATION ERROR: '{$migrationFilename} - No up()?");
    exit(1);
}

?>