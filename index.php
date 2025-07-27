<?php
// htdocs/index.php

// Set up error reporting for debugging.
// IMPORTANT: Turn display_errors OFF and error_reporting to 0 in production!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
use App\Core\Connection;
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Adjust path to Composer's autoloader based on your actual project structure.
// If your vendor folder is one level above htdocs (recommended for security):
require_once __DIR__ . '/vendor/autoload.php';
// If your vendor folder is inside htdocs (less secure, but sometimes necessary):
// require_once __DIR__ . '/vendor/autoload.php';

// Adjust path to your App/Core/Connection.php based on your actual project structure.
// If App/Core/ is one level above htdocs:

// If App/Core/ is inside htdocs:
// require_once __DIR__ . '/App/Core/Connection.php';


// Initialize database connection
\App\Core\Connection::init();

use App\Core\Router;
use App\Core\Logger;
use Illuminate\Database\Capsule\Manager as DB; // Add this if you want to use DB facade for diagnostic

// --- MORE VERBOSE DIAGNOSTIC CODE (NOW AFTER Connection::init()) ---
// This block is for diagnosing database connection, not URL parsing.
try {
    Logger::log("DIAGNOSTIC: Attempting to get Capsule instance...");
    $capsuleInstance = Connection::getCapsule();

    if ($capsuleInstance === null) {
        Logger::log("DIAGNOSTIC_ERROR: Connection::getCapsule() returned NULL. Capsule was not properly initialized.");
    } else {
        Logger::log("DIAGNOSTIC: Connection::getCapsule() returned a non-NULL instance. Type: " . get_class($capsuleInstance));

        Logger::log("DIAGNOSTIC: Attempting to get Connection object...");
        $connectionObject = $capsuleInstance->getConnection();
        Logger::log("DIAGNOSTIC: getConnection() returned a Connection object. Type: " . get_class($connectionObject));

        Logger::log("DIAGNOSTIC: Attempting to get PDO object...");
        $pdo = $connectionObject->getPdo();
        Logger::log("DIAGNOSTIC: getPdo() returned a PDO object. Type: " . get_class($pdo));

        if ($pdo->inTransaction()) {
            Logger::log("DIAGNOSTIC: PDO transaction is ALREADY ACTIVE before routing.");
        } else {
            Logger::log("DIAGNOSTIC: PDO transaction is NOT active before routing.");
        }
    }
} catch (\Exception $e) {
    Logger::log("DIAGNOSTIC_ERROR: An error occurred during PDO transaction status check: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
}
// --- END DIAGNOSTIC CODE ---


// 4. Get URL for routing - REFINED LOGIC
$url = '';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

Logger::log("URL_DEBUG: Raw REQUEST_URI: " . $requestUri);
Logger::log("URL_DEBUG: Raw _GET array: " . json_encode($_GET));

// Priority 1: Check if .htaccess passed a 'url' GET parameter (clean URL)
if (isset($_GET['url'])) {
    $url = $_GET['url'];
    Logger::log("URL_DEBUG: URL obtained from \$_GET['url']: " . $url);
} else {
    // Priority 2: If not, parse from REQUEST_URI.
    // This handles cases where .htaccess might not be active or direct access.

    // Remove query string from REQUEST_URI
    $path = strtok($requestUri, '?');
    Logger::log("URL_DEBUG: Path after strtok: " . $path);

    // Remove /index.php if it's explicitly in the path (e.g., /index.php/route)
    // This is important for direct access without .htaccess or if .htaccess is configured differently.
    $path = str_replace('/index.php', '', $path);
    Logger::log("URL_DEBUG: Path after /index.php removal: " . $path);

    // Trim leading/trailing slashes
    $url = trim($path, '/');
    Logger::log("URL_DEBUG: Final URL after trimming: " . $url);
}

// Ensure $url is never empty, default to homepage route
$url = $url ?: '';

Logger::log("📥 Incoming request parsed as: " . ($url ?: 'homepage'));

try {
    // 5. Instantiate and dispatch the router
    $router = new Router();
    $router->route($url);

    Logger::log("ROUTING (index.php): Dispatched to " . ($url ?: 'homepage'));

} catch (\Throwable $e) {
    Logger::log(message: "❌ FATAL EXCEPTION/ERROR: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
    http_response_code(500);
    echo "<h1>Internal Server Error</h1>";
    echo "<p>An unexpected error occurred. Please check the logs for details.</p>";
}

?>
