<?php

// 2025_07_24_072327_add_data_sqlite.php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Models\User;

use Illuminate\Database\Eloquent\Model;
// IMPORTANT: Make sure your Logger class is available here.
// If it's autoloaded by Composer (recommended), you don't need a direct require_once here.
// If not, you might need: require_once __DIR__ . '/../app/Logger.php'; // Adjust path


function up()
{
    try {

        echo "Attempting to table 'data_sqlite' table..." . PHP_EOL;


        Capsule::schema()->create('users', function (Blueprint $table) {
            // Add your table columns here
            // This is a placeholder. You'll fill this in after generation.
            $table->id(); // Example for 'create' operation
            $table->string('username', 50);
            $table->string('email',50);
            $table->string('password');
            $table->string('last_name', 50);
            $table->string('first_name', 50);
            $table->string('middle_name', 50);
            $table->date('birthdate');
            $table->timestamps(); // Example for 'create' operation
        });

        User::create([
            'username'=> 'admin',
            'email'=> 'recon21342@gmail.com',
            'password'=> 'admin12345',
            'first_name'=> 'Super',
            'last_name'=> 'Admin',
            'middle_name'=>'User',
            'birthdate'=> '2000-01-01',
        ]);
    
        echo "'data_sqlite' table tabled successfully!" . PHP_EOL;
        Logger::log("MIGRATION SUCCESS: tabled 'data_sqlite' table.");

    } catch (\Exception $e) { // Catch any general PHP Exception
        // Log the detailed error message
        $errorMessage = "Error tableing 'data_sqlite' table: " . $e->getMessage();
        echo $errorMessage . PHP_EOL;
        Logger::log("MIGRATION FAILED: " . $errorMessage);
        // It's good practice to re-throw the exception so the runner script knows it failed,
        // and doesn't incorrectly report overall success.
        throw $e;
    }
}

function down()
{
    try {
        echo "Attempting to drop 'data_sqlite' table..." . PHP_EOL;
        Capsule::schema()->dropIfExists('data_sqlite');
        echo "'data_sqlite' table dropped successfully!" . PHP_EOL;
        Logger::log("MIGRATION SUCCESS: Dropped 'data_sqlite' table.");

    } catch (\Exception $e) {
        $errorMessage = "Error dropping 'data_sqlite' table: " . $e->getMessage();
        echo $errorMessage . PHP_EOL;
        Logger::log("MIGRATION FAILED: " . $errorMessage);
        throw $e;
    }
}