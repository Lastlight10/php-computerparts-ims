<?php
namespace Controllers;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Connection;
use Dompdf\Dompdf;
use Dompdf\Options;
use Models\Product;
use Models\Category;
use Models\Brand;
use Carbon\Carbon;
use Models\ProductInstance; // Ensure this is available if you plan to use it for deletion checks
use Models\Supplier;

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

        // Retrieve search, filter, and sort parameters for product instances
        $instance_search_query = $this->input('instance_search_query');
        $instance_filter_status = $this->input('instance_filter_status');
        $instance_sort_by = $this->input('instance_sort_by') ?: 'serial_number'; // Default sort for instances
        $instance_sort_order = $this->input('instance_sort_order') ?: 'asc';     // Default sort order for instances

        // Find the product by its ID and eager load related data
        $product = Product::with([
            'category',
            'brand',
            'productInstances' => function($query) use ($instance_search_query, $instance_filter_status, $instance_sort_by, $instance_sort_order) {
                // Apply search filter for instances
                if (!empty($instance_search_query)) {
                    $query->where('serial_number', 'LIKE', '%' . $instance_search_query . '%');
                    Logger::log("DEBUG: Applied instance search query: '{$instance_search_query}'");
                }

                // Apply status filter for instances
                if (!empty($instance_filter_status)) {
                    $query->where('status', $instance_filter_status);
                    Logger::log("DEBUG: Applied instance status filter: '{$instance_filter_status}'");
                }

                // Apply sorting for instances
                $allowed_instance_sort_columns = [
                    'id', 'serial_number', 'status', 'cost_at_receipt',
                    'warranty_expires_at', 'created_at', 'updated_at'
                ];
                if (!in_array($instance_sort_by, $allowed_instance_sort_columns)) {
                    $instance_sort_by = 'serial_number'; // Fallback
                }
                if (!in_array(strtolower($instance_sort_order), ['asc', 'desc'])) {
                    $instance_sort_order = 'asc'; // Fallback
                }
                $query->orderBy($instance_sort_by, $instance_sort_order);
                Logger::log("DEBUG: Applied instance sorting: '{$instance_sort_by}' {$instance_sort_order}");

                // Eager load transaction items and their transactions for dates
                $query->with([
                    'purchaseTransactionItem.transaction',
                    'saleTransactionItem.transaction'
                ]);
            }
        ])->find($id);

        if (!$product) {
            Logger::log("PRODUCT_SHOW_ERROR: Product not found. ID: {$id}");
            $_SESSION['error_message'] = "Product with ID {$id} not found.";


            header('Location: /staff/products_list');
            exit();
        }

        // Get all possible ProductInstance statuses for the filter dropdown
        // This list should ideally come from a constant or database schema reflection
        // For now, hardcoding based on your schema:
        $product_instance_statuses = [
            'In Stock',
            'Sold',
            'Returned - Resalable',
            'Returned - Defective',
            'Repairing',
            'Scrapped',
            'Pending Stock',
            'Adjusted Out',
            'Removed'
        ];

        Logger::log("PRODUCT_SHOW_SUCCESS: Displaying product details for ID: {$id}");
        $this->view('staff/products/show', [
            'product' => $product,
            'instance_search_query' => $instance_search_query,
            'instance_filter_status' => $instance_filter_status,
            'instance_sort_by' => $instance_sort_by,
            'instance_sort_order' => $instance_sort_order,
            'product_instance_statuses' => $product_instance_statuses, // Pass statuses to view
        ], 'staff'); // Pass 'staff' layout
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
        $suppliers = Supplier::all();

        $this->view('staff/products/add', [
            'categories' => $categories,
            'brands' => $brands,
            'suppliers' => $suppliers,
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
        $name            = trim($this->input('name'));
        $description     = trim($this->input('description'));
        $category_id     = trim($this->input('category_id'));
        $brand_id        = trim($this->input('brand_id'));
        $unit_price      = trim($this->input('unit_price'));
        $cost_price      = trim($this->input('cost_price'));
        // $current_stock is NOT taken from input
        $reorder_level   = trim($this->input('reorder_level'));
        $is_serialized   = $this->input('is_serialized') === 'on' ? true : false;
        $is_active       = $this->input('is_active') === 'on' ? true : false;
        $location_aisle  = trim($this->input('location_aisle'));
        $location_bin    = trim($this->input('location_bin'));

        $supplier_ids    = $this->input('supplier_ids') ?? [];

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
        if (empty($supplier_ids)) $errors[] = 'At least one Supplier is required.';
        if (!is_numeric($unit_price) || $unit_price < 0) $errors[] = 'Unit Price must be a non-negative number.';
        // current_stock validation removed as it's not user input
        if (!is_numeric($reorder_level) || $reorder_level < 0) $errors[] = 'Reorder Level must be a non-negative integer.';

        // Optional fields validation
        if (!empty($cost_price) && (!is_numeric($cost_price) || $cost_price < 0)) $errors[] = 'Cost Price must be a non-negative number if provided.';
        
        if (!empty($supplier_ids)) {
            if (!is_array($supplier_ids)) {
                $errors[] = 'Invalid supplier data submitted.';
            } else {
                // Check that all submitted IDs are numeric and non-zero
                $invalid_ids = array_filter($supplier_ids, function($id) {
                    return !is_numeric($id) || $id <= 0;
                });
                if (!empty($invalid_ids)) {
                    $errors[] = 'One or more submitted Supplier IDs are invalid.';
                }
            }
        }
        if (!empty($errors)) {
            Logger::log("PRODUCT_STORE_FAILED: Validation errors: " . implode(', ', $errors));
            
            $categories = Category::all();
            $brands = Brand::all();

            $suppliers = Supplier::all(); 
            
            $_SESSION['error_message']="Error: " . implode('<br>', $errors);
            $this->view('staff/products/add', [
                'categories' => $categories,
                'brands' => $brands,
                'suppliers' => $suppliers,
                'product' => (object)[ // Create a dummy object to mimic Eloquent model for form repopulation
                    'name'            => $name, // Ensure name is repopulated
                    'description'     => $description,
                    'category_id'     => $category_id,
                    'brand_id'        => $brand_id,
                    'unit_price'      => $unit_price,
                    'cost_price'      => $cost_price,
                    'reorder_level'   => $reorder_level,
                    'is_serialized'   => $is_serialized,
                    'is_active'       => $is_active,
                    'location_aisle'  => $location_aisle,
                    'location_bin'    => $location_bin,
                    'supplier_ids'    => $supplier_ids
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

             if (!empty($supplier_ids)) {
            // Assuming your Product model has a suppliers() BelongsToMany relationship
                $product->suppliers()->sync($supplier_ids);
                Logger::log("PRODUCT_STORE_SUCCESS: Synced suppliers for product ID: {$product->id}.");
            }

            Logger::log("PRODUCT_STORE_SUCCESS: New product '{$product->name}' (ID: {$product->id}, SKU: {$product->sku}) added successfully.");

            $_SESSION['success_message']="New product " . $product->name . " added successfully.";
            header('Location: /staff/products_list');
            exit();

        } catch (\Exception $e) {
            Logger::log("PRODUCT_STORE_DB_ERROR: Failed to add product - " . $e->getMessage());
            $categories = Category::all();
            $brands = Brand::all();

            $suppliers = Supplier::all(); 

            $_SESSION['error_message']="Failed to add product. " . $e->getMessage();

            $this->view('staff/products/add', [
                'categories' => $categories,
                'brands' => $brands,
                'suppliers' => $suppliers,
                'product' => (object)[ // Re-populate form with submitted data (SKU and current_stock excluded)
                    'name'            => $name,
                    'description'     => $description,
                    'category_id'     => $category_id,
                    'brand_id'        => $brand_id,
                    'unit_price'      => $unit_price,
                    'cost_price'      => $cost_price,
                    'reorder_level'   => $reorder_level,
                    'is_serialized'   => $is_serialized,
                    'is_active'       => $is_active,
                    'location_aisle'  => $location_aisle,
                    'location_bin'    => $location_bin,
                    'supplier_ids'    => $supplier_ids
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

       $product = Product::with('suppliers')->find($id);

        if (!$product) {
            Logger::log("PRODUCT_EDIT_FAILED: Product ID $id not found for editing.");

            $_SESSION['error_message']="Product not found.";

            return $this->view('staff/products_list', [],'staff');
        }

        // Fetch categories and brands for dropdowns
        $categories = Category::all();
        $brands = Brand::all();
        $suppliers = Supplier::all();

        $selected_supplier_ids = $product->suppliers->pluck('id')->toArray();

        // Attach this array to the product object for easy access in the view
        // (This is primarily for repopulating a multi-select box)
        $product->supplier_ids = $selected_supplier_ids; 

        Logger::log("PRODUCT_EDIT_SUCCESS: Displaying edit form for product ID: $id - {$product->name}");
        $this->view('staff/products/edit', [
            'product' => $product,
            'categories' => $categories,
            'suppliers' => $suppliers,
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
    $id              = trim($this->input('id'));
    // SKU is removed from input retrieval as it's not editable
    $name            = trim($this->input('name'));
    $description     = trim($this->input('description'));
    $category_id     = trim($this->input('category_id'));
    $brand_id        = trim($this->input('brand_id'));
    $unit_price      = trim($this->input('unit_price'));
    $cost_price      = trim($this->input('cost_price'));
    // REMOVED: $current_stock = $this->input('current_stock'); // Not user input
    $reorder_level   = trim($this->input('reorder_level'));
    $is_serialized   = $this->input('is_serialized') === 'on' ? true : false;
    $is_active       = $this->input('is_active') === 'on' ? true : false;
    $location_aisle  = trim($this->input('location_aisle'));
    $location_bin    = trim($this->input('location_bin'));

    $product = Product::find($id);

     $supplier_ids    = $this->input('supplier_ids') ?? [];

    if (!$product) {
        Logger::log("PRODUCT_UPDATE_FAILED: Product ID $id not found for update.");
        $_SESSION['error_message']="Product not found.";
        return $this->view('staff/products_list', [], 'staff');
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

    if (empty($supplier_ids)) {
         $errors[] = 'At least one Supplier is required.';
    } elseif (!is_array($supplier_ids) || count(array_filter($supplier_ids, 'is_numeric')) !== count($supplier_ids)) {
         $errors[] = 'Invalid supplier data submitted.';
    }

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

        $product->supplier_ids = $supplier_ids; 
        $categories = Category::all();
        $brands = Brand::all();
        $suppliers = Supplier::all();


        $_SESSION['error_message']="Error: ". implode('<br>',$errors); 
        $this->view('staff/products/edit', [
            'product' => $product,
            'categories' => $categories,
            'brands' => $brands,
            'suppliers' => $suppliers,
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

    // Check if any product attributes were changed
    $product_is_dirty = $product->isDirty();
    
    // Check if the list of suppliers has changed
    // This requires fetching the current IDs before saving
    $current_supplier_ids = $product->suppliers->pluck('id')->map(function($item) { return (string)$item; })->toArray(); // Map to string for strict comparison
    $submitted_supplier_ids = array_map('strval', $supplier_ids); // Map to string for strict comparison

    // Check if arrays are identical (requires sorting and strict comparison)
    sort($current_supplier_ids);
    sort($submitted_supplier_ids);
    $suppliers_changed = ($current_supplier_ids !== $submitted_supplier_ids);


    if (!$product_is_dirty && !$suppliers_changed) {
        Logger::log("PRODUCT_UPDATE_INFO: Product ID $id submitted form with no changes.");
        
        $categories = Category::all();
        $brands = Brand::all();
        $suppliers = Supplier::all(); 
        
        // Re-assign submitted supplier_ids to product for view repopulation
        $product->supplier_ids = $submitted_supplier_ids; 
        
        $_SESSION['warning_message']="No changes made.";
        $this->view('staff/products/edit', [
            'product' => $product,
            'categories' => $categories,
            'brands' => $brands,
            'suppliers' => $suppliers,
        ],'staff');
        return;
    }
    try {
         // 5. Save Product
        if ($product_is_dirty) {
            $product->save();
        }

        // 6. Sync Suppliers only if they changed
        if ($suppliers_changed) {
            $product->suppliers()->sync($supplier_ids);
            Logger::log("PRODUCT_UPDATE_INFO: Synced suppliers for product ID: {$product->id}.");
        }
        Logger::log("PRODUCT_UPDATE_SUCCESS: Product '{$product->name}' (ID: {$product->id}) updated successfully.");

        $_SESSION['success_message']="Successfully updated product.";
        header('Location: /staff/products_list');
        exit();
    } catch (\Exception $e) {
        Logger::log("PRODUCT_UPDATE_DB_ERROR: Failed to update product ID $id - " . $e->getMessage());
        $product->supplier_ids = $supplier_ids; 
        
        $categories = Category::all();
        $brands = Brand::all();
        $suppliers = Supplier::all();

        $_SESSION['error_message']="Failed to update product. " . $e->getMessage();
        $this->view('staff/products/edit', [
            'product' => $product,
            'categories' => $categories,
            'brands' => $brands,
            'suppliers' => $suppliers,
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

            $_SESSION['error_message']="Product not found.";
            header('Location: /staff/products_list?');
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

                 $_SESSION['error_message']="Product can't be deleted because current_stock is greater than 0";
                 header('Location: /staff/products_list');
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

            $_SESSION['success_message']="Successfully deleted product: " . $product->name;
            header('Location: /staff/products_list');
            exit();
        } catch (\Exception $e) {
            Logger::log("PRODUCT_DELETE_DB_ERROR: Failed to delete product ID $id - " . $e->getMessage());

            $_SESSION['error_message']="Failed to delete product. " . $e->getMessage();
            header('Location: /staff/products_list');
            exit();
        }
    }
    public function printProductsList() {
        Logger::log("PRINT_PRODUCTS_LIST: Attempting to generate PDF for products list.");
        date_default_timezone_set('Asia/Manila');
        // Retrieve search, filter, and sort parameters from GET request
        $search_query = trim($this->input('search_query'));
        $filter_category_id = trim($this->input('filter_category_id'));
        $filter_brand_id = trim($this->input('filter_brand_id'));
        $filter_is_serialized = trim($this->input('filter_is_serialized'));
        $filter_date_range = trim($this->input('filter_date_range'));

        $filter_is_active = trim($this->input('filter_is_active'));
        $sort_by = trim($this->input('sort_by')) ?: 'name';
        $sort_order = trim($this->input('sort_order')) ?: 'asc';

        $products_query = Product::with(['category', 'brand']);

        // Apply search query
        if (!empty($search_query)) {
            $products_query->where(function($query) use ($search_query) {
                $query->where('sku', 'like', '%' . $search_query . '%')
                      ->orWhere('name', 'like', '%' . $search_query . '%')
                      ->orWhere('description', 'like', '%' . $search_query . '%')
                      ->orWhereHas('category', function($q) use ($search_query) {
                          $q->where('name', 'like', '%' . $search_query . '%');
                      })
                      ->orWhereHas('brand', function($q) use ($search_query) {
                          $q->where('name', 'like', '%' . $search_query . '%');
                      });
            });
        }
        if (!empty($filter_date_range)) {
    $now = Carbon::now();

    switch ($filter_date_range) {
        case 'today':
            $products_query->whereDate('created_at', $now->toDateString());
            break;
        case 'yesterday':
            $products_query->whereDate('created_at', $now->copy()->subDay()->toDateString());
            break;
        case 'week':
            $products_query->whereBetween('created_at', [
                $now->copy()->startOfWeek()->toDateString(),
                $now->copy()->endOfWeek()->toDateString()
            ]);
            break;
        case 'month':
            $products_query->whereBetween('created_at', [
                $now->copy()->startOfMonth()->toDateString(),
                $now->copy()->endOfMonth()->toDateString()
            ]);
            break;
        case 'year':
            $products_query->whereBetween('created_at', [
                $now->copy()->startOfYear()->toDateString(),
                $now->copy()->endOfYear()->toDateString()
            ]);
            break;
    }
}


        // Apply filters
        if (!empty($filter_category_id)) {
            $products_query->where('category_id', $filter_category_id);
        }
        if (!empty($filter_brand_id)) {
            $products_query->where('brand_id', $filter_brand_id);
        }
        if ($filter_is_serialized !== '') { // Check for empty string specifically, as '0' is a valid value
            $products_query->where('is_serialized', $filter_is_serialized === 'Yes' ? 1 : 0);
        }
        if ($filter_is_active !== '') { // Check for empty string specifically
            $products_query->where('is_active', $filter_is_active === 'Yes' ? 1 : 0);
        }

        // Apply sorting
        $products = $products_query->orderBy($sort_by, $sort_order)->get();

        if ($products->isEmpty()) {
            Logger::log("PRINT_PRODUCTS_LIST_FAILED: No products found matching criteria for printing.");
            // Redirect back to the list page with an error message

            $_SESSION['error_message']="No products found to print.";
            header('Location: /staff/products_list');
            exit();
        }
        

        // Configure Dompdf options
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $logoData = base64_encode(file_get_contents('resources/images/Heading.png'));
        $logoSrc = 'data:image/png;base64,' . $logoData;
        // Build the HTML content for the PDF list
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Products List Report</title>
            <style>
                body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; line-height: 1; color: #333; }
                .container { width: 95%; margin: 0 auto; padding: 7px; }
                .header { text-align: center; margin-bottom: 20px; }
                .header h1 { margin: 0; padding: 0; color: #0056b3; font-size: 20px; }
                .filters-info { margin-bottom: 20px; font-size: 10px; }
                .filters-info strong { display: inline-block; width: 80px; }
                .items-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                .items-table th, .items-table td { border: 1px solid #ddd; padding: 4px; text-align: left; }
                .items-table th { background-color: #f2f2f2; font-weight: bold; }
                .footer { text-align: center; margin-top: 30px; font-size: 8px; color: #777; }
            </style>
        </head>
        <body>
            <div class="container">
            <div class="logo" style="text-align: center;">
                    <img src="' . $logoSrc . '" alt="Company Logo" style="height: 100px; width: 100px; border-radius: 50%; object-fit: cover;">
                    <div class="company-info" style="margin-top: 10px; font-size: 13px; color: #333;">
                        <strong>Computer Parts Company</strong><br>
                            123 Main Street, City, Country<br>
                            Phone: (123) 456-7890 | Email: info@company.com
                    </div>
                </div>
                <div class="header">
                    <h1>Products List Report</h1>
                    <p>Generated on: ' . date('F j, Y, h:i A') . '</p>
                </div>

                <div class="filters-info">
                    <p><strong>Search:</strong> ' . htmlspecialchars($search_query ?: 'N/A') . '</p>
                    <p><strong>Category Filter:</strong> ' . htmlspecialchars(Category::find($filter_category_id)->name ?? 'All Categories') . '</p>
                    <p><strong>Brand Filter:</strong> ' . htmlspecialchars(Brand::find($filter_brand_id)->name ?? 'All Brands') . '</p>
                    <p><strong>Serialized Filter:</strong> ' . htmlspecialchars($filter_is_serialized ?: 'All') . '</p>
                    <p><strong>Active Filter:</strong> ' . htmlspecialchars($filter_is_active ?: 'All') . '</p>
                    <p><strong>Sort By:</strong> ' . htmlspecialchars(ucwords(str_replace('_', ' ', $sort_by))) . ' (' . htmlspecialchars(ucfirst($sort_order)) . ')</p>
                    <p><strong>Date Filter:</strong> ' . htmlspecialchars(match($filter_date_range) {
                        'today' => 'Today',
                        'yesterday' => 'Yesterday',
                        'week' => 'This Week',
                        'month' => 'This Month',
                        'year' => 'This Year',
                        default => 'All Dates'
                    }) . '</p>

                    </div>


                <table class="items-table">
                    <thead>
                        <tr>
                            <th>CODE</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Brand</th>
                            <th>Unit Price (₱)</th>
                            <th>Cost Price (₱)</th>
                            <th>Stock</th>
                            <th>Serialized?</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach ($products as $product) {
            $html .= '
                        <tr>
                            <td>' . htmlspecialchars($product->sku ?? 'N/A') . '</td>
                            <td style="max-width:200px; white-space:normal; overflow-wrap:break-word; word-wrap: break-word;">' . htmlspecialchars($product->name ?? 'N/A') . '</td>
                            <td>' . htmlspecialchars($product->category->name ?? 'N/A') . '</td>
                            <td>' . htmlspecialchars($product->brand->name ?? 'N/A') . '</td>
                            <td>₱' . number_format($product->unit_price ?? 0.00, 2) . '</td>
                            <td>₱' . number_format($product->cost_price ?? 0.00, 2) . '</td>
                            <td>' . htmlspecialchars($product->current_stock ?? 'N/A') . '</td>
                            <td>' . htmlspecialchars(($product->is_serialized ? 'Yes' : 'No')) . '</td>
                        </tr>';
        }

        $html .= '
                    </tbody>
                </table>

                <div class="footer">
                    <p>Report generated by Computer IMS.</p>
                </div>
            </div>
        </body>
        </html>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait'); // Landscape for wider table
        $dompdf->render();

        $dompdf->stream("Products_List_Report_" . date('Ymd_His') . ".pdf", ["Attachment" => false]);

        $_SESSION['success_message']="Product successfully printed as a pdf.";
        Logger::log("PRINT_PRODUCTS_LIST_SUCCESS: PDF list generated and streamed.");

        header('Location: /staff/products_list');
        exit();
    }
    public function printProductDetails($id) {
        Logger::log("PRINT_PRODUCT_DETAILS: Attempting to generate PDF for product ID: $id.");
        date_default_timezone_set('Asia/Manila');

        // Load the product with all necessary relationships
        $product = Product::with([
            'category',
            'brand',
            'suppliers', // Load the suppliers relationship
            'productInstances.purchaseTransactionItem.transaction',
            'productInstances.saleTransactionItem.transaction',
            'productInstances.returnedFromCustomerTransactionItem.transaction',
            'productInstances.returnedToSupplierTransactionItem.transaction',
            'productInstances.adjustedInTransactionItem.transaction',
            'productInstances.adjustedOutTransactionItem.transaction',
            'createdBy',
            'updatedBy'
        ])->find($id);

        if (!$product) {
            Logger::log("PRINT_PRODUCT_DETAILS_FAILED: Product ID $id not found for printing.");

            $_SESSION['error_message'] = "Product not found for printing.";
            header('Location: /staff/products_list'); 
            exit();
        }

        // Configure Dompdf options
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $logoData = base64_encode(file_get_contents('resources/images/Heading.png'));
        $logoSrc = 'data:image/png;base64,' . $logoData;

        // Logic to build the supplier list in HTML bullet format
        $supplier_html = '';
        if (!empty($product->suppliers) && method_exists($product->suppliers, 'isNotEmpty') && $product->suppliers->isNotEmpty()) {
            $supplier_html .= '<ul style="margin: 0; padding-left: 20px;">'; // Start the unordered list
            foreach ($product->suppliers as $supplier) {
                $name = $supplier->company_name ?: trim($supplier->contact_first_name . ' ' . $supplier->contact_last_name);
                $supplier_html .= '<li>' . htmlspecialchars($name ?: "Supplier #{$supplier->id}") . '</li>'; // Add a list item
            }
            $supplier_html .= '</ul>'; // End the unordered list
        } else {
            $supplier_html = 'No supplier(s) provided.';
        }

        // Build the HTML content for the PDF
        $html = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Product Details: ' . htmlspecialchars($product->name) . '</title>
                <style>
                    body { font-family: "DejaVu Sans", sans-serif; font-size: 12px; line-height: 1.6; color: #333; }
                    .container { width: 90%; margin: 0 auto; padding: 20px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
                    .header { text-align: center; margin-bottom: 30px; }
                    .header h1 { margin: 0; padding: 0; color: #0056b3; font-size: 20px;}
                    .details-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
                    .details-table td { padding: 8px; border-bottom: 1px solid #eee; }
                    .details-table strong { display: inline-block; width: 150px; }
                    .instances-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    .instances-table th, .instances-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    .instances-table th { background-color: #f2f2f2; }
                    .notes { margin-top: 20px; padding: 10px; border: 1px solid #eee; background-color: #f9f9f9; }
                    .footer { text-align: center; margin-top: 30px; font-size: 10px; color: #777; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                    <div class="logo" style="text-align: center;">
                        <img src="' . $logoSrc . '" alt="Company Logo" style="height: 100px; width: 100px; border-radius: 50%; object-fit: cover;">
                        <div class="company-info" style="margin-top: 10px; font-size: 13px; color: #333;">
                            <strong>Computer Parts Company</strong><br>
                                123 Main Street, City, Country<br>
                                Phone: (123) 456-7890 | Email: info@company.com
                        </div>
                    </div>
                        <h1>Product Details Report</h1>
                        <h2>' . htmlspecialchars($product->name) . ' (Code: ' . htmlspecialchars($product->sku) . ')</h2>
                    </div>

                    <table class="details-table">
                        <tr><td><strong>Category:</strong></td><td>' . htmlspecialchars($product->category->name ?? 'N/A') . '</td></tr>
                        <tr><td><strong>Brand:</strong></td><td>' . htmlspecialchars($product->brand->name ?? 'N/A') . '</td></tr>
                        <tr><td><strong>Supplier/s:</strong></td><td>' . $supplier_html . '</td></tr> <tr><td><strong>Unit Price:</strong></td><td>₱' . number_format($product->unit_price ?? 0, 2) . '</td></tr>
                        <tr><td><strong>Cost Price:</strong></td><td>₱' . number_format($product->cost_price ?? 0, 2) . '</td></tr>
                        <tr><td><strong>Current Stock:</strong></td><td>' . htmlspecialchars($product->current_stock ?? 'N/A') . '</td></tr>
                        <tr><td><strong>Reorder Level:</strong></td><td>' . htmlspecialchars($product->reorder_level ?? 'N/A') . '</td></tr>
                        <tr><td><strong>Serialized:</strong></td><td>' . (($product->is_serialized ?? false) ? 'Yes' : 'No') . '</td></tr>
                        <tr><td><strong>Active:</strong></td><td>' . (($product->is_active ?? false) ? 'Yes' : 'No') . '</td></tr>
                        <tr><td><strong>Location:</strong></td><td>' . htmlspecialchars($product->location_aisle ?? 'N/A') . ' / ' . htmlspecialchars($product->location_bin ?? 'N/A') . '</td></tr>
                        
                        <tr><td><strong>Created At:</strong></td><td>' . htmlspecialchars($product->created_at ? date('Y-m-d H:i', strtotime($product->created_at)) : 'N/A') . '</td></tr>
                        
                        <tr><td><strong>Updated At:</strong></td><td>' . htmlspecialchars($product->updated_at ? date('Y-m-d H:i', strtotime($product->updated_at)) : 'N/A') . '</td></tr>
                    </table>

                    <div class="notes" style="max-width:800px; white-space:normal; overflow-wrap:break-word;">
                        <strong>Description:</strong><br>' . nl2br(htmlspecialchars($product->description ?? 'No description provided.')) . '
                    </div>';

        if ($product->is_serialized && $product->productInstances->isNotEmpty()) {
            $html .= '
                    <h3>Individual Units</h3>
                    <table class="instances-table">
                        <thead>
                            <tr>
                                <th>Serial Number</th>
                                <th>Status</th>
                                <th>Cost at Receipt</th>
                                <th>Warranty Expiration</th>
                                <th>Purchase Date</th>
                                <th>Sold Date</th>
                            </tr>
                        </thead>
                        <tbody>';
            foreach ($product->productInstances as $instance) {
                $purchase_date = 'N/A';
                if (isset($instance->purchaseTransactionItem->transaction->transaction_date)) {
                    $purchase_date = date('Y-m-d', strtotime($instance->purchaseTransactionItem->transaction->transaction_date));
                }

                $sold_date = 'N/A';
                if (isset($instance->saleTransactionItem->transaction->transaction_date)) {
                    $sold_date = date('Y-m-d', strtotime($instance->saleTransactionItem->transaction->transaction_date));
                }

                $html .= '
                            <tr>
                                <td>' . htmlspecialchars($instance->serial_number ?? 'N/A') . '</td>
                                <td>' . htmlspecialchars($instance->status ?? 'N/A') . '</td>
                                <td>₱' . number_format((float)$instance->cost_at_receipt ?? 0, 2) . '</td>
                                <td>' . htmlspecialchars($instance->warranty_expires_at ? date('Y-m-d', strtotime($instance->warranty_expires_at)) : 'N/A') . '</td>
                                <td>' . htmlspecialchars($purchase_date) . '</td>
                                <td>' . htmlspecialchars($sold_date) . '</td>
                            </tr>';
            }
            $html .= '
                        </tbody>
                    </table>';
        } elseif ($product->is_serialized && $product->productInstances->isEmpty()) {
            $html .= '<h3>Individual Units</h3><p>No individual units tracked yet for this serialized product.</p>';
        } else {
            $html .= '<h3>Individual Units</h3><p>This product is not serialized, so individual units are not tracked.</p>';
        }

        $html .= '
                    <div class="footer">
                        <p>Generated by Computer IMS on ' . date('F j, Y, h:i A') . '</p>
                    </div>
                </div>
            </body>
            </html>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        $dompdf->stream("Product_Details_" . htmlspecialchars($product->sku) . ".pdf", ["Attachment" => false]);
        Logger::log("PRINT_PRODUCT_DETAILS_SUCCESS: PDF generated and streamed for product ID: $id.");

        // The script execution must stop after streaming the PDF content.
        exit();
    }
}
