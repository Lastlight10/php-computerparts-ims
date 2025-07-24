<?php
use Illuminate\Database\Capsule\Manager as Capsule;

require_once 'core/Logger.php';


try {
    // Load Composer's autoloader
    $autoloadPath = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        throw new Exception("ERROR: Composer autoloader not found at " . $autoloadPath);
    }
    require_once $autoloadPath;
    echo "Composer autoloader loaded." . PHP_EOL;

    //Load ENV file here
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . ''); // adjust path
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
    $databasePath = realpath('./database.sqlite');

    if (!$databasePath || !file_exists($databasePath)) {
        throw new Exception("SQLite database file not found at expected location: " . $databasePath);
    }

    $capsule->addConnection([
        'driver'   => 'sqlite',
        'database' => $databasePath, // absolute path
        'prefix'   => '',
    ]);
    
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    // ✅ Actual test query to check DB connection
    Capsule::select('SELECT 1');

    echo "✅ Database connection is successful and Eloquent ORM is booted." . PHP_EOL;
    Logger::log("✅ DATABASE SETUP SUCCESS: Connection and Eloquent booted.");

} catch (\Exception $e) {
    $errorMessage = "❌ DATABASE SETUP FAILED: " . $e->getMessage();
    echo $errorMessage . PHP_EOL;
    Logger::log("❌ CONNECTION ERROR: $errorMessage");
    exit(1);
}
?>
