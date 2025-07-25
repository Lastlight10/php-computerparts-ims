<?php
namespace Controllers;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Connection;

use Models\Product;
use Models\Category; // Added for product forms
use Models\Brand;    // Added for product forms
// If you implement deletion logic that affects instances/transaction items,
// you might need those models here too, e.g., use Models\ProductInstance;


require_once 'vendor/autoload.php';


class StaffProductController extends Controller {

    // Assuming view() and input() are inherited from base Controller

    /**
     * Displays a single product's details, including its serialized instances.
     * Accessible via /staff/products/show/{id}
     *
     * @param int $id The ID of the product to show.
     * @return void
     */
    public function show($id) {
        Logger::log("PRODUCT_SHOW: Attempting to show product ID: $id");

        $product = Product::with([
            'category', // Eager load category name
            'brand',    // Eager load brand name
            'instances',
            'instances.purchaseItem.transaction', // Eager load transaction for purchase details
            'instances.saleItem.transaction'     // Eager load transaction for sale details
        ])->find($id);

        if (!$product) {
            Logger::log("PRODUCT_SHOW_FAILED: Product ID $id not found.");
            return $this->view('errors/404', ['message' => 'Product not found.'],'staff');
        }

        Logger::log("PRODUCT_SHOW_SUCCESS: Displaying product ID: $id - {$product->name}");
        $this->view('staff/products/show', ['product' => $product],'staff');
    }

    /**
     * Displays the form to add a new product.
     * Accessible via /staff/products/add
     *
     * @return void
     */
    public function add() {
        Logger::log('PRODUCT_ADD: Displaying new product form.');

        // Fetch categories and brands for dropdowns
        $categories = Category::all();
        $brands = Brand::all();

        $this->view('staff/products/add', [
            'categories' => $categories,
            'brands' => $brands,
            'product' => new Product() // Pass an empty Product model for form binding
        ],'staff');
    }

    /**
     * Handles the POST request to store a new product in the database.
     * Accessible via /staff/products/store
     *
     * @return void
     */
    public function store() {
        Logger::log('PRODUCT_STORE: Attempting to store new product.');

        // 1. Retrieve Input Data
        $sku             = $this->input('sku');
        $name            = $this->input('name');
        $description     = $this->input('description');
        $category_id     = $this->input('category_id');
        $brand_id        = $this->input('brand_id');
        $unit_price      = $this->input('unit_price');
        $cost_price      = $this->input('cost_price');
        $current_stock   = $this->input('current_stock');
        $reorder_level   = $this->input('reorder_level');
        $is_serialized   = $this->input('is_serialized') === 'on' ? true : false; // Checkbox value
        $is_active       = $this->input('is_active') === 'on' ? true : false;     // Checkbox value
        $location_aisle  = $this->input('location_aisle');
        $location_bin    = $this->input('location_bin');

        // 2. Validation
        $errors = [];

        if (empty($sku)) $errors[] = 'SKU is required.';
        else {
            $existingProduct = Product::where('sku', $sku)->first();
            if ($existingProduct) $errors[] = 'SKU must be unique.';
        }
        if (empty($name)) $errors[] = 'Product Name is required.';
        if (empty($category_id)) $errors[] = 'Category is required.';
        if (empty($brand_id)) $errors[] = 'Brand is required.';
        if (!is_numeric($unit_price) || $unit_price < 0) $errors[] = 'Unit Price must be a non-negative number.';
        if (!is_numeric($current_stock) || $current_stock < 0) $errors[] = 'Current Stock must be a non-negative integer.';
        if (!is_numeric($reorder_level) || $reorder_level < 0) $errors[] = 'Reorder Level must be a non-negative integer.';

        // Optional fields validation
        if (!empty($cost_price) && !is_numeric($cost_price) || $cost_price < 0) $errors[] = 'Cost Price must be a non-negative number if provided.';

        if (!empty($errors)) {
            Logger::log("PRODUCT_STORE_FAILED: Validation errors: " . implode(', ', $errors));
            // Re-fetch categories and brands to repopulate the form
            $categories = Category::all();
            $brands = Brand::all();
            // Pass back input data to re-populate form fields
            $this->view('staff/products/add', [
                'error' => implode('<br>', $errors),
                'categories' => $categories,
                'brands' => $brands,
                'product' => (object)[ // Create a dummy object to mimic Eloquent model for form repopulation
                    'sku' => $sku, 'name' => $name, 'description' => $description,
                    'category_id' => $category_id, 'brand_id' => $brand_id,
                    'unit_price' => $unit_price, 'cost_price' => $cost_price,
                    'current_stock' => $current_stock, 'reorder_level' => $reorder_level,
                    'is_serialized' => $is_serialized, 'is_active' => $is_active,
                    'location_aisle' => $location_aisle, 'location_bin' => $location_bin,
                ]
            ],'staff');
            return;
        }

        // 3. Create and Save Product
        try {
            $product = new Product();
            $product->sku = $sku;
            $product->name = $name;
            $product->description = $description;
            $product->category_id = $category_id;
            $product->brand_id = $brand_id;
            $product->unit_price = $unit_price;
            $product->cost_price = !empty($cost_price) ? $cost_price : null;
            $product->current_stock = $current_stock;
            $product->reorder_level = $reorder_level;
            $product->is_serialized = $is_serialized;
            $product->is_active = $is_active;
            $product->location_aisle = !empty($location_aisle) ? $location_aisle : null;
            $product->location_bin = !empty($location_bin) ? $location_bin : null;

            $product->save();

            Logger::log("PRODUCT_STORE_SUCCESS: New product '{$product->name}' (ID: {$product->id}) added successfully.");
            // Redirect to product list with success message
            // Assuming you have a products_list method that can display messages
            header('Location: /staff/products_list?success_message=' . urlencode('Product added successfully!'));
            exit();

        } catch (\Exception $e) {
            Logger::log("PRODUCT_STORE_DB_ERROR: Failed to add product - " . $e->getMessage());
            // Re-fetch categories and brands to repopulate the form
            $categories = Category::all();
            $brands = Brand::all();
            $this->view('staff/products/add', [
                'error' => 'An error occurred while adding the product. Please try again. ' . $e->getMessage(),
                'categories' => $categories,
                'brands' => $brands,
                'product' => (object)[ // Re-populate form with submitted data
                    'sku' => $sku, 'name' => $name, 'description' => $description,
                    'category_id' => $category_id, 'brand_id' => $brand_id,
                    'unit_price' => $unit_price, 'cost_price' => $cost_price,
                    'current_stock' => $current_stock, 'reorder_level' => $reorder_level,
                    'is_serialized' => $is_serialized, 'is_active' => $is_active,
                    'location_aisle' => $location_aisle, 'location_bin' => $location_bin,
                ]
            ],'staff');
            return;
        }
    }

