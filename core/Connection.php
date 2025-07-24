<?php
use Illuminate\Database\Capsule\Manager as Capsule;

// core/Connection.php
require_once 'core/Logger.php';
// 1. Include the Logger class first, so it's available for error logging


// Enable error reporting for debugging (optional, but good for development)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);



try {
    Logger::log( "Attempting to set up database connection..." . PHP_EOL);
    // Load Composer's autoloader
    $autoloadPath = 'vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        throw new Exception("ERROR: Composer autoloader not found at " . $autoloadPath);
    }
    require_once $autoloadPath;
    Logger::log("Composer autoloader loaded." . PHP_EOL);

    //Load ENV file here
    $dotenv = Dotenv\Dotenv::createImmutable('./'); // adjust path
    $dotenv->load();

    $capsule = new Capsule;
    /* temporarily
    $capsule->addConnection([
        'driver'    => 'mysql',
        'host'      => $_ENV['DB_HOST'],
        'database'  => $_ENV['DB_NAME'],
        'username'  => $_ENV['DB_USER'],
        'password'  => $_ENV['DB_PASS'],
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix'    => '',
    ]);
    */
    $capsule->addConnection([
        'driver'   => 'sqlite',
        'database' => __DIR__ . '/../database.sqlite',
        'prefix'   => '',
    ]);

    // Set the event dispatcher used by Eloquent models (optional)
    // use Illuminate\Events\Dispatcher;
    // use Illuminate\Container\Container;
    // $capsule->setEventDispatcher(new Dispatcher(new Container));

    // Make this Capsule instance available globally via static methods...
    $capsule->setAsGlobal();

    // Setup the Eloquent ORM... (this is important!)
    $capsule->bootEloquent();

    Logger::log("Database connection and Eloquent ORM successfully set up." . PHP_EOL);

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
