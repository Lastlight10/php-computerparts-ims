<?php
// run_rollback.php (in your project root)

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Starting migration rollback runner..." . PHP_EOL;

// 1. Load Composer's autoloader
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    echo "Error: Composer autoload file not found at " . $autoloadPath . PHP_EOL;
    Logger::log("ROLLBACK FAILED: Can't load autoloader? - " . $autoloadPath);
    exit(1);
}
require_once $autoloadPath;
echo "Composer autoloader loaded." . PHP_EOL;

// 2. Set up the database connection (Capsule Manager)
require_once __DIR__ . '/core/Connection.php';
echo "Database connection (Capsule) loaded." . PHP_EOL;

// Include Logger (assuming it's in app/Logger.php relative to project root)
require_once __DIR__ . '/app/Logger.php'; // Adjust path if necessary

// --- Argument Handling ---
if ($argc < 2) {
    echo "Usage: php " . basename(__FILE__) . " <migration_filename_without_php_extension>" . PHP_EOL;
    echo "Example: php " . basename(__FILE__) . " 2025_07_23_CreateUserTable" . PHP_EOL;
    Logger::log("ROLLBACK FAILED: Wrong arguments");
    exit(1);
}

$migrationFilename = $argv[1]; // Get the first argument
$migrationFilePath = __DIR__ . '/migrations/' . $migrationFilename . '.php';

// Check if the migration file exists
echo "Attempting to include migration file for rollback: " . $migrationFilePath . PHP_EOL;
if (!file_exists($migrationFilePath)) {
    echo "Error: Migration file not found at " . $migrationFilePath . PHP_EOL;
    Logger::log("ROLLBACK FAILED: Migration file does not exist? - " . $migrationFilePath);
    exit(1);
}

// --- End Argument Handling ---

// 3. Include the specified migration file
require_once $migrationFilePath;
echo "Migration file '" . $migrationFilename . "' included successfully." . PHP_EOL;


// 4. Call the 'down' function from the migration
if (function_exists('down')) { // <<<<<<<<<<<<<< KEY CHANGE HERE: CALLING DOWN()
    echo "Executing 'down' function..." . PHP_EOL;
    try {
        down();
        echo "--- Migration '{$migrationFilename}' DOWN process finished ---" . PHP_EOL;
        Logger::log("ROLLBACK SUCCESS: '{$migrationFilename}");
    } catch (\Exception $e) {
        $errorMessage = "Error during DOWN process for '{$migrationFilename}': " . $e->getMessage();
        echo $errorMessage . PHP_EOL;
        Logger::log("ROLLBACK FAILED: " . $errorMessage);
        exit(1); // Exit with error if rollback fails
    }
} else {
    echo "Error: 'down' function not found after including '{$migrationFilename}'. " .
         "Please check if 'down()' is defined globally in that file, or if there's a syntax error preventing it from being parsed." . PHP_EOL;
    Logger::log("ROLLBACK ERROR: '{$migrationFilename} - No down() function found after inclusion.");
    exit(1);
}

?>