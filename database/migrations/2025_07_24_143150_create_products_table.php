Okay, here's the migration script for your `products` table, including all the detailed column definitions and foreign key constraints.

Remember that `categories` and `brands` tables **must be migrated before** this `products` table because `products` depends on them via foreign keys.

```php
<?php

// 2025_07_25_XXXXXX_create_products_table.php (Adjust timestamp in filename)

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

// Assuming Logger is available globally or via a use statement if in a namespace
// use YourApp\Core\Logger;

function up()
{
    $tableName = 'products';

    try {
        if (!Capsule::schema()->hasTable($tableName)) {
            echo "Creating '$tableName' table..." . PHP_EOL;

            Capsule::schema()->create($tableName, function (Blueprint $table) {
                $table->id(); // Primary Key, INT, Auto-increment

                // Stock Keeping Unit (Your internal unique identifier)
                $table->string('sku', 100)->unique()->nullable(false);

                // Product Name and Description
                $table->string('name', 255)->nullable(false);
                $table->text('description')->nullable();

                // Foreign Keys
                // Using unsignedBigInteger to match the type created by $table->id()
                $table->unsignedBigInteger('category_id')->nullable(false);
                $table->foreign('category_id')->references('id')->on('categories');

                $table->unsignedBigInteger('brand_id')->nullable(false);
                $table->foreign('brand_id')->references('id')->on('brands');

                // Pricing
                $table->decimal('unit_price', 10, 2)->nullable(false); // Current selling price
                $table->decimal('cost_price', 10, 2)->nullable();     // Last known purchase cost

                // Inventory Levels
                $table->integer('current_stock')->nullable(false)->default(0); // Total quantity currently in inventory
                $table->integer('reorder_level')->nullable(false)->default(0); // Minimum quantity to trigger reorder

                // Product Tracking Flags
                $table->boolean('is_serialized')->nullable(false)->default(false); // TRUE if each unit has a serial number
                $table->boolean('is_active')->nullable(false)->default(true);     // Whether the product is available for sale

                // Physical Storage Location
                $table->string('location_aisle', 50)->nullable();
                $table->string('location_bin', 50)->nullable();

                // Timestamps
                $table->timestamps();
            });

            echo "Table '$tableName' created successfully!" . PHP_EOL;
        } else {
            echo "Modifying '$tableName' table (adding missing columns if any)..." . PHP_EOL;

            Capsule::schema()->table($tableName, function (Blueprint $table) use ($tableName) {
                // Add columns if they don't exist, preserving order with ->after()
                if (!Capsule::schema()->hasColumn($tableName, 'sku')) {
                    $table->string('sku', 100)->unique()->nullable(false)->after('id');
                    echo " - Added 'sku' column." . PHP_EOL;
                }
                if (!Capsule::schema()->hasColumn($tableName, 'name')) {
                    $table->string('name', 255)->nullable(false)->after('sku');
                    echo " - Added 'name' column." . PHP_EOL;
                }
                if (!Capsule::schema()->hasColumn($tableName, 'description')) {
                    $table->text('description')->nullable()->after('name');
                    echo " - Added 'description' column." . PHP_EOL;
                }

                // Add foreign keys. Note: Referenced tables must exist BEFORE adding FKs.
                if (!Capsule::schema()->hasColumn($tableName, 'category_id')) {
                    $table->unsignedBigInteger('category_id')->nullable(false)->after('description');
                    $table->foreign('category_id')->references('id')->on('categories');
                    echo " - Added 'category_id' column and foreign key." . PHP_EOL;
                }
                if (!Capsule::schema()->hasColumn($tableName, 'brand_id')) {
                    $table->unsignedBigInteger('brand_id')->nullable(false)->after('category_id');
                    $table->foreign('brand_id')->references('id')->on('brands');
                    echo " - Added 'brand_id' column and foreign key." . PHP_EOL;
                }

                if (!Capsule::schema()->hasColumn($tableName, 'unit_price')) {
                    $table->decimal('unit_price', 10, 2)->nullable(false)->after('brand_id');
                    echo " - Added 'unit_price' column." . PHP_EOL;
                }
                if (!Capsule::schema()->hasColumn($tableName, 'cost_price')) {
                    $table->decimal('cost_price', 10, 2)->nullable()->after('unit_price');
                    echo " - Added 'cost_price' column." . PHP_EOL;
                }
                if (!Capsule::schema()->hasColumn($tableName, 'current_stock')) {
                    $table->integer('current_stock')->nullable(false)->default(0)->after('cost_price');
                    echo " - Added 'current_stock' column." . PHP_EOL;
                }
                if (!Capsule::schema()->hasColumn($tableName, 'reorder_level')) {
                    $table->integer('reorder_level')->nullable(false)->default(0)->after('current_stock');
                    echo " - Added 'reorder_level' column." . PHP_EOL;
                }
                if (!Capsule::schema()->hasColumn($tableName, 'is_serialized')) {
                    $table->boolean('is_serialized')->nullable(false)->default(false)->after('reorder_level');
                    echo " - Added 'is_serialized' column." . PHP_EOL;
                }
                if (!Capsule::schema()->hasColumn($tableName, 'is_active')) {
                    $table->boolean('is_active')->nullable(false)->default(true)->after('is_serialized');
                    echo " - Added 'is_active' column." . PHP_EOL;
                }
                if (!Capsule::schema()->hasColumn($tableName, 'location_aisle')) {
                    $table->string('location_aisle', 50)->nullable()->after('is_active');
                    echo " - Added 'location_aisle' column." . PHP_EOL;
                }
                if (!Capsule::schema()->hasColumn($tableName, 'location_bin')) {
                    $table->string('location_bin', 50)->nullable()->after('location_aisle');
                    echo " - Added 'location_bin' column." . PHP_EOL;
                }
                // Timestamps are assumed to be added on initial creation or managed separately.
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

function down()
{
    $tableName = 'products';

    try {
        echo "Dropping '$tableName' table..." . PHP_EOL;
        // Drop foreign key constraints before dropping the table if necessary,
        // although dropIfExists usually handles this by dropping the table entirely.
        Capsule::schema()->dropIfExists($tableName);
        echo "Table '$tableName' dropped successfully!" . PHP_EOL;
        if (function_exists('Logger::log')) {
            Logger::log("MIGRATION SUCCESS: Dropped '$tableName' table.");
        } elseif (class_exists('Logger') && method_exists('Logger', 'log')) {
            Logger::log("MIGRATION SUCCESS: Dropped '$tableName' table.");
        } else {
            error_log("MIGRATION SUCCESS: Dropped '$tableName' table.");
        }

    } catch (\Exception $e) {
        $errorMessage = "Error dropping '$tableName' table: " . $e->getMessage();
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
