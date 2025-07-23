<?php
require 'core/Router.php';
require 'core/Controller.php';
require 'core/Logger.php';

spl_autoload_register(function ($class) {
    foreach (['controllers', 'models'] as $folder) {
        $path = "$folder/$class.php";
        if (file_exists($path)) require $path;
    }
});
Logger::log(message: "📥 Incoming request: " . ($_GET['url'] ?? 'N/A'));
try {
    $router = new Router();
    $router->route($_GET['url'] ?? 'login/login');
    Logger::log("ROUTING (index.php): To login/login");
} catch (Throwable $e) {
    Logger::log("❌ Exception: " . $e->getMessage());
    http_response_code(500);
    echo "Internal Server Error";
}

