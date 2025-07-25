<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// 2. Load Composer's autoloader FIRST
// This loads all your namespaced classes (Controllers, Models, App\Core)
require_once __DIR__ . '/vendor/autoload.php';

// 3. Remove the custom autoloader - it's conflicting with Composer
// spl_autoload_register(function ($class) {
//     foreach (['controllers', 'models'] as $folder) {
//         $path = "$folder/$class.php";
//         if (file_exists($path)) require $path;
//     }
// });

// 4. Start the session (if not already done)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 5. Import necessary classes using their namespaces
// These 'use' statements make it so you don't have to write \App\Core\Router everywhere
use App\Core\Router;
use App\Core\Logger;
use App\Core\Connection; // <--- YOU ARE MISSING THIS! You need to call Connection::init()
// use App\Core\Controller; // You typically don't 'use' the base Controller class here in index.php unless you instantiate it directly


// 6. Initialize the database connection
// This call is crucial for your application to connect to the database
try {
    Connection::init();
    Logger::log("DB_INFO: Database connection initialized successfully in index.php.");
} catch (\Exception $e) {
    Logger::log("DB_FATAL_ERROR: Could not initialize database connection in index.php: " . $e->getMessage());
    http_response_code(500);
    echo "<h1>Error: Database Connection Failed</h1>";
    echo "<p>Please check your configuration and logs.</p>";
    exit(1); // Exit if the database connection fails, as the app won't work
}


// 7. Get URL for routing
$url = $_GET['url'] ?? ''; // This seems like an old way of handling URLs (e.g., index.php?url=...)
if (empty($url)) {
    $url = $_SERVER['REQUEST_URI'] ?? '/';
    $url = str_replace('/index.php', '', $url); // Remove /index.php if it's in the URL
    $url = ltrim($url, '/'); // Remove leading slash
    $url = strtok($url, '?'); // Remove query string (e.g., ?success=true)
}

// Fallback for empty URL or root path
// Your Router currently handles '/' by itself, so 'login/login' might be redundant or conflicting
// If your router handles '/' -> LoginController@login, then $url = $url ?: 'login'; might be enough.
// Let's stick with a simple empty fallback for now.
$url = $url ?: ''; // This will be an empty string for the root URL '/'


Logger::log("📥 Incoming request: " . $url);

try {
    // 8. Instantiate and dispatch the router
    $router = new Router();
    // Your Router's route method now expects just the $url, not the HTTP method as a separate arg
    // However, your Router.php code snippet only showed public function route($url), not $url, $method
    // If your router's route() method expects the HTTP method, you need to pass it:
    // $router->route($url, $_SERVER['REQUEST_METHOD']);
    $router->route($url); // Assuming route() only takes $url

    Logger::log("ROUTING (index.php): Dispatched to $url");

} catch (\Throwable $e) { // Use \Throwable to catch both Errors and Exceptions
    Logger::log(message: "❌ FATAL EXCEPTION/ERROR: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
    http_response_code(500);
    echo "<h1>Internal Server Error</h1>";
    echo "<p>An unexpected error occurred. Please check the logs for details.</p>";
    // Optionally, for development, display full error:
    // echo "<pre>" . $e->getMessage() . "\n" . $e->getTraceAsString() . "</pre>";
}