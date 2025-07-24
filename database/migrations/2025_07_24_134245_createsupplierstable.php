<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

// Assuming Logger is available globally or via a use statement if in a namespace
// use YourApp\Core\Logger;

function up()
{
    $tableName = 'suppliers';

    try {
        if (!Capsule::schema()->hasTable($tableName)) {
            echo "Creating '$tableName' table..." . PHP_EOL;

            Capsule::schema()->create($tableName, function (Blueprint $table) {
                $table->id(); // Auto-incrementing primary key

                // Supplier Type
                $table->enum('supplier_type', ['Individual', 'Company'])->default('Company')->nullable(false);

                // Company Name (adjusted length)
                $table->string('company_name', 100)->nullable();

                // Contact Person Names (new structure)
                $table->string('contact_first_name', 100)->nullable();
                $table->string('contact_middle_name', 100)->nullable(); // New column
                $table->string('contact_last_name', 100)->nullable(); // Renamed/Adjusted

                // Contact Information
                $table->string('email', 255)->nullable();
                $table->string('phone_number', 50)->nullable();

                // Consolidated Address
                $table->string('address', 255)->nullable(); // Consolidated from multiple address fields

                // Timestamps (created_at and updated_at)
                $table->timestamps();
            });

            echo "Table '$tableName' created successfully!" . PHP_EOL;

        } else {
            echo "Modifying '$tableName' table to new schema..." . PHP_EOL;

            Capsule::schema()->table($tableName, function (Blueprint $table) use ($tableName) {

                // 1. Rename existing contact columns if they exist
                if (Capsule::schema()->hasColumn($tableName, 'contact_person_first_name')) {
                    $table->renameColumn('contact_person_first_name', 'contact_first_name');
                    echo " - Renamed 'contact_person_first_name' to 'contact_first_name'." . PHP_EOL;
                }
                if (Capsule::schema()->hasColumn($tableName, 'contact_person_last_name')) {
                    $table->renameColumn('contact_person_last_name', 'contact_last_name');
                    echo " - Renamed 'contact_person_last_name' to 'contact_last_name'." . PHP_EOL;
                }

                // 2. Add new contact columns if they don't exist
                if (!Capsule::schema()->hasColumn($tableName, 'contact_middle_name')) {
                    $table->string('contact_middle_name', 100)->nullable()->after('contact_first_name');
                    echo " - Added 'contact_middle_name'." . PHP_EOL;
                }
                // Ensure the renamed columns (contact_first_name, contact_last_name) also have the correct length if they were just added and not renamed.
                // If they were renamed from longer VARCHARs, they would retain their old length unless explicitly changed.
                // Using ->change() for string length requires 'doctrine/dbal'.
                if (Capsule::schema()->hasColumn($tableName, 'company_name')) {
                    // This attempts to change the length if it's different.
                    // Requires 'doctrine/dbal'
                    $table->string('company_name', 100)->nullable()->change();
                    echo " - Adjusted 'company_name' length to 100." . PHP_EOL;
                }
                if (Capsule::schema()->hasColumn($tableName, 'contact_first_name')) {
                     $table->string('contact_first_name', 100)->nullable()->change();
                }
                 if (Capsule::schema()->hasColumn($tableName, 'contact_last_name')) {
                     $table->string('contact_last_name', 100)->nullable()->change();
                }


                // 3. Drop old address columns if they exist
                $oldAddressColumns = [
                    'address_street',
                    'address_city',
                    'address_state_province',
                    'address_zip_code'
                ];
                foreach ($oldAddressColumns as $col) {
                    if (Capsule::schema()->hasColumn($tableName, $col)) {
                        $table->dropColumn($col);
                        echo " - Dropped old address column: '$col'." . PHP_EOL;
                    }
                }

                // 4. Add the new consolidated address column if it doesn't exist
                if (!Capsule::schema()->hasColumn($tableName, 'address')) {
                    // Attempt to place it after phone_number, if phone_number already exists.
                    if (Capsule::schema()->hasColumn($tableName, 'phone_number')) {
                        $table->string('address', 255)->nullable()->after('phone_number');
                    } else {
                        $table->string('address', 255)->nullable(); // Add at end if phone_number doesn't exist
                    }
                    echo " - Added 'address' (consolidated)." . PHP_EOL;
                }

                // 5. Drop the 'notes' column if it exists
                if (Capsule::schema()->hasColumn($tableName, 'notes')) {
                    $table->dropColumn('notes');
                    echo " - Dropped 'notes' column." . PHP_EOL;
                }
                // Handle supplier_type, email, phone_number if they somehow don't exist
                // The 'create' block ensures they are added initially. The 'modify' is for structural changes.
                // Assuming supplier_type, email, phone_number always exist from initial create or previous migration steps.
            });

            echo "Table '$tableName' modified successfully!" . PHP_EOL;
        }

    } catch (\Exception $e) {
        $errorMessage = "Error updating '$tableName' table: " . $e->getMessage();
        echo $errorMessage . PHP_EOL;
        if (function_exists('Logger::log')) {
            Logger::log("MIGRATION FAILED: " . $errorMessage);
        } elseif (class_exists('Logger') && method_exists('Logger', 'log')) {
            Logger::log("MIGRATION FAILED: " . $errorMessage);
        } else {
            error_log("MIGRATION FAILED: " . $errorMessage);
        }
        throw $e;
    }
}