<?php

namespace App\Core; // <-- Define the namespace for this class

use Illuminate\Database\Capsule\Manager as Capsule;
use Dotenv\Dotenv; // Use the Dotenv class directly
use Illuminate\Database\Capsule\Manager as DB; // Ensure this is aliased as DB
// IMPORTANT: Remove require_once 'core/Logger.php'; here
// because Logger is now also namespaced and will be autoloaded.
// Also, remove require_once 'vendor/autoload.php'; if it's already in your index.php.

class Connection
{
    /**
     * @var Capsule|null The Eloquent Capsule instance.
     */
    protected static ?Capsule $capsule = null;

    /**
     * Initializes the database connection and Eloquent ORM.
     * This method should be called once at the application's bootstrap.
     *
     * @return void
     * @throws \Exception If database setup fails.
     */
    public static function init(): void
    {
        
        // Prevent multiple initializations
        if (static::$capsule !== null) {
            Logger::log("DB_INFO: Database connection already initialized. Skipping.");
            return;
        }

        // Enable error reporting for debugging (optional, but good for development)
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        try {
            Logger::log("DB_INFO: Attempting to set up database connection...");

            // Load Composer's autoloader (ensure this is done once at the application entry point)
            // If vendor/autoload.php is already required in index.php, you can remove this block.
            $autoloadPath = __DIR__ . '/../vendor/autoload.php'; // Adjust path relative to Connection.php
            if (!file_exists($autoloadPath)) {
                throw new \Exception("Composer autoloader not found at " . $autoloadPath);
            }
            require_once $autoloadPath;
            Logger::log("DB_INFO: Composer autoloader loaded (if not already).");

            // Load ENV file here
            // Adjust path to your .env file relative to the project root
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
            $dotenv->load();
            Logger::log("DB_INFO: .env file loaded.");

            static::$capsule = new Capsule;

            // Use SQLite connection as per your current setup
            static::$capsule->addConnection([
                'driver'   => 'sqlite',
                'database' => __DIR__ . '/../database.sqlite', // Path to your sqlite file
                'prefix'   => '',
            ]);

            // Set the event dispatcher used by Eloquent models (optional)
            // use Illuminate\Events\Dispatcher;
            // use Illuminate\Container\Container;
            // static::$capsule->setEventDispatcher(new Dispatcher(new Container));

            // Make this Capsule instance available globally via static methods...
            static::$capsule->setAsGlobal();

            // Setup the Eloquent ORM... (this is important!)
            static::$capsule->bootEloquent();

            Logger::log("DB_INFO: Database connection and Eloquent ORM successfully set up.");

        } catch (\Exception $e) {
            $errorMessage = "DATABASE SETUP FAILED: " . $e->getMessage();
            echo $errorMessage . PHP_EOL; // Display error for immediate feedback
            Logger::log("DB_ERROR: $errorMessage");
            exit(1); // Exit with an error code if DB connection is critical
        }
    }

    /**
     * Get the Eloquent Capsule instance.
     * @return Capsule|null
     */
    public static function getCapsule(): ?Capsule
    {
        return static::$capsule;
    }
}