    /**
     * Displays the form to edit an existing product.
     * Accessible via /staff/products/edit/{id}
     *
     * @param int $id The ID of the product to edit.
     * @return void
     */
    public function edit($id) {
        Logger::log("PRODUCT_EDIT: Attempting to display edit form for product ID: $id");

        $product = Product::find($id);

        if (!$product) {
            Logger::log("PRODUCT_EDIT_FAILED: Product ID $id not found for editing.");
            return $this->view('errors/404', ['message' => 'Product not found.'],'staff');
        }

        // Fetch categories and brands for dropdowns
        $categories = Category::all();
        $brands = Brand::all();

        Logger::log("PRODUCT_EDIT_SUCCESS: Displaying edit form for product ID: $id - {$product->name}");
        $this->view('staff/products/edit', [
            'product' => $product,
            'categories' => $categories,
            'brands' => $brands
        ],'staff');
    }

    /**
     * Handles the POST request to update an existing product in the database.
     * Accessible via /staff/products/update
     *
     * @return void
     */
    public function update() {
    Logger::log('PRODUCT_UPDATE: Attempting to update product.');

    // ... (Input Retrieval and Initial Product Find - these lines stay the same) ...
    $id              = $this->input('id');
    $sku             = $this->input('sku');
    $name            = $this->input('name');
    $description     = $this->input('description');
    $category_id     = $this->input('category_id');
    $brand_id        = $this->input('brand_id');
    $unit_price      = $this->input('unit_price');
    $cost_price      = $this->input('cost_price');
    $current_stock   = $this->input('current_stock');
    $reorder_level   = $this->input('reorder_level');
    $is_serialized   = $this->input('is_serialized') === 'on' ? true : false;
    $is_active       = $this->input('is_active') === 'on' ? true : false;
    $location_aisle  = $this->input('location_aisle');
    $location_bin    = $this->input('location_bin');

    $product = Product::find($id);

    if (!$product) {
        Logger::log("PRODUCT_UPDATE_FAILED: Product ID $id not found for update.");
        return $this->view('errors/404', ['message' => 'Product not found.'], 'default'); // Added 'default' layout
    }

    // 3. Validation
    $errors = [];

    // ... (Your existing validation rules here) ...
    if (empty($sku)) $errors[] = 'SKU is required.';
    else {
        $existingProduct = Product::where('sku', $sku)->where('id', '!=', $id)->first();
        if ($existingProduct) $errors[] = 'SKU must be unique.';
    }
    if (empty($name)) $errors[] = 'Product Name is required.';
    if (empty($category_id)) $errors[] = 'Category is required.';
    if (empty($brand_id)) $errors[] = 'Brand is required.';
    if (!is_numeric($unit_price) || $unit_price < 0) $errors[] = 'Unit Price must be a non-negative number.';
    if (!is_numeric($current_stock) || $current_stock < 0) $errors[] = 'Current Stock must be a non-negative integer.';
    if (!is_numeric($reorder_level) || $reorder_level < 0) $errors[] = 'Reorder Level must be a non-negative integer.';
    if (!empty($cost_price) && (!is_numeric($cost_price) || $cost_price < 0)) $errors[] = 'Cost Price must be a non-negative number if provided.';


    if (!empty($errors)) {
        Logger::log("PRODUCT_UPDATE_FAILED: Validation errors for Product ID $id: " . implode(', ', $errors));

        // IMPORTANT: Assign submitted values to the product object for form repopulation
        $product->sku = $sku;
        $product->name = $name;
        $product->description = $description;
        $product->category_id = $category_id;
        $product->brand_id = $brand_id;
        $product->unit_price = $unit_price;
        $product->cost_price = $cost_price; // Pass the raw submitted cost price
        $product->current_stock = $current_stock;
        $product->reorder_level = $reorder_level;
        $product->is_serialized = $is_serialized;
        $product->is_active = $is_active;
        $product->location_aisle = $location_aisle;
        $product->location_bin = $location_bin;

        $categories = Category::all();
        $brands = Brand::all();

        $this->view('staff/products/edit', [
            'error' => implode('<br>', $errors),
            'product' => $product, // This $product now contains the user's submitted (invalid) data
            'categories' => $categories,
            'brands' => $brands,
        ], 'staff'); // Keep 'staff' layout as it's an edit form
        return;
    }

    // ... (Rest of your update logic: Assign values again for saving, isDirty() check, try-catch) ...
    // Note: The assignment block here (before isDirty() and save()) will largely duplicate the one above.
    // This is fine and common. The first assignment is for repopulating, the second for actual model update.
    $product->sku = $sku;
    $product->name = $name;
    $product->description = $description;
    $product->category_id = $category_id;
    $product->brand_id = $brand_id;
    $product->unit_price = $unit_price;
    $product->cost_price = !empty($cost_price) ? $cost_price : null; // Normalize for DB
    $product->current_stock = $current_stock;
    $product->reorder_level = $reorder_level;
    $product->is_serialized = $is_serialized;
    $product->is_active = $is_active;
    $product->location_aisle = !empty($location_aisle) ? $location_aisle : null; // Normalize for DB
    $product->location_bin = !empty($location_bin) ? $location_bin : null;       // Normalize for DB

    if (!$product->isDirty()) {
        Logger::log("PRODUCT_UPDATE_INFO: Product ID $id submitted form with no changes.");
        $categories = Category::all();
        $brands = Brand::all();
        $this->view('staff/products/edit', [
            'success_message' => 'No changes were made to the product.',
            'product' => $product,
            'categories' => $categories,
            'brands' => $brands
        ],'staff');
        return;
    }

    try {
        $product->save();
        Logger::log("PRODUCT_UPDATE_SUCCESS: Product '{$product->name}' (ID: {$product->id}) updated successfully.");
        header('Location: /staff/products_list?success_message=' . urlencode('Product updated successfully!'));
        exit();
    } catch (\Exception $e) {
        Logger::log("PRODUCT_UPDATE_DB_ERROR: Failed to update product ID $id - " . $e->getMessage());
        $categories = Category::all();
        $brands = Brand::all();
        $this->view('staff/products/edit', [
            'error' => 'An error occurred while updating the product. Please try again. ' . $e->getMessage(),
            'product' => $product,
            'categories' => $categories,
            'brands' => $brands
        ],'staff');
        return;
    }
}

