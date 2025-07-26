<?php
namespace Controllers;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Connection;

use Models\Product;
use Models\Category;
use Models\Brand;
use Models\ProductInstance; // Ensure this is available if you plan to use it for deletion checks

// As previously discussed, this line ideally belongs in your main application bootstrap (e.g., public/index.php)
// not necessarily in every controller.
require_once 'vendor/autoload.php';


class StaffProductController extends Controller {

    /**
     * Displays a single product's details, including its serialized instances.
     * Accessible via /staff/products/show/{id}
     *
     * @param int $id The ID of the product to show.
     * @return void
     */
    public function show($id)
    {
        Logger::log("PRODUCT_SHOW: Attempting to show product ID: {$id}");

        // Find the product by its ID and eager load related data
        // Ensure that the relationships defined in your Product and ProductInstance models are correct.
        // For ProductInstance relationships, they should link to TransactionItem model.
        $product = Product::with([
            'category', // Assuming product has a category relationship
            'productInstances' => function($query) {
                $query->with([
                    // Assuming ProductInstance has these relationships defined
                    // These names should match the function names in ProductInstance model
                    'purchaseTransactionItem',
                    'saleTransactionItem',
                    'returnedFromCustomerTransactionItem',
                    'returnedToSupplierTransactionItem',
                    'adjustedInTransactionItem',
                    'adjustedOutTransactionItem'
                ]);
            }
        ])->find($id);

        if (!$product) {
            Logger::log("PRODUCT_SHOW_ERROR: Product not found. ID: {$id}");
            // Use session for error messages and redirect back
            $_SESSION['error_message'] = "Product with ID {$id} not found.";
            header('Location: /staff/products_list?error=' . urlencode($_SESSION['error_message']));
            exit();
        }

        Logger::log("PRODUCT_SHOW_SUCCESS: Displaying product details for ID: {$id}");
        $this->view('staff/products/show', ['product' => $product]);
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

        // 1. Retrieve Input Data (SKU and current_stock are no longer from input)
        $name            = $this->input('name');
        $description     = $this->input('description');
        $category_id     = $this->input('category_id');
        $brand_id        = $this->input('brand_id');
        $unit_price      = $this->input('unit_price');
        $cost_price      = $this->input('cost_price');
        // $current_stock is NOT taken from input
        $reorder_level   = $this->input('reorder_level');
        $is_serialized   = $this->input('is_serialized') === 'on' ? true : false;
        $is_active       = $this->input('is_active') === 'on' ? true : false;
        $location_aisle  = $this->input('location_aisle');
        $location_bin    = $this->input('location_bin');

        // 2. Validation
        $errors = [];

        // Validate Product Name (must be unique)
        if (empty($name)) {
            $errors[] = 'Product Name is required.';
        } else {
            $existingProduct = Product::where('name', $name)->first();
            if ($existingProduct) {
                $errors[] = 'Product Name must be unique.';
            }
        }

        if (empty($category_id)) $errors[] = 'Category is required.';
        if (empty($brand_id)) $errors[] = 'Brand is required.';
        if (!is_numeric($unit_price) || $unit_price < 0) $errors[] = 'Unit Price must be a non-negative number.';
        // current_stock validation removed as it's not user input
        if (!is_numeric($reorder_level) || $reorder_level < 0) $errors[] = 'Reorder Level must be a non-negative integer.';

        // Optional fields validation
        if (!empty($cost_price) && (!is_numeric($cost_price) || $cost_price < 0)) $errors[] = 'Cost Price must be a non-negative number if provided.';

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
                    'name'            => $name, // Ensure name is repopulated
                    'description'     => $description,
                    'category_id'     => $category_id,
                    'brand_id'        => $brand_id,
                    'unit_price'      => $unit_price,
                    'cost_price'      => $cost_price,
                    // 'current_stock' should not be repopulated from input
                    'reorder_level'   => $reorder_level,
                    'is_serialized'   => $is_serialized,
                    'is_active'       => $is_active,
                    'location_aisle'  => $location_aisle,
                    'location_bin'    => $location_bin,
                ]
            ],'staff');
            return;
        }

        // 3. Create and Save Product
        try {
            $product = new Product();
            // Auto-generate SKU
            $product->sku = uniqid('PROD-', true); // 'PROD-' prefix, true for more entropy

            $product->name = $name;
            $product->description = $description;
            $product->category_id = $category_id;
            $product->brand_id = $brand_id;
            $product->unit_price = $unit_price;
            $product->cost_price = !empty($cost_price) ? $cost_price : null;
            $product->current_stock = 0; // Initialize current_stock to 0
            $product->reorder_level = $reorder_level;
            $product->is_serialized = $is_serialized;
            $product->is_active = $is_active;
            $product->location_aisle = !empty($location_aisle) ? $location_aisle : null;
            $product->location_bin = !empty($location_bin) ? $location_bin : null;

            $product->save();

            Logger::log("PRODUCT_STORE_SUCCESS: New product '{$product->name}' (ID: {$product->id}, SKU: {$product->sku}) added successfully.");
            header('Location: /staff/products_list?success_message=' . urlencode('Product added successfully!'));
            exit();

        } catch (\Exception $e) {
            Logger::log("PRODUCT_STORE_DB_ERROR: Failed to add product - " . $e->getMessage());
            $categories = Category::all();
            $brands = Brand::all();
            $this->view('staff/products/add', [
                'error' => 'An error occurred while adding the product. Please try again. ' . $e->getMessage(),
                'categories' => $categories,
                'brands' => $brands,
                'product' => (object)[ // Re-populate form with submitted data (SKU and current_stock excluded)
                    'name'            => $name,
                    'description'     => $description,
                    'category_id'     => $category_id,
                    'brand_id'        => $brand_id,
                    'unit_price'      => $unit_price,
                    'cost_price'      => $cost_price,
                    // 'current_stock' should not be repopulated
                    'reorder_level'   => $reorder_level,
                    'is_serialized'   => $is_serialized,
                    'is_active'       => $is_active,
                    'location_aisle'  => $location_aisle,
                    'location_bin'    => $location_bin,
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

    // 1. Retrieve Input Data
    $id              = $this->input('id');
    // SKU is removed from input retrieval as it's not editable
    $name            = $this->input('name');
    $description     = $this->input('description');
    $category_id     = $this->input('category_id');
    $brand_id        = $this->input('brand_id');
    $unit_price      = $this->input('unit_price');
    $cost_price      = $this->input('cost_price');
    // REMOVED: $current_stock = $this->input('current_stock'); // Not user input
    $reorder_level   = $this->input('reorder_level');
    $is_serialized   = $this->input('is_serialized') === 'on' ? true : false;
    $is_active       = $this->input('is_active') === 'on' ? true : false;
    $location_aisle  = $this->input('location_aisle');
    $location_bin    = $this->input('location_bin');

    $product = Product::find($id);

    if (!$product) {
        Logger::log("PRODUCT_UPDATE_FAILED: Product ID $id not found for update.");
        return $this->view('errors/404', ['message' => 'Product not found.'], 'staff');
    }

    // 2. Validation
    $errors = [];

    // Validate Product Name (must be unique, excluding current product)
    if (empty($name)) {
        $errors[] = 'Product Name is required.';
    } else {
        $existingProduct = Product::where('name', $name)->where('id', '!=', $id)->first();
        if ($existingProduct) {
            $errors[] = 'Product Name must be unique.';
        }
    }

    if (empty($category_id)) $errors[] = 'Category is required.';
    if (empty($brand_id)) $errors[] = 'Brand is required.';
    if (!is_numeric($unit_price) || $unit_price < 0) $errors[] = 'Unit Price must be a non-negative number.';
    // REMOVED: current_stock validation removed as it's not user input
    if (!is_numeric($reorder_level) || $reorder_level < 0) $errors[] = 'Reorder Level must be a non-negative integer.';
    if (!empty($cost_price) && (!is_numeric($cost_price) || $cost_price < 0)) $errors[] = 'Cost Price must be a non-negative number if provided.';


    if (!empty($errors)) {
        Logger::log("PRODUCT_UPDATE_FAILED: Validation errors for Product ID $id: " . implode(', ', $errors));

        // IMPORTANT: Assign submitted values to the product object for form repopulation (SKU and current_stock excluded)
        $product->name = $name;
        $product->description = $description;
        $product->category_id = $category_id;
        $product->brand_id = $brand_id;
        $product->unit_price = $unit_price;
        $product->cost_price = $cost_price;
        // REMOVED: $product->current_stock = $current_stock; // Not updated by form
        $product->reorder_level = $reorder_level;
        $product->is_serialized = $is_serialized;
        $product->is_active = $is_active;
        $product->location_aisle = $location_aisle;
        $product->location_bin = $location_bin;

        $categories = Category::all();
        $brands = Brand::all();

        $this->view('staff/products/edit', [
            'error' => implode('<br>', $errors),
            'product' => $product,
            'categories' => $categories,
            'brands' => $brands,
        ], 'staff');
        return;
    }

    // 4. Assign new values and check for changes
    // SKU and current_stock are NOT updated here
    $product->name = $name;
    $product->description = $description;
    $product->category_id = $category_id;
    $product->brand_id = $brand_id;
    $product->unit_price = $unit_price;
    $product->cost_price = !empty($cost_price) ? $cost_price : null;
    // REMOVED: $product->current_stock = $current_stock; // Not updated by form
    $product->reorder_level = $reorder_level;
    $product->is_serialized = $is_serialized;
    $product->is_active = $is_active;
    $product->location_aisle = !empty($location_aisle) ? $location_aisle : null;
    $product->location_bin = !empty($location_bin) ? $location_bin : null;

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
            // If current_stock should prevent deletion, add a check here:
            if ($product->current_stock > 0) {
                 Logger::log("PRODUCT_DELETE_FAILED: Product ID $id cannot be deleted because current_stock is greater than 0.");
                 header('Location: /staff/products_list?error=' . urlencode('Product cannot be deleted while it has stock remaining.'));
                 exit();
            }

            // If product instances exist, you might also want to prevent deletion or cascade delete them
            // if (!$product->instances->isEmpty()) {
            //     Logger::log("PRODUCT_DELETE_FAILED: Product ID $id cannot be deleted because it has associated instances.");
            //     header('Location: /staff/products_list?error=' . urlencode('Product cannot be deleted while it has associated instances.'));
            //     exit();
            // }


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