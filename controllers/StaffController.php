<?php

namespace Controllers;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Connection;

use Models\User;
use Models\Transaction;
use Models\Customer;
use Models\Supplier;
use Models\Product;
use Models\Category;
use Models\Brand;


require_once 'vendor/autoload.php';


class StaffController extends Controller {


  public function dashboard() {
    Logger::log("Reached Dashboard.");
    $first_data = $this->getUserInfoAndCount();
    $second_data = $this->getProductAndTransactionCount();
    $data = array_merge($first_data, $second_data);
    $this->view('staff/dashboard', $data,'staff');
  }

  public function user_list() {
    Logger::log('Reached List of Users');
    $user_info = User::select('id', 'username', 'email', 'first_name', 'last_name','middle_name','birthdate','created_at','type')->get(); 
    $this->view('staff/user_list', ['user_info' => $user_info],'staff');
  }

  public function products_list() {
    Logger::log('Reached List of Products');
    $products_info = Product::select('id', 
    'sku', 
    'name', 
    'description', 
    'category_id',
    'brand_id',
    'unit_price',
    'cost_price',
    'current_stock',
    'reorder_level',
    'is_serialized',
    'is_active',
    'location_aisle',
    'location_bin',
    'created_at')->get(); 
    $this->view('staff/products_list', ['products_info' => $products_info],'staff');
  }

  public function transactions_list() {
    Logger::log('Reached List of Transactions');

    // Eager load customer and supplier relationships to prevent "attempt to read property of null" errors
    // when accessing $transaction->customer->name or $transaction->supplier->name in the view.
    $transactions_info = Transaction::select(
        'id',
        'transaction_type',
        'customer_id',
        'supplier_id',
        'transaction_date',
        'invoice_bill_number',
        'total_amount',
        'status',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
        'created_at',
        'updated_at'
    )
    ->with(['customer', 'supplier', 'createdBy', 'updatedBy']) // Added createdBy, updatedBy here for full consistency with view
    ->get();

    // Add this line to log the count of retrieved transactions
    Logger::log('DEBUG: Number of transactions retrieved in transactions_list: ' . $transactions_info->count());

    // Although Transaction::get() should always return a Collection (even an empty one),
    // this check provides an extra layer of robustness in case of unexpected scenarios
    // where it might somehow result in null before reaching the view.
    // However, Eloquent's get() method always returns a Collection, so is_null is unlikely to be true.
    // It's more common to check ->isEmpty() or ->count() directly on the collection.
    if (is_null($transactions_info)) {
        $transactions_info = collect([]); // Initialize an empty Eloquent Collection
    }

    $this->view('staff/transactions_list', ['transactions_info' => $transactions_info], 'staff');
}

  public function customers_list() {
    Logger::log(message: 'Reached List of Customers');
    $customers_info = Customer::select(
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
        'updated_at',
        )->get(); 
    $this->view('staff/customers_list', ['customers_info' => $customers_info],'staff');
  }

  public function suppliers_list() {
        Logger::log('Reached List of Suppliers'); // Changed named parameter for broader compatibility

        // Ensure all relevant columns are selected, including contact_middle_name
        $suppliers_info = Supplier::select(
            'id',
            'supplier_type',
            'company_name',
            'contact_first_name',
            'contact_middle_name', // Included contact_middle_name
            'contact_last_name',
            'email',
            'phone_number',
            'address',           // Single address field
            'created_at',
            'updated_at'
        )->get();

        $this->view('staff/suppliers_list', [
            'suppliers_info' => $suppliers_info
        ], 'staff');
    }
  public function categories_list() {
    Logger::log(message: 'Reached List of Categories');
    $category_info = Category::select(
      'id',
        'name',
        'description',
        'created_at',
        'updated_at',
        )->get(); 
    $this->view('staff/categories_list', ['category_info' => $category_info],'staff');
  }

  public function brands_list() {
    Logger::log(message: 'Reached List of Brands');
    $brand_info = Brand::select(
      'id',
        'name',
        'website',
        'contact_email',
        'created_at',
        'updated_at',
        )->get(); 
    $this->view('staff/brands_list', ['brand_info' => $brand_info],'staff');
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
        $this->view('login/login', ['error' => 'Your session is invalid. Please log in again.'],'default');
        return;
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
        $this->view('login/login', ['error' => 'Your session has expired. Please log in again.'], 'default'); // Consider 'default' layout for login
        return;
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
        $this->view('staff/edit_user_account', [
            'error' => 'You are not authorized to perform this action.',
            'user_account' => User::find($currentUserId)
        ], 'staff');
        return;
    }

    // 4. Retrieve the User Model instance
    $user = User::find($currentUserId);

    if (!$user) {
        Logger::log("UPDATE_FAILED: User ID $currentUserId not found in database for update.");
        session_destroy();
        $this->view('login/login', ['error' => 'User account not found. Please log in again.'], 'default'); // Consider 'default' layout
        return;
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
        $this->view('staff/edit_user_account', [
            'error' => implode('<br>', $errors),
            'user_account' => $user // Pass the current user object back to re-populate the form
        ], 'staff');
        return;
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
        $this->view('staff/edit_user_account', [
            'success_message' => 'No changes were made to your account.', // Use success_message for info
            'user_account' => $user // Pass the current user object back (it has the same values)
        ], 'staff');
        return; // Exit as no update is needed
    }

    try {
        // 8. Save Changes (only if isDirty() was true)
        $user->save();
        Logger::log("UPDATE_SUCCESS: User ID $currentUserId account updated successfully.");
        $this->view('staff/edit_user_account', [
            'success_message' => 'Account information updated successfully!',
            'user_account' => $user // Pass the updated user object back
        ], 'staff');
    } catch (\Exception $e) {
        Logger::log("UPDATE_DB_ERROR: User ID $currentUserId - " . $e->getMessage());
        $this->view('staff/edit_user_account', [
            'error' => 'An error occurred while updating your account. Please try again.',
            'user_account' => $user // Pass the user object back
        ], 'staff');
    }
}
}