    /**
     * Handles the deletion of a product.
     * Accessible via /staff/products/delete/{id}
     *
     * @param int $id The ID of the product to delete.
     * @return void
     */
    public function delete($id) {
        Logger::log("PRODUCT_DELETE: Attempting to delete product ID: $id");

        $product = Product::find($id);

        if (!$product) {
            Logger::log("PRODUCT_DELETE_FAILED: Product ID $id not found for deletion.");
            header('Location: /staff/products_list?error=' . urlencode('Product not found for deletion.'));
            exit();
        }

        try {
            // IMPORTANT: Consider foreign key constraints here.
            // If product_instances or transaction_items reference this product,
            // you will get a foreign key constraint violation error unless:
            // 1. The foreign keys are set to ON DELETE CASCADE (which will delete related records).
            // 2. You manually delete related records first (e.g., ProductInstance::where('product_id', $id)->delete();).
            // 3. You prevent deletion if related records exist (e.g., check $product->instances->count() > 0).
            // For now, this assumes your DB handles CASCADE or you'll get an error.

            $product->delete();
            Logger::log("PRODUCT_DELETE_SUCCESS: Product '{$product->name}' (ID: {$product->id}) deleted successfully.");
            header('Location: /staff/products_list?success_message=' . urlencode('Product deleted successfully!'));
            exit();
        } catch (\Exception $e) {
            Logger::log("PRODUCT_DELETE_DB_ERROR: Failed to delete product ID $id - " . $e->getMessage());
            header('Location: /staff/products_list?error=' . urlencode('An error occurred while deleting the product: ' . $e->getMessage()));
            exit();
        }
    }
}