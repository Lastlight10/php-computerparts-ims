<?php

namespace Controllers;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Connection;
use Carbon\Carbon;
use Models\User;
use Models\Transaction;
use Models\Customer;
use Models\Supplier;
use Models\Product;
use Models\Category;
use Models\Brand;


require_once 'vendor/autoload.php';


class StaffController extends Controller {

    public function home() {
        $this->view('staff/home', [],'staff');
    }

  public function dashboard() {
    Logger::log("Reached Dashboard.");
    $first_data = $this->getUserInfoAndCount();
    $second_data = $this->getProductAndTransactionCount();
    $data = array_merge($first_data, $second_data);
    $this->view('staff/dashboard', $data,'staff');
  }

  public function user_list() {
    Logger::log('Reached List of Users');

    // Get and trim search query
    $search_query = trim((string) $this->input('search_query'));

    // Build query (still select all needed columns)
    $user_query = User::select(
        'id',
        'username',
        'email',
        'first_name',
        'middle_name',
        'last_name',
        'birthdate',
        'created_at',
        'type'
    );

    // Apply search filter (username, email, names only)
    if (!empty($search_query)) {
        $user_query->where(function ($q) use ($search_query) {
            $q->where('username', 'LIKE', '%' . $search_query . '%')
              ->orWhere('email', 'LIKE', '%' . $search_query . '%')
              ->orWhere('first_name', 'LIKE', '%' . $search_query . '%')
              ->orWhere('middle_name', 'LIKE', '%' . $search_query . '%')
              ->orWhere('last_name', 'LIKE', '%' . $search_query . '%');
        });
        Logger::log("DEBUG: Applied user search query: '{$search_query}'");
    }

    // Fetch results
    $user_info = $user_query->get();

    Logger::log("DEBUG: Number of users retrieved: " . $user_info->count());

    // Ensure iterable
    if (is_null($user_info)) {
        $user_info = collect([]);
    }

    // Pass back search_query
    $this->view('staff/user_list', [
        'user_info'    => $user_info,
        'search_query' => $search_query
    ], 'staff');
}
  /**
   * Displays a list of all products with search, filter, and sort capabilities.
   * Accessible via /staff/products_list
   *
   * @return void
   */
  public function products_list() {
    Logger::log('Reached List of Products');

    // Retrieve search, filter, and sort parameters from GET request
    $search_query = trim((string) $this->input('search_query'));
    $filter_category_id = $this->input('filter_category_id');
    $filter_brand_id = $this->input('filter_brand_id');
    $filter_date_range = $this->input('filter_date_range');
    $filter_is_serialized = $this->input('filter_is_serialized'); // 'Yes', 'No', or ''
    $filter_is_active = $this->input('filter_is_active');     // 'Yes', 'No', or ''
    $sort_by = $this->input('sort_by') ?: 'name'; // Default sort column
    $sort_order = $this->input('sort_order') ?: 'asc'; // Default sort order

    // Start building the query
    $products_query = Product::select(
        'id', 'sku', 'name', 'description', 'category_id', 'brand_id',
        'unit_price', 'cost_price', 'current_stock', 'reorder_level',
        'is_serialized', 'is_active', 'location_aisle', 'location_bin',
        'created_at', 'updated_at'
    )
    ->with(['category', 'brand']); // Eager load relationships

    // Apply search filter
    if (!empty($search_query)) {
        $products_query->where(function ($query) use ($search_query) {
            $query->where('sku', 'LIKE', '%' . $search_query . '%')
                  ->orWhere('name', 'LIKE', '%' . $search_query . '%')
                  ->orWhere('description', 'LIKE', '%' . $search_query . '%')
                  ->orWhereHas('category', function ($q) use ($search_query) {
                      $q->where('name', 'LIKE', '%' . $search_query . '%');
                  })
                  ->orWhereHas('brand', function ($q) use ($search_query) {
                      $q->where('name', 'LIKE', '%' . $search_query . '%');
                  });
        });
        Logger::log("DEBUG: Applied product search query: '{$search_query}'");
    }
    if (!empty($filter_date_range)) {
    $now = Carbon::now();
        Logger::log("Date Range Filter: $filter_date_range");

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

    // Apply category filter
    if (!empty($filter_category_id)) {
        $products_query->where('category_id', $filter_category_id);
        Logger::log("DEBUG: Applied category filter: '{$filter_category_id}'");
    }

    // Apply brand filter
    if (!empty($filter_brand_id)) {
        $products_query->where('brand_id', $filter_brand_id);
        Logger::log("DEBUG: Applied brand filter: '{$filter_brand_id}'");
    }

    // Apply is_serialized filter
    if ($filter_is_serialized !== '' && $filter_is_serialized !== null) {
        $is_serialized_value = ($filter_is_serialized === 'Yes') ? 1 : 0;
        $products_query->where('is_serialized', $is_serialized_value);
        Logger::log("DEBUG: Applied serialized filter: '{$filter_is_serialized}' ({$is_serialized_value})");
    }

    // Apply is_active filter
    if ($filter_is_active !== '' && $filter_is_active !== null) {
        $is_active_value = ($filter_is_active === 'Yes') ? 1 : 0;
        $products_query->where('is_active', $is_active_value);
        Logger::log("DEBUG: Applied active filter: '{$filter_is_active}' ({$is_active_value})");
    }

    // Apply sorting
    $allowed_sort_columns = [
        'id', 'sku', 'name', 'unit_price', 'cost_price',
        'current_stock', 'reorder_level', 'is_serialized', 'is_active',
        'created_at', 'updated_at'
    ];
    if (!in_array($sort_by, $allowed_sort_columns)) {
        $sort_by = 'name'; // Fallback to default if invalid
    }

    if (!in_array(strtolower($sort_order), ['asc', 'desc'])) {
        $sort_order = 'asc'; // Fallback to default if invalid
    }

    $products_query->orderBy($sort_by, $sort_order);
    Logger::log("DEBUG: Applied product sorting: '{$sort_by}' {$sort_order}");

    $products_info = $products_query->get();

    Logger::log('DEBUG: Number of products retrieved: ' . $products_info->count());

    // Fetch all categories and brands for filter dropdowns
    $categories = Category::all();
    $brands = Brand::all();

    $this->view('staff/products_list', [
        'products_info' => $products_info,
        'search_query' => $search_query,
        'filter_category_id' => $filter_category_id,
        'filter_brand_id' => $filter_brand_id,
        'filter_is_serialized' => $filter_is_serialized,
        'filter_is_active' => $filter_is_active,
        'filter_date_range' => $filter_date_range,
        'sort_by' => $sort_by,
        'sort_order' => $sort_order,
        'categories' => $categories, // Pass categories to view
        'brands' => $brands,         // Pass brands to view
    ], 'staff');
  }

  /**
   * Displays a list of all transactions with search, filter, and sort capabilities.
   * Accessible via /staff/transactions_list
   *
   * @return void
   */
  public function transactions_list() {
    Logger::log('Reached List of Transactions');

    // --- DEBUGGING START ---
    Logger::log('DEBUG: Raw GET parameters: ' . json_encode($_GET));
    // --- DEBUGGING END ---

    // Retrieve search, filter, and sort parameters from GET request
    $search_query = trim((string) $this->input('search_query'));
    $filter_type = $this->input('filter_type');
    $filter_status = $this->input('filter_status');
    $filter_date_range = $this->input('filter_date_range');

    $sort_by = $this->input('sort_by') ?: 'transaction_date'; // Default sort column
    $sort_order = $this->input('sort_order') ?: 'desc'; // Default sort order

    // Start building the query
    $transactions_query = Transaction::with(['items.product', 'customer', 'supplier', 'createdBy', 'updatedBy']);

    // Apply search filter
    if (!empty(trim($search_query))) {
        $transactions_query->where(function ($query) use ($search_query) {
            $query->where('invoice_bill_number', 'LIKE', '%' . $search_query . '%')
                  ->orWhere('transaction_type', 'LIKE', '%' . $search_query . '%')
                  ->orWhereHas('items.product', function ($productQuery) use ($search_query) {
                  $productQuery->where('name', 'LIKE', '%' . $search_query . '%');
              });
        });
        Logger::log("DEBUG: Applied search query: '{$search_query}'");
    }

    // Apply type filter
    if (!empty($filter_type)) {
        $transactions_query->where('transaction_type', $filter_type);
        Logger::log("DEBUG: Applied type filter: '{$filter_type}'");
    }

    // Apply status filter
    if (!empty($filter_status)) {
        $transactions_query->where('status', $filter_status);
        Logger::log("DEBUG: Applied status filter: '{$filter_status}'");
    }
    if (!empty($filter_date_range)) {
        $now = Carbon::now();
        Logger::log("Date Range Filter: $filter_date_range");

        switch ($filter_date_range) {
            case 'today':
                $transactions_query->whereDate('created_at', $now);
                break;
            case 'yesterday':
                $transactions_query->whereDate('created_at', $now->copy()->subDay());
                break;
            case 'week':
                $transactions_query->whereBetween('created_at', [
                    $now->copy()->startOfWeek(),
                    $now->copy()->endOfWeek()
                ]);
                break;
            case 'month':
                $transactions_query->whereBetween('created_at', [
                    $now->copy()->startOfMonth(),
                    $now->copy()->endOfMonth()
                ]);
                break;
            case 'year':
                $transactions_query->whereBetween('created_at', [
                    $now->copy()->startOfYear(),
                    $now->copy()->endOfYear()
                ]);
                break;
        }
    }

    // Apply sorting
    // Validate sort_by column to prevent SQL injection
    $allowed_sort_columns = [
        'id', 'transaction_type', 'transaction_date', 'invoice_bill_number',
        'total_amount', 'status', 'created_at', 'updated_at'
    ];
    if (!in_array($sort_by, $allowed_sort_columns)) {
        $sort_by = 'transaction_date'; // Fallback to default if invalid
    }

    // Validate sort_order
    if (!in_array(strtolower($sort_order), ['asc', 'desc'])) {
        $sort_order = 'desc'; // Fallback to default if invalid
    }

    $transactions_query->orderBy($sort_by, $sort_order);
    Logger::log("DEBUG: Applied sorting: '{$sort_by}' {$sort_order}");

    // --- DEBUGGING START ---
    // Get the SQL query string and its bindings for debugging
    try {
        $debug_sql = $transactions_query->toSql();
        $debug_bindings = $transactions_query->getBindings();
        Logger::log("DEBUG: SQL Query: " . $debug_sql);
        Logger::log("DEBUG: SQL Bindings: " . json_encode($debug_bindings));
    } catch (\Exception $e) {
        Logger::log("DEBUG: Could not get SQL query for debugging: " . $e->getMessage());
    }
    // --- DEBUGGING END ---


    $transactions_info = $transactions_query->get();

    Logger::log('DEBUG: Number of transactions retrieved in transactions_list: ' . $transactions_info->count());

    // Ensure transactions_info is an iterable collection even if empty
    if (is_null($transactions_info)) {
        $transactions_info = collect([]);
    }

    $this->view('staff/transactions_list', [
        'transactions_info' => $transactions_info,
        'search_query' => $search_query, // Pass back to view
        'filter_type' => $filter_type,   // Pass back to view
        'filter_status' => $filter_status, // Pass back to view
        'filter_date_range' => $filter_date_range,
        'sort_by' => $sort_by,           // Pass back to view
        'sort_order' => $sort_order,     // Pass back to view
        'transaction_types_list' => ['Sale', 'Purchase', 'Customer Return', 'Supplier Return', 'Stock Adjustment'], // For filter dropdown
        'transaction_statuses_list' => ['Pending','Completed', 'Cancelled'], // For filter dropdown
    ], 'staff');
  }
  public function sales_report() {
    Logger::log('Reached Sales Report');

    // --- DEBUGGING START ---
    Logger::log('DEBUG: Raw GET parameters: ' . json_encode($_GET));
    // --- DEBUGGING END ---

    // Retrieve search, filter, and sort parameters from GET request
    $search_query = trim((string) $this->input('search_query'));
    $filter_status = $this->input('filter_status');
    $filter_date_range = $this->input('filter_date_range');

    $sort_by = $this->input('sort_by') ?: 'transaction_date'; // Default sort column
    $sort_order = $this->input('sort_order') ?: 'desc'; // Default sort order

    // Start building the query
    $transactions_query = Transaction::with(['customer', 'supplier', 'createdBy', 'updatedBy']);

    // Apply search filter
    if (!empty(trim($search_query))) {
        $transactions_query->where(function ($query) use ($search_query) {
            $query->where('invoice_bill_number', 'LIKE', '%' . $search_query . '%');
        });
        Logger::log("DEBUG: Applied search query: '{$search_query}'");
    }

    // Apply status filter
    if (!empty($filter_status)) {
        $transactions_query->where('status', $filter_status);
        Logger::log("DEBUG: Applied status filter: '{$filter_status}'");
    }
    if (!empty($filter_date_range)) {
        $now = Carbon::now();
        Logger::log("Date Range Filter: $filter_date_range");

        switch ($filter_date_range) {
            case 'today':
                $transactions_query->whereDate('created_at', $now);
                break;
            case 'yesterday':
                $transactions_query->whereDate('created_at', $now->copy()->subDay());
                break;
            case 'week':
                $transactions_query->whereBetween('created_at', [
                    $now->copy()->startOfWeek(),
                    $now->copy()->endOfWeek()
                ]);
                break;
            case 'month':
                $transactions_query->whereBetween('created_at', [
                    $now->copy()->startOfMonth(),
                    $now->copy()->endOfMonth()
                ]);
                break;
            case 'year':
                $transactions_query->whereBetween('created_at', [
                    $now->copy()->startOfYear(),
                    $now->copy()->endOfYear()
                ]);
                break;
        }
    }

    // Apply sorting
    // Validate sort_by column to prevent SQL injection
    $allowed_sort_columns = [
        'id', 'transaction_date', 'invoice_bill_number',
        'total_amount', 'status', 'created_at', 'updated_at'
    ];
    if (!in_array($sort_by, $allowed_sort_columns)) {
        $sort_by = 'transaction_date'; // Fallback to default if invalid
    }

    // Validate sort_order
    if (!in_array(strtolower($sort_order), ['asc', 'desc'])) {
        $sort_order = 'desc'; // Fallback to default if invalid
    }

    $transactions_query->orderBy($sort_by, $sort_order);
    Logger::log("DEBUG: Applied sorting: '{$sort_by}' {$sort_order}");

    // --- DEBUGGING START ---
    // Get the SQL query string and its bindings for debugging
    try {
        $debug_sql = $transactions_query->toSql();
        $debug_bindings = $transactions_query->getBindings();
        Logger::log("DEBUG: SQL Query: " . $debug_sql);
        Logger::log("DEBUG: SQL Bindings: " . json_encode($debug_bindings));
    } catch (\Exception $e) {
        Logger::log("DEBUG: Could not get SQL query for debugging: " . $e->getMessage());
    }
    // --- DEBUGGING END ---


    $transactions_info = $transactions_query->get();

    Logger::log('DEBUG: Number of transactions retrieved in transactions_list: ' . $transactions_info->count());

    // Ensure transactions_info is an iterable collection even if empty
    if (is_null($transactions_info)) {
        $transactions_info = collect([]);
    }

    $this->view('staff/sales_report', [
        'transactions_info' => $transactions_info,
        'search_query' => $search_query, // Pass back to view
        'filter_status' => $filter_status, // Pass back to view
        'filter_date_range' => $filter_date_range,
        'sort_by' => $sort_by,           // Pass back to view
        'sort_order' => $sort_order,     // Pass back to view
        'transaction_statuses_list' => ['Pending','Completed', 'Cancelled'], // For filter dropdown
    ], 'staff');
  }

  public function customers_list() {
    Logger::log('Reached List of Customers');

    // Get search query
    $search_query = trim((string) $this->input('search_query'));

    // Start query
    $customers_query = Customer::select(
        'id',
        'customer_type',
        'company_name',
        'contact_first_name',
        'contact_middle_name',
        'contact_last_name',
        'email',
        'phone_number',
        'address',
        'created_at',
        'updated_at'
    );

    // Apply search filter (only on specified fields)
    if (!empty($search_query)) {
        $customers_query->where(function ($q) use ($search_query) {
            $q->where('company_name', 'LIKE', '%' . $search_query . '%')
              ->orWhere('contact_first_name', 'LIKE', '%' . $search_query . '%')
              ->orWhere('contact_middle_name', 'LIKE', '%' . $search_query . '%')
              ->orWhere('contact_last_name', 'LIKE', '%' . $search_query . '%')
              ->orWhere('customer_type', 'LIKE', '%' . $search_query . '%')
              ->orWhere('email', 'LIKE', '%' . $search_query . '%');
        });
        Logger::log("DEBUG: Applied customer search query: '{$search_query}'");
    }

    // Get results
    $customers_info = $customers_query->get();

    Logger::log("DEBUG: Number of customers retrieved: " . $customers_info->count());

    // Ensure iterable
    if (is_null($customers_info)) {
        $customers_info = collect([]);
    }

    // Pass search_query back to view
    $this->view('staff/customers_list', [
        'customers_info' => $customers_info,
        'search_query' => $search_query
    ], 'staff');
    }

public function suppliers_list() {
    Logger::log('Reached List of Suppliers');

    // Get search query
    $search_query = trim((string) $this->input('search_query'));

    // Build query
    $suppliers_query = Supplier::select(
        'id',
        'supplier_type',
        'company_name',
        'contact_first_name',
        'contact_middle_name',
        'contact_last_name',
        'email',
        'phone_number',
        'address',
        'created_at',
        'updated_at'
    );

    // Apply search filter
    if (!empty($search_query)) {
        $suppliers_query->where(function ($q) use ($search_query) {
            $q->where('supplier_type', 'LIKE', '%' . $search_query . '%')
              ->orWhere('company_name', 'LIKE', '%' . $search_query . '%')
              ->orWhere('contact_first_name', 'LIKE', '%' . $search_query . '%')
              ->orWhere('contact_middle_name', 'LIKE', '%' . $search_query . '%')
              ->orWhere('contact_last_name', 'LIKE', '%' . $search_query . '%')
              ->orWhere('email', 'LIKE', '%' . $search_query . '%');
        });
        Logger::log("DEBUG: Applied supplier search query: '{$search_query}'");
    }

    // Fetch results
    $suppliers_info = $suppliers_query->get();

    Logger::log("DEBUG: Number of suppliers retrieved: " . $suppliers_info->count());

    // Ensure iterable
    if (is_null($suppliers_info)) {
        $suppliers_info = collect([]);
    }

    // Pass search_query back to view
    $this->view('staff/suppliers_list', [
        'suppliers_info' => $suppliers_info,
        'search_query'   => $search_query
    ], 'staff');
}

 public function categories_list() {
    Logger::log('Reached List of Categories');

    // Get and trim search query
    $search_query = trim((string) $this->input('search_query'));

    // Start query
    $category_query = Category::select(
        'id',
        'name',
        'description',
        'created_at',
        'updated_at'
    );

    // Apply search filter (only on name)
    if (!empty($search_query)) {
        $category_query->where('name', 'LIKE', '%' . $search_query . '%');
        Logger::log("DEBUG: Applied category search query: '{$search_query}'");
    }

    // Fetch results
    $category_info = $category_query->get();

    Logger::log("DEBUG: Number of categories retrieved: " . $category_info->count());

    // Ensure iterable
    if (is_null($category_info)) {
        $category_info = collect([]);
    }

    // Pass search_query back to view
    $this->view('staff/categories_list', [
        'category_info' => $category_info,
        'search_query'  => $search_query
    ], 'staff');
}


  public function brands_list() {
    Logger::log('Reached List of Brands');

    // Get search query
    $search_query = $this->input('search_query');

    // Start building the query
    $brand_query = Brand::select(
        'id',
        'name',
        'website',
        'contact_email',
        'created_at',
        'updated_at'
    );

    // Apply search filter
    if (!empty($search_query)) {
        $brand_query->where(function ($q) use ($search_query) {
            $q->where('name', 'LIKE', '%' . $search_query . '%')
              ->orWhere('contact_email', 'LIKE', '%' . $search_query . '%');
        });
        Logger::log("DEBUG: Applied brand search query: '{$search_query}'");
    }

    // Fetch data
    $brand_info = $brand_query->get();

    Logger::log("DEBUG: Number of brands retrieved: " . $brand_info->count());

    // Ensure iterable
    if (is_null($brand_info)) {
        $brand_info = collect([]);
    }

    // Pass search_query back to view to keep the input filled
    $this->view('staff/brands_list', [
        'brand_info' => $brand_info,
        'search_query' => $search_query
    ], 'staff');
}


  public function edit_user_account() {
   Logger::log('Attempting to retrieve current user account for editing.');

    if (!isset($_SESSION['user']['id'])) {
        Logger::log('USER_EDIT_ACCOUNT_FAILED: No user ID found in session. Redirecting to login.');
        $this->view('login/login', ['error' => 'Please log in to edit your account.'],'default');
        return;
    }

    $currentUserId = $_SESSION['user']['id'];

    $user_account = User::select(
        'id',
        'username',
        'email',
        'first_name',
        'last_name',
        'middle_name',
        'birthdate',
        'type',
        'created_at'
    )
    ->find($currentUserId);

    if (!$user_account) {
        Logger::log("USER_EDIT_ACCOUNT_FAILED: User ID $currentUserId not found in database.");
        session_destroy();
        $_SESSION['error_message']="Your session is invalid. Please login again.";
        header('Location: /login/login');
        exit();;
    }

    Logger::log("USER_EDIT_ACCOUNT_SUCCESS: Retrieved account info for user ID $currentUserId.");
    $this->view('staff/edit_user_account', ['user_account' => $user_account],'staff');
}
    public function update_user_account()
    {
        Logger::log('Attempting to update user account via self-edit form.');

        // 1. Basic Security & Session Check
        if (!isset($_SESSION['user']['id'])) {
            Logger::log('UPDATE_FAILED: No user ID in session. Redirecting to login.');
            $_SESSION['error_message']="Your session has expired. Please login again.";
            header('Location: /login/login');
            exit();
        }

        $currentUserId = $_SESSION['user']['id'];

        // 2. Retrieve Input Data
        $submittedUserId = $this->input('user_id'); // Hidden field from form
        $email           = $this->input('email');
        $first_name      = $this->input('first_name');
        $last_name       = $this->input('last_name');
        $middle_name     = $this->input('middle_name'); // Optional
        $birthdate       = $this->input('birthdate');   // Optional

        // 3. Authorization Check: Ensure the user is updating their own account
        if ($currentUserId != $submittedUserId) {
            Logger::log("SECURITY_ALERT: User ID $currentUserId attempted to update account ID $submittedUserId.");
            // Re-fetch current user data for display in case of unauthorized access attempt
            $_SESSION['error_message']="You are not authorized for this action.";
            header('Location: /staff/edit_user_account');
            exit();
        }

        // 4. Retrieve the User Model instance
        $user = User::find($currentUserId);

        if (!$user) {
            Logger::log("UPDATE_FAILED: User ID $currentUserId not found in database for update.");
            session_destroy();
            $_SESSION['error_message']="Please login again.";
            exit();
        }

        // 5. Input Validation
        $errors = [];

        if (empty($email)) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format.';
        } else {
            // Check if email already exists for another user
            $existingUserWithEmail = User::where('email', $email)
                                         ->where('id', '!=', $currentUserId)
                                         ->first();
            if ($existingUserWithEmail) {
                $errors[] = 'Email already taken by another account.';
            }
        }

        if (empty($first_name)) {
            $errors[] = 'First Name is required.';
        }
        if (empty($last_name)) {
            $errors[] = 'Last Name is required.';
        }

        // Add more validation rules as needed (e.g., birthdate format)

        if (!empty($errors)) {
            Logger::log("UPDATE_FAILED: Validation errors for User ID $currentUserId: " . implode(', ', $errors));

            // Store error messages in session
            $_SESSION['error_message'] = implode('<br>', $errors);

            // Optionally: store old form data too (e.g., for repopulating fields)
            $_SESSION['old_input'] = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                // Add other fields as needed
            ];

            // Redirect back to the form
            header('Location: /edit_user_account?id=' . urlencode($currentUserId));
            exit;
        }


