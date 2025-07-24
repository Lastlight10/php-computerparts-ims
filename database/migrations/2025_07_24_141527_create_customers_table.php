<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

// Assuming Logger is available globally or via a use statement if in a namespace
// use YourApp\Core\Logger;

function up()
{
    $tableName = 'customers';

    try {
        // Check if the table exists
        if (!Capsule::schema()->hasTable($tableName)) {
            echo "Creating '$tableName' table..." . PHP_EOL;

            Capsule::schema()->create($tableName, function (Blueprint $table) {
                $table->id(); // Auto-incrementing primary key

                // Customer Type
                $table->enum('customer_type', ['Individual', 'Company'])->default('Individual')->nullable(false);

                // Company Name (nullable if customer_type is 'Individual')
                $table->string('company_name', 255)->nullable();

                // Contact Person Names (can be individual's name or primary contact for company)
                $table->string('contact_person_first_name', 100)->nullable();
                $table->string('contact_person_last_name', 100)->nullable();

                // Contact Information
                $table->string('email', 255)->nullable();
                $table->string('phone_number', 50)->nullable();

                // Address
                $table->string('address', 255)->nullable();

                // Timestamps (created_at and updated_at)
                $table->timestamps();
            });

            echo "Table '$tableName' created successfully!" . PHP_EOL;

        } else {
            echo "Modifying '$tableName' table (adding missing columns if any)..." . PHP_EOL;

            Capsule::schema()->table($tableName, function (Blueprint $table) use ($tableName) {
                // Check and add columns if they don't exist
                if (!Capsule::schema()->hasColumn($tableName, 'customer_type')) {
                    $table->enum('customer_type', ['Individual', 'Company'])->default('Individual')->nullable(false)->after('id');
                }
                if (!Capsule::schema()->hasColumn($tableName, 'company_name')) {
                    $table->string('company_name', 255)->nullable()->after('customer_type');
                }
                if (!Capsule::schema()->hasColumn($tableName, 'contact_person_first_name')) {
                    $table->string('contact_person_first_name', 100)->nullable()->after('company_name');
                }
                if (!Capsule::schema()->hasColumn($tableName, 'contact_person_last_name')) {
                    $table->string('contact_person_last_name', 100)->nullable()->after('contact_person_first_name');
                }
                if (!Capsule::schema()->hasColumn($tableName, 'email')) {
                    $table->string('email', 255)->nullable()->after('contact_person_last_name');
                }
                if (!Capsule::schema()->hasColumn($tableName, 'phone_number')) {
                    $table->string('phone_number', 50)->nullable()->after('email');
                }
                if (!Capsule::schema()->hasColumn($tableName, 'address')) {
                    $table->string('address', 255)->nullable()->after('phone_number');
                }
                // Timestamps are assumed to be added on initial creation or managed separately.
            });

            echo "Table '$tableName' modified successfully!" . PHP_EOL;
        }

    } catch (\Exception $e) {
        $errorMessage = "Error updating '$tableName' table: " . $e->getMessage();
        echo $errorMessage . PHP_EOL;
        // Log the error using your Logger class if it's properly set up
        if (function_exists('Logger::log')) {
            Logger::log("MIGRATION FAILED: " . $errorMessage);
        } elseif (class_exists('Logger') && method_exists('Logger', 'log')) {
            Logger::log("MIGRATION FAILED: " . $errorMessage);
        } else {
            error_log("MIGRATION FAILED: " . $errorMessage); // Fallback to PHP error log
        }
        throw $e; // Re-throw the exception to stop the migration process
    }
}