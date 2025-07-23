<?php
use Illuminate\Database\Capsule\Manager as Capsule;

// core/Connection.php

// 1. Include the Logger class first, so it's available for error logging
// Adjust this path if your Logger.php is located elsewhere relative to this file
require 'corepp/Logger.php'; // Assuming 'app/Logger.php' from project root


// Enable error reporting for debugging (optional, but good for development)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Attempting to set up database connection..." . PHP_EOL;

try {
    // Load Composer's autoloader
    $autoloadPath = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        throw new Exception("ERROR: Composer autoloader not found at " . $autoloadPath);
    }
    require_once $autoloadPath;
    echo "Composer autoloader loaded." . PHP_EOL;

    $capsule = new Capsule;

    $capsule->addConnection([
        'driver'    => 'mysql',
        'host'      => 'your_db_host',      // Replace with your InfinityFree DB Host
        'database'  => 'your_db_name',      // Replace with your InfinityFree DB Name
        'username'  => 'your_db_user',      // Replace with your InfinityFree DB Username
        'password'  => 'your_db_password',  // Replace with your InfinityFree DB Password
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix'    => '',
    ]);

    // Set the event dispatcher used by Eloquent models (optional)
    // use Illuminate\Events\Dispatcher;
    // use Illuminate\Container\Container;
    // $capsule->setEventDispatcher(new Dispatcher(new Container));

    // Make this Capsule instance available globally via static methods...
    $capsule->setAsGlobal();

    // Setup the Eloquent ORM... (this is important!)
    $capsule->bootEloquent();

    echo "Database connection and Eloquent ORM successfully set up." . PHP_EOL;
    Logger::log("DATABASE SETUP SUCCESS: Connection and Eloquent booted.");

} catch (\Exception $e) {
    // Catch any exception that occurs during the database setup process
    $errorMessage = "DATABASE SETUP FAILED: " . $e->getMessage();
    echo $errorMessage . PHP_EOL;
    Logger::log("CONNECTION ERROR: $errorMessage");

    // Depending on your application's needs, you might want to:
    // 1. Just log and display the error (as above).
    // 2. Exit the script, as the application cannot function without a DB connection.
    exit(1); // Exit with an error code
}

// You can now access the database through Capsule or your Eloquent Models.
// For example:
// $users = Capsule::table('users')->get();
// print_r($users);