        // --- REVISED LOGIC STARTS HERE ---

        // 6. Assign new values to the User Model properties
        //    Perform normalization (empty string to null) directly during assignment.
        //    The ORM's isDirty() method will track changes from this point.
        $user->email = $email;
        $user->first_name = $first_name;
        $user->last_name = $last_name;
        $user->middle_name = !empty($middle_name) ? $middle_name : null;
        $user->birthdate = !empty($birthdate) ? $birthdate : null; // Assuming $birthdate is 'YYYY-MM-DD' or empty string

        // 7. Check if any actual changes were made using the ORM's isDirty()
        //    This method compares the current state of the model with its original state.
        if (!$user->isDirty()) {
            Logger::log("UPDATE_INFO: User ID $currentUserId submitted form with no changes.");
            $_SESSION['warning_message']="No changes were made.";
            $this->view('staff/edit_user_account', [
                'user_account' => $user // Pass the current user object back (it has the same values)
            ], 'staff');
            return; // Exit as no update is needed
        }

        try {
            // 8. Save Changes (only if isDirty() was true)
            $user->save();
            Logger::log("UPDATE_SUCCESS: User ID $currentUserId account updated successfully.");
            $_SESSION['success_message']="Successfully updated user info.";
            $this->view('staff/edit_user_account', [
                'user_account' => $user // Pass the updated user object back
            ], 'staff');
            return;
        } catch (\Exception $e) {
            Logger::log("UPDATE_DB_ERROR: User ID $currentUserId - " . $e->getMessage());
            $_SESSION['error_message']="Cannot update user info:" . $e->getMessage();
            $this->view('staff/edit_user_account', [
                'user_account' => $user // Pass the user object back
            ], 'staff');
            return;
        }
    }
}
