<?php
use App\Core\Connection;
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Load Composer's autoloader FIRST
require_once __DIR__ . '/vendor/autoload.php';

// 2. Start the session (if not already done)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 3. Import necessary classes using their namespaces
use App\Core\Router;
use App\Core\Logger;
use Illuminate\Database\Capsule\Manager as DB; // Add this if you want to use DB facade for diagnostic

// *** CRITICAL: Call Connection::init() here, early in the script ***
try {
    Connection::init(); // This now handles Eloquent bootstrapping
    Logger::log("APP_INIT: Database and Eloquent initialized successfully.");
} catch (\Exception $e) {
    Logger::log("APP_FATAL_ERROR: Application failed to initialize database/Eloquent: " . $e->getMessage());
    http_response_code(500);
    echo "<h1>Error: Application Startup Failed</h1>";
    echo "<p>A critical error occurred during database initialization. Please check logs.</p>";
    exit(1);
}

// --- MORE VERBOSE DIAGNOSTIC CODE (NOW AFTER Connection::init()) ---
try {
    Logger::log("DIAGNOSTIC: Attempting to get Capsule instance...");
    $capsuleInstance = Connection::getCapsule();

    if ($capsuleInstance === null) {
        Logger::log("DIAGNOSTIC_ERROR: Connection::getCapsule() returned NULL. Capsule was not properly initialized.");
        // This is the root cause if this log appears after successful APP_INIT.
    } else {
        Logger::log("DIAGNOSTIC: Connection::getCapsule() returned a non-NULL instance. Type: " . get_class($capsuleInstance));

        Logger::log("DIAGNOSTIC: Attempting to get Connection object...");
        $connectionObject = $capsuleInstance->getConnection(); // This is line 19 or similar
        Logger::log("DIAGNOSTIC: getConnection() returned a Connection object. Type: " . get_class($connectionObject));

        Logger::log("DIAGNOSTIC: Attempting to get PDO object...");
        $pdo = $connectionObject->getPdo();
        Logger::log("DIAGNOSTIC: getPdo() returned a PDO object. Type: " . get_class($pdo));

        if ($pdo->inTransaction()) {
            Logger::log("DIAGNOSTIC: PDO transaction is ALREADY ACTIVE before routing.");
            // If this log appears, it means something else is starting a transaction.
            // Consider if $pdo->rollBack(); is appropriate here.
        } else {
            Logger::log("DIAGNOSTIC: PDO transaction is NOT active before routing.");
        }
    }
} catch (\Exception $e) {
    // This catch block will now specifically log errors from the diagnostic steps themselves
    Logger::log("DIAGNOSTIC_ERROR: An error occurred during PDO transaction status check: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
    // The previous error trace pinpointed line 19. This detailed log will help confirm.
}
// --- END DIAGNOSTIC CODE ---


// 4. Get URL for routing
$url = $_GET['url'] ?? '';
if (empty($url)) {
    $url = $_SERVER['REQUEST_URI'] ?? '/';
    $url = str_replace('/index.php', '', $url);
    $url = ltrim($url, '/');
    $url = strtok($url, '?');
}
$url = $url ?: '';

Logger::log("📥 Incoming request: " . $url);

try {
    // 5. Instantiate and dispatch the router
    $router = new Router();
    $router->route($url);

    Logger::log("ROUTING (index.php): Dispatched to $url");

} catch (\Throwable $e) {
    Logger::log(message: "❌ FATAL EXCEPTION/ERROR: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
    http_response_code(500);
    echo "<h1>Internal Server Error</h1>";
    echo "<p>An unexpected error occurred. Please check the logs for details.</p>";
}

?>