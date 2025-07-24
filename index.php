<?php
require_once 'core/Router.php';
require_once 'core/Controller.php';
require_once 'core/Logger.php';
require_once 'vendor/autoload.php'; 

spl_autoload_register(function ($class) {
    foreach (['controllers', 'models'] as $folder) {
        $path = "$folder/$class.php";
        if (file_exists($path)) require $path;
    }
});

// Get URL from either GET parameter or REQUEST_URI
$url = $_GET['url'] ?? '';
if (empty($url)) {
    // Fallback to REQUEST_URI method
    $url = $_SERVER['REQUEST_URI'] ?? '/';
    $url = str_replace('/index.php', '', $url);
    $url = ltrim($url, '/');
    $url = strtok($url, '?'); // Remove query string
}

$url = $url ?: 'login/login'; // Default fallback

Logger::log("📥 Incoming request: " . $url);
try {
    $router = new Router();
    $router->route($url);
    Logger::log("ROUTING (index.php): To $url");
} catch (Throwable $e) {
    Logger::log("❌ Exception: " . $e->getMessage());
    http_response_code(500);
    echo "Internal Server Error";
}