<?php
namespace Controllers;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Connection; // Assuming you still use this for DB connection init if not handled by Eloquent
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Capsule\Manager as DB;
use Models\Transaction;
use Models\Customer; // For dropdowns
use Models\Supplier; // For dropdowns
use Models\ProductInstance;
use Models\TransactionItem;
use Models\User;     // For createdBy/updatedBy relationships

// As previously discussed, 'vendor/autoload.php' should ideally be in your main application bootstrap
require_once 'vendor/autoload.php'; // This should be handled by your application's entry point

class StaffTransactionController extends Controller {

    /**
     * Helper to get current user ID. Replace with your actual authentication method.
     * @return int|null
     */
    private function getCurrentUserId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Displays a list of all transactions.
     * Accessible via /staff/transactions_list
     *
     * @return void
     */
    public function index() {
        Logger::log('TRANSACTION_INDEX: Displaying list of transactions.');
        $transactions = Transaction::with(['customer', 'supplier', 'createdBy', 'updatedBy'])->orderBy('transaction_date', 'desc')->get();
        $this->view('staff/transactions/index', ['transactions' => $transactions], 'staff');
    }

    /**
     * Displays a single transaction's details, including its items.
     * Accessible via /staff/transactions/show/{id}
     *
     * @param int $id The ID of the transaction to show.
     * @return void
     */
    public function show($id) {
        Logger::log("STAFF_TRANSACTIONS_SHOW: Attempting to display transaction ID: $id.");

        // *** THIS IS THE CRITICAL LINE ***
        // Eager load all relationships that your show.php view depends on.
        // 'items.product' is crucial for both $transaction->items->isEmpty() and $item->product->product_name
        $transaction = Transaction::with([
            'items.product', // Eager load transaction items and their associated products
            'customer',      // Eager load the customer if it's a sale/return
            'supplier',      // Eager load the supplier if it's a purchase/return
            'createdBy',     // Eager load the user who created the transaction
            'updatedBy'      // Eager load the user who last updated the transaction
        ])->find($id);

        if (!$transaction) {
            Logger::log("STAFF_TRANSACTIONS_SHOW_FAILED: Transaction ID $id not found.");
            // Use view instead of header redirect
            $this->view('staff/transactions_list', ['error' => 'Transaction not found.'], 'staff');
            return;
        }

        // Pass any success/error messages that came via the redirect's GET parameters
        $success_message = $_GET['success_message'] ?? null;
        $error_message = $_GET['error'] ?? null;

        Logger::log("STAFF_TRANSACTIONS_SHOW_SUCCESS: Displaying transaction ID: $id.");

        // Render the show.php view, passing the fully-loaded transaction object
        $this->view('staff/transactions/show', [
            'transaction' => $transaction,
            'success_message' => $success_message,
            'error' => $error_message,
        ], 'staff');
    }

    /**
     * Displays the form to add a new transaction.
     * Accessible via /staff/transactions/add
     *
     * @return void
     */
   public function add() {
        Logger::log('TRANSACTION_ADD: Displaying new transaction form.');

        // Remove ->toArray() here:
        $customers = Customer::all(); // This will return an Eloquent Collection
        $suppliers = Supplier::all(); // This will return an Eloquent Collection

        $this->view('staff/transactions/add', [
            'customers' => $customers,
            'suppliers' => $suppliers,
            'transaction' => new Transaction() // Pass an empty Transaction model
            // Using `(object)[]` can also work but `new Transaction()` is cleaner
        ], 'staff');
    }


    /**
     * Handles the POST request to store a new transaction in the database.
     * Accessible via /staff/transactions/store
     *
     * @return void
     */
    public function store() {
        Logger::log('TRANSACTION_STORE: Attempting to store new transaction.');

        // 1. Retrieve Input Data
        $transaction_type      = $this->input('transaction_type');
        $customer_id           = $this->input('customer_id');
        $supplier_id           = $this->input('supplier_id');
        $transaction_date_str  = $this->input('transaction_date'); // Store as string for input/repopulation
        $status                = $this->input('status'); // 'Draft', 'Pending', 'Confirmed', 'Completed', 'Cancelled'
        $notes                 = $this->input('notes');
        $current_user_id       = $this->getCurrentUserId();
        Logger::log(message: 'DEBUG: Value of $current_user_id from getCurrentUserId(): ' . var_export($current_user_id, true));
        // 2. Validation
        Logger::log("DEBUG_INPUTS_INITIAL: transaction_type='{$transaction_type}', customer_id='" . var_export($customer_id, true) . "', supplier_id='" . var_export($supplier_id, true) . "'");
        $errors = [];

        if (empty($transaction_type)) $errors[] = 'Transaction Type is required.';
        if (!in_array($transaction_type, ['Purchase', 'Sale', 'Customer Return', 'Supplier Return','Stock Adjustment'])) $errors[] = 'Invalid Transaction Type.';
        if (empty($transaction_date_str)) $errors[] = 'Transaction Date is required.';
        if (!strtotime($transaction_date_str)) $errors[] = 'Transaction Date is invalid.';

        // Normalize transaction date for database storage and number generation
        $transaction_date_db = date('Y-m-d H:i:s', strtotime($transaction_date_str)); // For DB storage, include time if needed, or just Y-m-d

        // Conditional validation based on transaction type
        if ($transaction_type === 'Sale') {
            if (empty($customer_id)) $errors[] = 'Customer is required for sales.';
            $supplier_id = null; // Clear supplier for sales
        } elseif ($transaction_type === 'Purchase') {
            if (empty($supplier_id)) $errors[] = 'Supplier is required for purchases.';
            $customer_id = null; // Clear customer for purchases
        } elseif ($transaction_type === 'Customer Return' || $transaction_type === 'Supplier Return') {
            // For returns, either customer OR supplier is required. Not both.
            if (empty($customer_id) && empty($supplier_id)) {
                $errors[] = 'Either a Customer or a Supplier must be selected for a Return transaction.';
            } elseif (!empty($customer_id) && !empty($supplier_id)) {
                $errors[] = 'A Return transaction cannot be associated with both a Customer and a Supplier. Please select one or the other.';
            }
        } elseif ($transaction_type === 'Stock Adjustment') {
            $customer_id = null; // Clear both for adjustments
            $supplier_id = null;
        }
         Logger::log("DEBUG_AFTER_CLEARING_LOGIC: transaction_type='{$transaction_type}', customer_id='" . var_export($customer_id, true) . "', supplier_id='" . var_export($supplier_id, true) . "'");

        if (empty($status)) $errors[] = 'Status is required.';
        // UPDATED: Added 'Confirmed' to the allowed statuses
        if (!in_array($status, ['Draft', 'Pending', 'Confirmed', 'Completed', 'Cancelled'])) $errors[] = 'Invalid Status.';
        if (empty($current_user_id)) $errors[] = 'User ID not found. Please log in.';


        if (!empty($errors)) {
            Logger::log("TRANSACTION_STORE_FAILED: Validation errors: " . implode(', ', $errors));
            $customers = Customer::all()->toArray(); // Ensure array for view
            $suppliers = Supplier::all()->toArray(); // Ensure array for view
            $this->view('staff/transactions/add', [
                'error' => implode('<br>', $errors),
                'customers' => $customers,
                'suppliers' => $suppliers,
                'transaction' => (object)[ // Repopulate form with submitted data
                    'transaction_type' => $transaction_type,
                    'customer_id' => $customer_id,
                    'supplier_id' => $supplier_id,
                    'transaction_date' => $transaction_date_str, // Use original string for repopulation
                    'status' => $status,
                    'notes' => $notes,
                ]
            ], 'staff');
            return;
        }

        // 3. Generate Invoice/Bill Number based on the SELECTED transaction_date
        $prefix = '';
    if ($transaction_type === 'Sale') {
        $prefix = 'INV';
    } elseif ($transaction_type === 'Purchase') {
        $prefix = 'PO';
    } elseif ($transaction_type === 'Customer Return') { // <-- Match DB
        $prefix = 'CRET'; // Or 'RET' if you prefer a generic return prefix
    } elseif ($transaction_type === 'Supplier Return') { // <-- Match DB
        $prefix = 'SRET'; // Or 'RET' if you prefer a generic return prefix
    } elseif ($transaction_type === 'Stock Adjustment') { // <-- Match DB
        $prefix = 'ADJ';
    }

        $date_for_number = date('Ymd', strtotime($transaction_date_str)); // Date part of the invoice number
        $date_for_query = date('Y-m-d', strtotime($transaction_date_str)); // Date part for database query (e.g., '2025-07-26')

        // Find the highest sequence number for the chosen transaction date and prefix
        $lastTransactionForDate = Transaction::where('transaction_date', 'LIKE', $date_for_query . '%') // Query based on the date part chosen
                                            ->where('invoice_bill_number', 'LIKE', $prefix . '-' . $date_for_number . '-%')
                                            ->orderBy('invoice_bill_number', 'desc')
                                            ->first();

        $sequence = 1;
        if ($lastTransactionForDate && preg_match('/-(\d+)$/', $lastTransactionForDate->invoice_bill_number, $matches)) {
            $sequence = (int)$matches[1] + 1;
        }
        $generated_invoice_bill_number = $prefix . '-' . $date_for_number . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);

        // Double check for absolute uniqueness (rare but possible in race conditions without DB sequence)
        $attempts = 0;
        while (Transaction::where('invoice_bill_number', $generated_invoice_bill_number)->exists() && $attempts < 100) {
            $sequence++;
            $generated_invoice_bill_number = $prefix . '-' . $date_for_number . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
            $attempts++;
        }
        if ($attempts >= 100) {
             Logger::log("TRANSACTION_STORE_ERROR: Failed to generate unique invoice number after many attempts for type $transaction_type and date $transaction_date_str.");
             $customers = Customer::all()->toArray(); // Ensure array for view
             $suppliers = Supplier::all()->toArray(); // Ensure array for view
             $this->view('staff/transactions/add', [
                 'error' => 'Failed to generate a unique transaction number. Please try again or contact support.',
                 'customers' => $customers,
                 'suppliers' => $suppliers,
                 'transaction' => (object)[
                     'transaction_type' => $transaction_type,
                     'customer_id' => $customer_id,
                     'supplier_id' => $supplier_id,
                     'transaction_date' => $transaction_date_str,
                     'status' => $status,
                     'notes' => $notes,
                 ]
             ], 'staff');
             return;
        }


        // 4. Create and Save Transaction
        try {
            $transaction = new Transaction();
            $transaction->transaction_type = $transaction_type;
            $transaction->customer_id = $customer_id;
            $transaction->supplier_id = $supplier_id;
            $transaction->transaction_date = $transaction_date_db; // Use normalized date for DB
            $transaction->invoice_bill_number = $generated_invoice_bill_number;
            $transaction->total_amount = 0.00;
            $transaction->status = $status;
            $transaction->notes = $notes;
            $transaction->created_by_user_id = $current_user_id;
            $transaction->updated_by_user_id = $current_user_id;

            $transaction->save();

            Logger::log("TRANSACTION_STORE_SUCCESS: New transaction '{$transaction->invoice_bill_number}' (ID: {$transaction->id}, Type: {$transaction->transaction_type}) created successfully.");
            // Redirect to the show page to add items
            $this->view('staff/transactions/show', ['transaction' => $transaction, 'success_message' => 'Transaction created. Now add items.'], 'staff');
            return;

        } catch (\Exception $e) {
            Logger::log("TRANSACTION_STORE_DB_ERROR: Failed to add transaction - " . $e->getMessage());
            $customers = Customer::all()->toArray(); // Ensure array for view
            $suppliers = Supplier::all()->toArray(); // Ensure array for view
            $this->view('staff/transactions/add', [
                'error' => 'An error occurred while creating the transaction. Please try again. ' . $e->getMessage(),
                'customers' => $customers,
                'suppliers' => $suppliers,
                'transaction' => (object)[
                    'transaction_type' => $transaction_type,
                    'customer_id' => $customer_id,
                    'supplier_id' => $supplier_id,
                    'transaction_date' => $transaction_date_str, // Use original string for repopulation
                    'status' => $status,
                    'notes' => $notes,
                ]
            ], 'staff');
            return;
        }
    }

    /**
     * Displays the form to edit an existing transaction.
     * Accessible via /staff/transactions/edit/{id}
     *
     * @param int $id The ID of the transaction to edit.
     * @return void
     */
    // In StaffTransactionController.php (within the edit($id) method)

 public function edit($id) // Assuming $id is passed as a route parameter
    {
        Logger::log("TRANSACTION_EDIT: Attempting to retrieve transaction ID: $id for editing.");

        $transaction = Transaction::with([
            'items.product',
            'items.purchasedInstances',
            'items.soldInstances',
            'items.returnedFromCustomerInstances',
            'items.returnedToSupplierInstances',
            'items.adjustedInInstances',
            'items.adjustedOutInstances',
        ])->find($id);

        if (!$transaction) {
            Logger::log("TRANSACTION_EDIT_FAILED: Transaction ID $id not found.");
            // Use view instead of header redirect
            $this->view('staff/transactions_list', ['error' => 'Transaction not found for editing.'], 'staff');
            return;
        }

        $customers = Customer::all()->toArray(); // Convert to array
        $suppliers = Supplier::all()->toArray(); // Convert to array

        // Prepare available serial numbers for sale/return/adjustment outflow dropdowns
        // Convert these collections to arrays directly here.
        $available_serial_numbers_by_product = [];
        $potential_customer_return_serials_by_product = [];
        $potential_supplier_return_serials_by_product = [];
        $potential_adjusted_out_serials_by_product = [];

        foreach ($transaction->items as $item) {
            if ($item->product && $item->product->is_serialized) {
                $product_id = $item->product->id;

                // For Sales (products "In Stock")
                $available_serial_numbers_by_product[$product_id] = ProductInstance::where('product_id', $product_id)
                                                                ->where('status', 'In Stock')
                                                                ->get()
                                                                ->toArray(); // Convert to array here

                // For Customer Returns (products "Sold")
                $potential_customer_return_serials_by_product[$product_id] = ProductInstance::where('product_id', $product_id)
                                                                            ->where('status', 'Sold')
                                                                            ->get()
                                                                            ->toArray(); // Convert to array here

                // For Supplier Returns (products "In Stock" - usually implies returning existing stock)
                $potential_supplier_return_serials_by_product[$product_id] = ProductInstance::where('product_id', $product_id)
                                                                            ->where('status', 'In Stock')
                                                                            ->get()
                                                                            ->toArray(); // Convert to array here

                // For Stock Adjustment Outflow (products "In Stock")
                $potential_adjusted_out_serials_by_product[$product_id] = ProductInstance::where('product_id', $product_id)
                                                                        ->where('status', 'In Stock')
                                                                        ->get()
                                                                        ->toArray(); // Convert to array here
            }
        }


        Logger::log("TRANSACTION_EDIT_SUCCESS: Transaction ID: $id retrieved.");
        $this->view('staff/transactions/edit', [
            'transaction' => $transaction,
            'customers' => $customers,
            'suppliers' => $suppliers,
            'available_serial_numbers_by_product' => $available_serial_numbers_by_product,
            'potential_customer_return_serials_by_product' => $potential_customer_return_serials_by_product,
            'potential_supplier_return_serials_by_product' => $potential_supplier_return_serials_by_product,
            'potential_adjusted_out_serials_by_product' => $potential_adjusted_out_serials_by_product,
            // Pass error_data if you were redirecting from a validation error in update()
            // 'error_data' => $_SESSION['error_data'] ?? [],
            // 'error' => $_SESSION['error_message'] ?? '',
            // 'success_message' => $_SESSION['success_message'] ?? '',
        ], 'staff');

        // Clear session messages and error data after displaying
        // unset($_SESSION['error_data']);
        // unset($_SESSION['error_message']);
        // unset($_SESSION['success_message']);
    }
    /**
     * Handles the POST request to update an existing transaction in the database.
     * Accessible via /staff/transactions/update
     *
     * @return void
     */
    
    public function update($id = null)
    {
        Logger::log('TRANSACTION_UPDATE: Attempting to update transaction.');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = 'Invalid request method.';
            header('Location: /staff/transactions_list'); // Changed: Using header for redirect
            exit(); // Changed: Exit after redirect
        }

        $id = $_POST['id'] ?? $id;

        if (empty($id)) {
            Logger::log('ERROR: Transaction ID not provided in POST data or route for update.');
            $_SESSION['error_message'] = 'Transaction ID is missing.';
            header('Location: /staff/transactions_list'); // Changed: Using header for redirect
            exit(); // Changed: Exit after redirect
        }

        Logger::log("DEBUG: Final transaction ID being used for update: {$id}");

        $validationErrors = [];
        $submittedSerialsByItemId = []; // To store submitted serials for sticky form

        try {
            DB::beginTransaction(); // Start a database transaction

            $transaction = Transaction::with(['items.product', 'items.purchasedInstances', 'items.soldInstances', 'items.returnedFromCustomerInstances', 'items.returnedToSupplierInstances', 'items.adjustedInInstances', 'items.adjustedOutInstances'])->find($id);

            if (!$transaction) {
                // This error log might be misleading if it implies the transaction isn't found for validation.
                // It means the $transaction object itself is null here.
                Logger::log("ERROR: Transaction ID {$id} not found for update.");
                $_SESSION['error_message'] = 'Transaction not found.';
                DB::rollBack();
                header('Location: /staff/transactions_list'); // Changed: Using header for redirect
                exit(); // Changed: Exit after redirect
            }

            Logger::log("DEBUG: Final transaction ID being used for update: {$transaction->id}");

            $oldStatus = $transaction->status; // Store old status before updating
            $newStatus = $_POST['status'] ?? $oldStatus; // Get new status from form

            // Basic validation for required fields
            if (empty($_POST['transaction_type'])) {
                $validationErrors['transaction_type'] = 'Transaction Type is required.';
            }
            if (empty($_POST['transaction_date'])) {
                $validationErrors['transaction_date'] = 'Transaction Date is required.';
            }
            if (empty($_POST['status'])) {
                $validationErrors['status'] = 'Status is required.';
            }

            $transactionType = $_POST['transaction_type'];
            $customerId = $_POST['customer_id'] ?? null;
            $supplierId = $_POST['supplier_id'] ?? null;

            // Conditional validation for customer/supplier
            if (($transactionType == 'Sale' || $transactionType == 'Customer Return') && empty($customerId)) {
                $validationErrors['customer_id'] = 'Customer is required for Sale and Customer Return transactions.';
            }
            if (($transactionType == 'Purchase' || $transactionType == 'Supplier Return') && empty($supplierId)) {
                $validationErrors['supplier_id'] = 'Supplier is required for Purchase and Supplier Return transactions.';
            }

            // Validate Transaction Items and Serial Numbers
            if (!isset($_POST['item_quantities']) || !is_array($_POST['item_quantities'])) {
                // This should not happen if items are pre-populated from transaction->items
                $validationErrors['items'] = 'No transaction items found.';
            } else {
                foreach ($transaction->items as $item) {
                    $product = $item->product;
                    $itemId = $item->id;
                    $quantity = $item->quantity;

                    // Serialized product logic
                    if ($product->is_serialized) {
                        $serialsInput = [];
                        $adjustmentDirection = '';

                        if ($transactionType == 'Purchase') {
                            $serialsInput = $_POST['serial_numbers'][$itemId] ?? [];
                        } elseif ($transactionType == 'Sale') {
                            $serialsInput = $_POST['selected_serial_numbers'][$itemId] ?? [];
                        } elseif ($transactionType == 'Customer Return') {
                            $serialsInput = $_POST['returned_serial_numbers'][$itemId] ?? [];
                        } elseif ($transactionType == 'Supplier Return') {
                            $serialsInput = $_POST['supplier_returned_serial_numbers'][$itemId] ?? [];
                        } elseif ($transactionType == 'Stock Adjustment') {
                            $adjustmentDirection = $_POST['item_adjustment_direction'][$itemId] ?? '';
                            if ($adjustmentDirection == 'inflow') {
                                $serialsInput = $_POST['adjusted_in_serial_numbers'][$itemId] ?? [];
                            } elseif ($adjustmentDirection == 'outflow') {
                                $serialsInput = $_POST['adjusted_out_serial_numbers'][$itemId] ?? [];
                            }
                            if (empty($adjustmentDirection) && in_array($newStatus, ['Pending', 'Confirmed', 'Completed'])) {
                                 $validationErrors["item_adjustment_direction_{$itemId}"] = 'Adjustment direction is required for serialized stock adjustment items.';
                            }
                        }

                        // Store for sticky form
                        if (!empty($serialsInput)) {
                            // Trim and filter empty serials for validation purposes, but store raw for sticky form if needed.
                            $filteredSerialsInput = array_filter(array_map('trim', $serialsInput));
                            $submittedSerialsByItemId[$itemId] = $filteredSerialsInput;

                            Logger::log("DEBUG_VALIDATION: Item ID {$itemId}, Product '{$product->name}' (Serialized). Expected Quantity: {$quantity}, Submitted Serials Count: " . count($filteredSerialsInput) . ". Submitted Serials: " . json_encode($filteredSerialsInput));

                            // Only validate serial count if status is not Draft or Cancelled, and is a serialized product.
                            // The front-end disables fields for 'Cancelled', so no submission.
                            if (in_array($newStatus, ['Pending', 'Confirmed', 'Completed'])) {
                                if (count($filteredSerialsInput) !== (int)$quantity) {
                                    $_SESSION['serial_number_validation_errors'][$itemId][] = "Expected {$quantity} serial numbers, but " . count($filteredSerialsInput) . " provided for item '{$product->name}'.";
                                    $validationErrors['serial_numbers_count'] = true;
                                } else {
                                    $uniqueSerials = [];
                                    foreach ($filteredSerialsInput as $serial) {
                                        if (empty($serial)) {
                                            $_SESSION['serial_number_validation_errors'][$itemId][] = "Serial number cannot be empty for item '{$product->name}'.";
                                            $validationErrors['serial_numbers_empty'] = true;
                                            continue;
                                        }
                                        if (in_array($serial, $uniqueSerials)) {
                                            $_SESSION['serial_number_validation_errors'][$itemId][] = "Duplicate serial number '{$serial}' found for item '{$product->name}'.";
                                            $validationErrors['serial_numbers_duplicate'] = true;
                                        }
                                        $uniqueSerials[] = $serial;
                                    }

                                    // Further validation based on transaction type for serialized items
                                    if ($transactionType == 'Sale') {
                                        $existingInstances = ProductInstance::where('product_id', $product->id)
                                                                            ->whereIn('serial_number', $uniqueSerials)
                                                                            ->get()
                                                                            ->keyBy('serial_number');

                                        foreach ($uniqueSerials as $serial) {
                                            $instance = $existingInstances->get($serial);
                                            if (!$instance || $instance->status !== 'In Stock') {
                                                $_SESSION['serial_number_validation_errors'][$itemId][] = "Serial number '{$serial}' for item '{$product->name}' is not 'In Stock' for sale.";
                                                $validationErrors['serial_numbers_invalid_status'] = true;
                                            }
                                            // Also prevent selling the same instance multiple times in one transaction
                                            if ($instance && isset($soldSerialsInTransaction[$instance->id])) {
                                                $_SESSION['serial_number_validation_errors'][$itemId][] = "Serial number '{$serial}' for item '{$product->name}' is selected multiple times within this transaction.";
                                                $validationErrors['serial_numbers_duplicate_in_transaction'] = true;
                                            }
                                            $soldSerialsInTransaction[$instance->id] = true;
                                        }
                                    } elseif ($transactionType == 'Purchase') {
                                        foreach ($uniqueSerials as $serial) {
                                            if (ProductInstance::where('serial_number', $serial)->exists()) {
                                                $_SESSION['serial_number_validation_errors'][$itemId][] = "Serial number '{$serial}' for item '{$product->name}' already exists in the system.";
                                                $validationErrors['serial_numbers_exists'] = true;
                                            }
                                        }
                                    } elseif ($transactionType == 'Customer Return') {
                                        $existingInstances = ProductInstance::where('product_id', $product->id)
                                                                            ->whereIn('serial_number', $uniqueSerials)
                                                                            ->get()
                                                                            ->keyBy('serial_number');
                                        foreach ($uniqueSerials as $serial) {
                                            $instance = $existingInstances->get($serial);
                                            if (!$instance || $instance->status !== 'Sold') {
                                                $_SESSION['serial_number_validation_errors'][$itemId][] = "Serial number '{$serial}' for item '{$product->name}' was not found or not 'Sold' to be returned by a customer.";
                                                $validationErrors['serial_numbers_invalid_status'] = true;
                                            }
                                        }
                                    } elseif ($transactionType == 'Supplier Return') {
                                        $existingInstances = ProductInstance::where('product_id', $product->id)
                                                                            ->whereIn('serial_number', $uniqueSerials)
                                                                            ->get()
                                                                            ->keyBy('serial_number');
                                        foreach ($uniqueSerials as $serial) {
                                            $instance = $existingInstances->get($serial);
                                            if (!$instance || $instance->status !== 'In Stock') {
                                                $_SESSION['serial_number_validation_errors'][$itemId][] = "Serial number '{$serial}' for item '{$product->name}' was not found or not 'In Stock' to be returned to a supplier.";
                                                $validationErrors['serial_numbers_invalid_status'] = true;
                                            }
                                        }
                                    } elseif ($transactionType == 'Stock Adjustment') {
                                        if ($adjustmentDirection == 'inflow') {
                                            foreach ($uniqueSerials as $serial) {
                                                if (ProductInstance::where('serial_number', $serial)->exists()) {
                                                    $_SESSION['serial_number_validation_errors'][$itemId][] = "Serial number '{$serial}' for item '{$product->name}' already exists in the system and cannot be adjusted in.";
                                                    $validationErrors['serial_numbers_exists'] = true;
                                                }
                                            }
                                        } elseif ($adjustmentDirection == 'outflow') {
                                            $existingInstances = ProductInstance::where('product_id', $product->id)
                                                                                ->whereIn('serial_number', $uniqueSerials)
                                                                                ->get()
                                                                                ->keyBy('serial_number');
                                            foreach ($uniqueSerials as $serial) {
                                                $instance = $existingInstances->get($serial);
                                                if (!$instance || $instance->status !== 'In Stock') {
                                                    $_SESSION['serial_number_validation_errors'][$itemId][] = "Serial number '{$serial}' for item '{$product->name}' was not found or not 'In Stock' to be adjusted out.";
                                                    $validationErrors['serial_numbers_invalid_status'] = true;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // If there are validation errors, set session and redirect
            if (!empty($validationErrors)) {
                Logger::log("VALIDATION_ERROR: " . json_encode($validationErrors));
                $_SESSION['error_message'] = 'Please correct the errors below.';
                $_SESSION['error_data'] = [
                    'submitted_serial_numbers' => $submittedSerialsByItemId,
                    'item_adjustment_direction' => $_POST['item_adjustment_direction'] ?? [],
                    // Add other POST data you want to make sticky if necessary
                ];
                DB::rollBack();
                header('Location: /staff/transactions/edit/' . $transaction->id); // Changed: Using header for redirect
                exit(); // Changed: Exit after redirect
            }

            // Update transaction details
            $transaction->transaction_type = $transactionType;
            $transaction->customer_id = $customerId;
            $transaction->supplier_id = $supplierId;
            $transaction->transaction_date = $_POST['transaction_date'];
            $transaction->status = $newStatus;
            $transaction->notes = $_POST['notes'] ?? '';

            // Handle transition to 'Completed' status
            if ($newStatus === 'Completed' && $oldStatus !== 'Completed') {
                Logger::log("TRANSACTION_STATUS_CHANGE: Transaction #{$transaction->id} changing from '{$oldStatus}' to '{$newStatus}'. Processing stock updates.");

                foreach ($transaction->items as $item) {
                    $product = $item->product;
                    $quantity = $item->quantity;
                    $itemId = $item->id;

                    if ($product->is_serialized) {
                        $serialsToProcess = $submittedSerialsByItemId[$itemId] ?? [];
                        Logger::log("DEBUG_STOCK: Processing serialized product '{$product->name}' (ID: {$product->id}), Item ID: {$itemId}. Serials: " . json_encode($serialsToProcess));

                        foreach ($serialsToProcess as $serial) {
                            $productInstance = ProductInstance::firstOrNew(['serial_number' => $serial, 'product_id' => $product->id]);

                            if ($transactionType == 'Purchase') {
                                // For purchase, create new instances if they don't exist
                                $productInstance->product_id = $product->id; // Ensure product_id is set for new instances
                                $productInstance->serial_number = $serial;
                                $productInstance->status = 'In Stock';
                                $productInstance->purchase_transaction_item_id = $item->id;
                                $productInstance->save();
                                Logger::log("STOCK_UPDATE: Serial '{$serial}' set to 'In Stock' for Purchase (Product ID: {$product->id}).");

                            } elseif ($transactionType == 'Sale') {
                                // For sale, find existing instance and update status
                                $instanceToUpdate = ProductInstance::where('serial_number', $serial)
                                                                    ->where('product_id', $product->id)
                                                                    ->where('status', 'In Stock')
                                                                    ->first();
                                if ($instanceToUpdate) {
                                    $instanceToUpdate->status = 'Sold';
                                    $instanceToUpdate->sale_transaction_item_id = $item->id;
                                    $instanceToUpdate->save();
                                    Logger::log("STOCK_UPDATE: Serial '{$serial}' set to 'Sold' for Sale (Product ID: {$product->id}).");
                                } else {
                                    Logger::log("ERROR: Attempted to sell serial '{$serial}' for Product ID {$product->id} but it was not 'In Stock'. This should have been caught by validation.");
                                    // You might want to rollback here or throw an exception if this state is critical
                                }

                            } elseif ($transactionType == 'Customer Return') {
                                $instanceToUpdate = ProductInstance::where('serial_number', $serial)
                                                                    ->where('product_id', $product->id)
                                                                    ->where('status', 'Sold')
                                                                    ->first();
                                if ($instanceToUpdate) {
                                    $instanceToUpdate->status = 'In Stock';
                                    $instanceToUpdate->customer_return_transaction_item_id = $item->id;
                                    $instanceToUpdate->save();
                                    Logger::log("STOCK_UPDATE: Serial '{$serial}' set to 'In Stock' for Customer Return (Product ID: {$product->id}).");
                                } else {
                                    Logger::log("ERROR: Attempted to return serial '{$serial}' for Product ID {$product->id} but it was not 'Sold'. This should have been caught by validation.");
                                }

                            } elseif ($transactionType == 'Supplier Return') {
                                $instanceToUpdate = ProductInstance::where('serial_number', $serial)
                                                                    ->where('product_id', $product->id)
                                                                    ->where('status', 'In Stock')
                                                                    ->first();
                                if ($instanceToUpdate) {
                                    $instanceToUpdate->status = 'Returned to Supplier'; // Or 'Disposed', depending on your stock model
                                    $instanceToUpdate->supplier_return_transaction_item_id = $item->id;
                                    $instanceToUpdate->save();
                                    Logger::log("STOCK_UPDATE: Serial '{$serial}' set to 'Returned to Supplier' for Supplier Return (Product ID: {$product->id}).");
                                } else {
                                    Logger::log("ERROR: Attempted to return serial '{$serial}' to supplier for Product ID {$product->id} but it was not 'In Stock'. This should have been caught by validation.");
                                }

                            } elseif ($transactionType == 'Stock Adjustment') {
                                $adjustmentDirection = $_POST['item_adjustment_direction'][$itemId] ?? '';
                                if ($adjustmentDirection == 'inflow') {
                                    $productInstance->product_id = $product->id; // Ensure product_id is set for new instances
                                    $productInstance->serial_number = $serial;
                                    $productInstance->status = 'In Stock';
                                    $productInstance->adjusted_in_transaction_item_id = $item->id;
                                    $productInstance->save();
                                    Logger::log("STOCK_UPDATE: Serial '{$serial}' set to 'In Stock' for Adjustment Inflow (Product ID: {$product->id}).");
                                } elseif ($adjustmentDirection == 'outflow') {
                                    $instanceToUpdate = ProductInstance::where('serial_number', $serial)
                                                                        ->where('product_id', $product->id)
                                                                        ->where('status', 'In Stock')
                                                                        ->first();
                                    if ($instanceToUpdate) {
                                        $instanceToUpdate->status = 'Adjusted Out';
                                        $instanceToUpdate->adjusted_out_transaction_item_id = $item->id;
                                        $instanceToUpdate->save();
                                        Logger::log("STOCK_UPDATE: Serial '{$serial}' set to 'Adjusted Out' for Adjustment Outflow (Product ID: {$product->id}).");
                                    } else {
                                        Logger::log("ERROR: Attempted to adjust out serial '{$serial}' for Product ID {$product->id} but it was not 'In Stock'. This should have been caught by validation.");
                                    }
                                }
                            }
                        }
                        // After processing all instances for this item, recalculate and update product's current_stock
                        // For serialized products, current_stock should reflect actual 'In Stock' instances
                        $product->current_stock = ProductInstance::where('product_id', $product->id)
                                                                ->where('status', 'In Stock')
                                                                ->count();
                        $product->save();
                        Logger::log("PRODUCT_STOCK_UPDATE: Recalculated stock for Product '{$product->name}' (ID: {$product->id}). New stock: {$product->current_stock}.");

                    } else {
                        // Non-serialized product logic
                        Logger::log("DEBUG_STOCK: Processing non-serialized product '{$product->name}' (ID: {$product->id}), Item ID: {$itemId}. Quantity: {$quantity}");

                        if ($transactionType == 'Purchase' || $transactionType == 'Customer Return' || ($transactionType == 'Stock Adjustment' && ($_POST['item_adjustment_direction'][$itemId] ?? '') == 'inflow')) {
                            $product->current_stock += $quantity;
                            Logger::log("STOCK_UPDATE: Added {$quantity} to stock for non-serialized '{$product->name}'. New stock: {$product->current_stock}.");
                        } elseif ($transactionType == 'Sale' || $transactionType == 'Supplier Return' || ($transactionType == 'Stock Adjustment' && ($_POST['item_adjustment_direction'][$itemId] ?? '') == 'outflow')) {
                            $product->current_stock -= $quantity;
                            Logger::log("STOCK_UPDATE: Subtracted {$quantity} from stock for non-serialized '{$product->name}'. New stock: {$product->current_stock}.");
                        }
                        $product->save();
                        Logger::log("PRODUCT_STOCK_UPDATE: Updated stock for Product '{$product->name}' (ID: {$product->id}). New stock: {$product->current_stock}.");
                    }
                }
            } elseif ($newStatus === 'Cancelled' && $oldStatus !== 'Cancelled') {
                 // Revert stock changes if transaction goes from Completed to Cancelled
                if ($oldStatus === 'Completed') {
                    Logger::log("TRANSACTION_STATUS_CHANGE: Transaction #{$transaction->id} changing from 'Completed' to 'Cancelled'. Reverting stock.");
                    foreach ($transaction->items as $item) {
                        $product = $item->product;
                        $quantity = $item->quantity;
                        $itemId = $item->id;

                        if ($product->is_serialized) {
                            // Find instances associated with this item and revert their status
                            if ($transaction->transaction_type === 'Purchase') {
                                $instances = ProductInstance::where('purchase_transaction_item_id', $itemId)->get();
                                foreach ($instances as $instance) {
                                    $instance->status = 'Cancelled (from Purchase)'; // Or delete, depending on your business rules
                                    $instance->purchase_transaction_item_id = null; // Disassociate
                                    $instance->save();
                                }
                            } elseif ($transaction->transaction_type === 'Sale') {
                                $instances = ProductInstance::where('sale_transaction_item_id', $itemId)->get();
                                foreach ($instances as $instance) {
                                    $instance->status = 'In Stock'; // Revert to In Stock
                                    $instance->sale_transaction_item_id = null; // Disassociate
                                    $instance->save();
                                }
                            } elseif ($transaction->transaction_type === 'Customer Return') {
                                $instances = ProductInstance::where('customer_return_transaction_item_id', $itemId)->get();
                                foreach ($instances as $instance) {
                                    $instance->status = 'Sold'; // Revert to Sold
                                    $instance->customer_return_transaction_item_id = null; // Disassociate
                                    $instance->save();
                                }
                            } elseif ($transaction->transaction_type === 'Supplier Return') {
                                $instances = ProductInstance::where('supplier_return_transaction_item_id', $itemId)->get();
                                foreach ($instances as $instance) {
                                    $instance->status = 'In Stock'; // Revert to In Stock (from being returned to supplier)
                                    $instance->supplier_return_transaction_item_id = null; // Disassociate
                                    $instance->save();
                                }
                            } elseif ($transaction->transaction_type === 'Stock Adjustment') {
                                $adjustedInInstances = ProductInstance::where('adjusted_in_transaction_item_id', $itemId)->get();
                                foreach ($adjustedInInstances as $instance) {
                                    $instance->status = 'Cancelled (from Adjustment In)'; // Or delete
                                    $instance->adjusted_in_transaction_item_id = null;
                                    $instance->save();
                                }
                                $adjustedOutInstances = ProductInstance::where('adjusted_out_transaction_item_id', $itemId)->get();
                                foreach ($adjustedOutInstances as $instance) {
                                    $instance->status = 'In Stock'; // Revert to In Stock
                                    $instance->adjusted_out_transaction_item_id = null;
                                    $instance->save();
                                }
                            }
                             // Recalculate and update product's current_stock after reverting
                            $product->current_stock = ProductInstance::where('product_id', $product->id)
                                                                    ->where('status', 'In Stock')
                                                                    ->count();
                            $product->save();
                            Logger::log("PRODUCT_STOCK_REVERT: Recalculated stock for Product '{$product->name}' (ID: {$product->id}) after cancellation. New stock: {$product->current_stock}.");

                        } else {
                            // Non-serialized product stock reversion
                            if ($transaction->transaction_type == 'Purchase' || $transaction->transaction_type == 'Customer Return' || ($transaction->transaction_type == 'Stock Adjustment' && $item->adjustment_direction == 'inflow')) {
                                $product->current_stock -= $quantity; // Subtract if it was an addition
                                Logger::log("STOCK_REVERT: Subtracted {$quantity} from stock for non-serialized '{$product->name}' due to cancellation. New stock: {$product->current_stock}.");
                            } elseif ($transaction->transaction_type == 'Sale' || $transaction->transaction_type == 'Supplier Return' || ($transaction->transaction_type == 'Stock Adjustment' && $item->adjustment_direction == 'outflow')) {
                                $product->current_stock += $quantity; // Add back if it was a subtraction
                                Logger::log("STOCK_REVERT: Added {$quantity} to stock for non-serialized '{$product->name}' due to cancellation. New stock: {$product->current_stock}.");
                            }
                            $product->save();
                            Logger::log("PRODUCT_STOCK_REVERT: Updated stock for Product '{$product->name}' (ID: {$product->id}) after cancellation. New stock: {$product->current_stock}.");
                        }
                    }
                }
            }


            $transaction->save();

            DB::commit(); // Commit the transaction
            Logger::log('TRANSACTION_UPDATE_SUCCESS: Transaction ID ' . $transaction->id . ' updated successfully.');
            $_SESSION['success_message'] = 'Transaction updated successfully!';
            header('Location: /staff/transactions/edit/' . $transaction->id); // Changed: Using header for redirect
            exit(); // Changed: Exit after redirect

        } catch (Exception $e) {
            DB::rollBack(); // Rollback on error
            Logger::log('TRANSACTION_UPDATE_ERROR: ' . $e->getMessage());
            Logger::log('TRANSACTION_UPDATE_ERROR_TRACE: ' . $e->getTraceAsString());
            $_SESSION['error_message'] = 'Failed to update transaction: ' . $e->getMessage();
            $_SESSION['error_data'] = [
                'submitted_serial_numbers' => $submittedSerialsByItemId,
                'item_adjustment_direction' => $_POST['item_adjustment_direction'] ?? [],
                // Add other POST data you want to make sticky if necessary
            ];
            header('Location: /staff/transactions/edit/' . $id); // Changed: Using header for redirect
            exit(); // Changed: Exit after redirect
        }
    }

    /**
     * Handles the deletion of a transaction.
     * Accessible via /staff/transactions/delete/{id}
     *
     * @param int $id The ID of the transaction to delete.
     * @return void
     */
    public function delete($id) {
    Logger::log("TRANSACTION_DELETE: Attempting to delete transaction ID: $id");

    // Get the PDO instance and start the transaction
    $capsule = Connection::getCapsule();
    if (!$capsule) {
        // Handle case where capsule is null (e.g., connection not initialized)
        Logger::log("TRANSACTION_DELETE_FAILED: Database connection not initialized.");
        $_SESSION['error_message'] = 'Database error: Connection not initialized.';
        header('Location: /staff/transactions_list?error=' . urlencode($_SESSION['error_message']));
        exit();
    }
    $pdo = $capsule->getConnection()->getPdo();
    $pdo->beginTransaction(); // <--- Transaction started here

    // Eager load items and their related product instances for proper handling
    $transaction = Transaction::with([
        'items.product',
        'items.purchasedInstances',
        'items.soldInstances',
        'items.returnedFromCustomerInstances',
        'items.returnedToSupplierInstances',
        'items.adjustedInInstances',
        'items.adjustedOutInstances'
    ])->find($id);

    if (!$transaction) {
        Logger::log("TRANSACTION_DELETE_FAILED: Transaction ID $id not found for deletion.");
        $_SESSION['error_message'] = 'Transaction not found for deletion.';
        // IMPORTANT: Rollback before exiting on error path
        $pdo->rollBack();
        header('Location: /staff/transactions_list?error=' . urlencode($_SESSION['error_message']));
        exit();
    }

    // Option A (Safer): Prevent deletion if completed
    if ($transaction->status === 'Completed') {
        Logger::log("TRANSACTION_DELETE_PREVENTED: Cannot delete completed transaction ID: $id.");
        $_SESSION['error_message'] = 'Cannot delete a completed transaction. Change status to Cancelled or create a return/adjustment.';
        // IMPORTANT: Rollback before exiting on error path
        $pdo->rollBack();
        header('Location: /staff/transactions_list?error=' . urlencode($_SESSION['error_message']));
        exit();
    }

    try {
        $currentUserId = $this->getCurrentUserId();

        // Step 1: Handle associated Product Instances
        foreach ($transaction->items as $item) {
            $product = $item->product;

            if ($product->is_serialized) {
                $instancesToUpdate = collect();

                switch ($transaction->transaction_type) {
                    case 'Purchase':
                        $instancesToUpdate = $item->purchasedInstances;
                        break;
                    case 'Sale':
                        $instancesToUpdate = $item->soldInstances;
                        break;
                    case 'Customer Return':
                        $instancesToUpdate = $item->returnedFromCustomerInstances;
                        break;
                    case 'Supplier Return':
                        $instancesToUpdate = $item->returnedToSupplierInstances;
                        break;
                    case 'Stock Adjustment':
                        $instancesToUpdate = $item->adjustedInInstances->concat($item->adjustedOutInstances);
                        break;
                }

                foreach ($instancesToUpdate as $instance) {
                    $originalStatus = $instance->status;

                    switch ($transaction->transaction_type) {
                        case 'Purchase':
                            $instance->purchase_transaction_item_id = null;
                            $instance->status = 'Removed';
                            break;
                        case 'Sale':
                            $instance->sale_transaction_item_id = null;
                            $instance->status = 'In Stock';
                            break;
                        case 'Customer Return':
                            $instance->returned_from_customer_transaction_item_id = null;
                            $instance->status = 'Sold';
                            break;
                        case 'Supplier Return':
                            $instance->returned_to_supplier_transaction_item_id = null;
                            $instance->status = 'In Stock';
                            break;
                        case 'Stock Adjustment':
                            if ($instance->adjusted_in_transaction_item_id === $item->id) {
                                $instance->adjusted_in_transaction_item_id = null;
                                $instance->status = 'Removed';
                            } elseif ($instance->adjusted_out_transaction_item_id === $item->id) {
                                $instance->adjusted_out_transaction_item_id = null;
                                $instance->status = 'In Stock';
                            }
                            break;
                    }

                    $instance->updated_by_user_id = $currentUserId;
                    $instance->save();
                    Logger::log("ProductInstance {$instance->serial_number} (ID: {$instance->id}) status reverted from '{$originalStatus}' to '{$instance->status}' due to transaction deletion.");
                }
            } else {
                Logger::log("Non-serialized product '{$product->name}' associated with item {$item->id}. No explicit instance rollback needed.");
            }
        }

        // Step 2: Delete transaction items
        $transaction->items()->delete();
        Logger::log("TRANSACTION_DELETE_INFO: Deleted all items for transaction ID: {$transaction->id}.");

        // Step 3: Delete the transaction itself
        $transaction->delete();
        Logger::log("TRANSACTION_DELETE_SUCCESS: Transaction (ID: {$transaction->id}, Type: {$transaction->transaction_type}) deleted successfully.");

        $pdo->commit(); // <--- Transaction committed here on success
        $_SESSION['success_message'] = 'Transaction deleted successfully!';
        header('Location: /staff/transactions_list?success_message=' . urlencode($_SESSION['success_message']));
        exit();

    } catch (\Exception $e) {
        $pdo->rollBack(); // <--- Transaction rolled back here on failure
        Logger::log("TRANSACTION_DELETE_DB_ERROR: Failed to delete transaction ID {$id} - " . $e->getMessage());
        $_SESSION['error_message'] = 'An error occurred while deleting the transaction: ' . $e->getMessage();
        header('Location: /staff/transactions_list?error=' . urlencode($_SESSION['error_message']));
        exit();
    }
}

    private function handlePurchaseSerials(TransactionItem $item, array $submittedSerials, $originalTransactionStatus, $newTransactionStatus)
    {
        // Ensure purchasedInstances is a collection of objects before keyBy
        $existingInstances = $item->purchasedInstances->keyBy('serial_number');
        $serialsToKeep = [];

        foreach ($submittedSerials as $serialNumber) {
            $serialNumber = trim($serialNumber);
            if (empty($serialNumber)) continue;

            if ($existingInstances->has($serialNumber)) {
                $instance = $existingInstances->get($serialNumber);
                if ($newTransactionStatus === 'Completed' && $instance->status !== 'In Stock') {
                    $instance->status = 'In Stock';
                    $instance->updated_by_user_id = $this->getCurrentUserId();
                    $instance->updated_at = date('Y-m-d H:i:s');
                    $instance->save();
                    Logger::log("Updated status of existing purchased serial {$serialNumber} to 'In Stock'.");
                } elseif ($newTransactionStatus !== 'Completed' && $instance->status === 'In Stock') {
                    // CHANGE HERE: Use 'Pending Stock'
                    $instance->status = 'Pending Stock';
                    $instance->updated_by_user_id = $this->getCurrentUserId();
                    $instance->updated_at = date('Y-m-d H:i:s');
                    $instance->save();
                    Logger::log("Reverted status of existing purchased serial {$serialNumber} to 'Pending Stock'.");
                }
                $serialsToKeep[] = $serialNumber;
            } else {
                try {
                    ProductInstance::create([
                        'product_id' => $item->product_id,
                        'serial_number' => $serialNumber,
                        // CHANGE HERE: Use 'Pending Stock'
                        'status' => ($newTransactionStatus === 'Completed' ? 'In Stock' : 'Pending Stock'),
                        'purchase_transaction_item_id' => $item->id,
                        'created_by_user_id' => $this->getCurrentUserId(),
                        'updated_by_user_id' => $this->getCurrentUserId(),
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    Logger::log("Created new purchased serial: {$serialNumber} for item {$item->id}.");
                    $serialsToKeep[] = $serialNumber;
                } catch (\Exception $e) {
                    Logger::log('ERROR: Failed to create new ProductInstance for serial ' . $serialNumber . ': ' . $e->getMessage());
                    throw $e;
                }
            }
        }

        foreach ($existingInstances as $existingInstance) {
            if (!in_array($existingInstance->serial_number, $serialsToKeep)) {
                if ($existingInstance->status === 'In Stock' || $existingInstance->status === 'Pending Stock') { // Adjusted 'Pending Purchase' to 'Pending Stock'
                    // CHANGE HERE: Use 'Removed' or 'Scrapped' or 'In Stock' (if it's truly available again)
                    // 'Removed' is in your schema, implies it's no longer in stock due to cancellation.
                    $existingInstance->status = 'Removed'; // Or 'Scrapped' if that fits cancellation better
                    $existingInstance->purchase_transaction_item_id = null;
                    $existingInstance->updated_by_user_id = $this->getCurrentUserId();
                    $existingInstance->updated_at = date('Y-m-d H:i:s');
                    $existingInstance->save();
                    Logger::log("Existing purchased serial {$existingInstance->serial_number} marked as 'Removed' due to cancellation.");
                }
            }
        }
    }

    private function handleStockAdjustmentSerials(TransactionItem $item, ?string $adjustmentDirection, array $submittedInSerials, array $submittedOutSerials, $originalTransactionStatus, $newTransactionStatus)
    {
        $existingInInstances = $item->adjustedInInstances->keyBy('serial_number');
        $existingOutInstances = $item->adjustedOutInstances->keyBy('serial_number');

        // Revert any instances previously linked to this item if they are no longer part of the current submission
        $all_submitted_serials_for_item = array_merge($submittedInSerials, $submittedOutSerials);

        foreach ($existingInInstances as $existingInstance) {
            if (!in_array($existingInstance->serial_number, $all_submitted_serials_for_item)) {
                if ($existingInstance->status === 'Pending Stock' || $existingInstance->status === 'In Stock') {
                    $existingInstance->status = 'In Stock'; // Or 'Unlinked' or 'Pre-Adjustment'
                    $existingInstance->adjusted_in_transaction_item_id = null;
                    $existingInstance->updated_by_user_id = $this->getCurrentUserId();
                    $existingInstance->updated_at = date('Y-m-d H:i:s');
                    $existingInstance->save();
                    Logger::log("Existing adjusted-in serial {$existingInstance->serial_number} unlinked/reverted to 'In Stock' due to non-submission.");
                }
            }
        }
        foreach ($existingOutInstances as $existingInstance) {
            if (!in_array($existingInstance->serial_number, $all_submitted_serials_for_item)) {
                if ($existingInstance->status === 'Adjusted Out' || $existingInstance->status === 'Pending Stock') {
                    $existingInstance->status = 'In Stock';
                    $existingInstance->adjusted_out_transaction_item_id = null;
                    $existingInstance->updated_by_user_id = $this->getCurrentUserId();
                    $existingInstance->updated_at = date('Y-m-d H:i:s');
                    $existingInstance->save();
                    Logger::log("Existing adjusted-out serial {$existingInstance->serial_number} reverted to 'In Stock' due to non-submission.");
                }
            }
        }


        if ($adjustmentDirection === 'inflow') {
            foreach ($submittedInSerials as $serialNumber) {
                $serialNumber = trim($serialNumber);
                if (empty($serialNumber)) {
                    continue; // Skip empty serial numbers
                }

                // Check if this serial was previously linked as an "inflow" to this item
                if ($existingInInstances->has($serialNumber)) {
                    $instance = $existingInInstances->get($serialNumber);
                    // If transaction is completed, ensure status is 'In Stock'
                    if ($newTransactionStatus === 'Completed' && $instance->status !== 'In Stock') {
                        $instance->status = 'In Stock';
                        $instance->save();
                        Logger::log("Updated status of existing adjusted-in serial {$serialNumber} to 'In Stock'.");
                    } elseif ($newTransactionStatus !== 'Completed' && $instance->status === 'In Stock') {
                        // If transaction is not completed, and instance is In Stock, revert to Pending Stock
                        $instance->status = 'Pending Stock';
                        $instance->save();
                        Logger::log("Reverted status of existing adjusted-in serial {$serialNumber} to 'Pending Stock'.");
                    }
                    // No change needed if it's already in the correct state
                } else {
                    // This is a new serial for this 'inflow' adjustment item
                    try {
                        ProductInstance::create([
                            'product_id' => $item->product_id,
                            'serial_number' => $serialNumber,
                            'status' => ($newTransactionStatus === 'Completed' ? 'In Stock' : 'Pending Stock'), // Set initial status based on transaction completion
                            'adjusted_in_transaction_item_id' => $item->id,
                            'created_by_user_id' => $this->getCurrentUserId(),
                            'updated_by_user_id' => $this->getCurrentUserId(),
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                        Logger::log("Created new adjusted-in serial: {$serialNumber} for item {$item->id}.");
                    } catch (\Exception $e) {
                        Logger::log('ERROR: Failed to create new ProductInstance for adjustment inflow serial ' . $serialNumber . ': ' . $e->getMessage());
                        throw $e; // Re-throw to indicate a critical error
                    }
                }
            }

            // After processing all submitted, handle any *previously linked* 'inflow' serials that were *not* submitted this time
            // This is crucial for handling removals or changes in serials for an existing adjustment item
            foreach ($existingInInstances as $existingInstance) {
                if (!in_array($existingInstance->serial_number, $submittedInSerials)) { // Only check against inflow serials for inflow
                    // If the transaction was completed, and this serial was adjusted in, but now it's removed from submission
                    // Then it means it's no longer part of this inflow adjustment.
                    // Decide what status it should revert to. 'In Stock' implies it's available again. 'Removed' implies it's gone.
                    // Based on your schema, 'Removed' is valid.
                    if ($existingInstance->status === 'In Stock' || $existingInstance->status === 'Pending Stock') {
                        $existingInstance->status = 'Removed'; // Or 'Scrapped' if that implies intentional removal/cancellation
                        $existingInstance->adjusted_in_transaction_item_id = null;
                        $existingInstance->updated_by_user_id = $this->getCurrentUserId();
                        $existingInstance->updated_at = date('Y-m-d H:i:s');
                        $existingInstance->save();
                        Logger::log("Existing adjusted-in serial {$existingInstance->serial_number} marked as 'Removed' as it was not re-submitted for inflow.");
                    }
                }
            }


        } elseif ($adjustmentDirection === 'outflow') {
            foreach ($submittedOutSerials as $serialNumber) {
                $serialNumber = trim($serialNumber);
                if (empty($serialNumber)) {
                    continue; // Skip empty serial numbers
                }

                $instance = ProductInstance::findBySerialNumber($serialNumber);
                if (!$instance) {
                    Logger::log('ERROR: Attempted to adjust out non-existent serial number: ' . $serialNumber . ' for product ' . $item->product->sku);
                    throw new \Exception('Invalid serial number for adjustment outflow: ' . $serialNumber);
                }

                // If transaction is completed, ensure status is 'Adjusted Out'
                if ($newTransactionStatus === 'Completed' && $instance->status !== 'Adjusted Out') {
                    $instance->status = 'Adjusted Out';
                } elseif ($newTransactionStatus !== 'Completed' && $instance->status !== 'Pending Stock') {
                    // If not completed, and not already Pending Stock, set to Pending Stock
                    $instance->status = 'Pending Stock';
                } elseif ($newTransactionStatus === 'Cancelled' && $instance->status === 'Adjusted Out') {
                    // If transaction is cancelled, and this serial was adjusted out, revert to 'In Stock'
                    $instance->status = 'In Stock';
                }
                $instance->adjusted_out_transaction_item_id = $item->id;
                $instance->updated_by_user_id = $this->getCurrentUserId();
                $instance->updated_at = date('Y-m-d H:i:s');
                $instance->save();
                Logger::log("Updated status of adjusted-out serial {$serialNumber} to '{$instance->status}'.");
            }

            // After processing all submitted, handle any *previously linked* 'outflow' serials that were *not* submitted this time
            foreach ($existingOutInstances as $existingInstance) {
                if (!in_array($existingInstance->serial_number, $submittedOutSerials)) { // Only check against outflow serials for outflow
                    // If the transaction was completed, and this serial was adjusted out, but now it's removed from submission
                    // Then it means it's no longer part of this outflow adjustment.
                    // It should revert to 'In Stock' as it's no longer out.
                    if ($existingInstance->status === 'Adjusted Out' || $existingInstance->status === 'Pending Stock') {
                        $existingInstance->status = 'In Stock';
                        $existingInstance->adjusted_out_transaction_item_id = null;
                        $existingInstance->updated_by_user_id = $this->getCurrentUserId();
                        $existingInstance->updated_at = date('Y-m-d H:i:s');
                        $existingInstance->save();
                        Logger::log("Existing adjusted-out serial {$existingInstance->serial_number} reverted to 'In Stock' as it was not re-submitted for outflow.");
                    }
                }
            }
        }
    }

    private function handleSaleSerials(TransactionItem $item, array $submittedSerials, $originalTransactionStatus, $newTransactionStatus)
    {
        $existingSoldInstances = $item->soldInstances->keyBy('serial_number'); // Instances previously sold with this item
        $serialsToKeep = [];

        foreach ($submittedSerials as $serialNumber) {
            $serialNumber = trim($serialNumber);
            if (empty($serialNumber)) continue;

            $instance = ProductInstance::where('serial_number', $serialNumber)
                                    ->where('product_id', $item->product_id)
                                    ->first();

            if ($instance) {
                // Check if this instance was already part of this transaction item
                if (!$existingSoldInstances->has($serialNumber)) {
                    // If it's a new serial being assigned to this sale item
                    // You might need to detach it from any previous transaction_item_id if it was mistakenly linked
                    // This is more complex if ProductInstance can only be linked to one item at a time.
                    // For now, we assume it's safe to re-link if not already linked to THIS item.
                    // If it's linked to another item, the validation should catch it.
                }

                $instance->sale_transaction_item_id = $item->id; // Link to the current sale item
                if ($newTransactionStatus === 'Completed' && $instance->status !== 'Sold') {
                    $instance->status = 'Sold';
                } elseif ($newTransactionStatus !== 'Completed' && $instance->status !== 'Pending Sale') {
                    $instance->status = 'Pending Sale';
                } elseif ($newTransactionStatus === 'Cancelled' && $instance->status === 'Sold') {
                    $instance->status = 'In Stock'; // Revert if cancelled
                }
                $instance->updated_by_user_id = $this->getCurrentUserId();
                $instance->updated_at = date('Y-m-d H:i:s');
                $instance->save();
                Logger::log("Updated status of sold serial {$serialNumber} to '{$instance->status}'.");
                $serialsToKeep[] = $serialNumber;
            } else {
                Logger::log('ERROR: Attempted to sell non-existent or invalid serial number: ' . $serialNumber . ' for product ' . $item->product->sku);
                throw new \Exception('Invalid serial number for sale: ' . $serialNumber); // Re-throw for transaction rollback
            }
        }

        // Handle instances previously sold with this item but now removed from submission
        foreach ($existingSoldInstances as $existingInstance) {
            if (!in_array($existingInstance->serial_number, $serialsToKeep)) {
                if ($existingInstance->status === 'Sold' || $existingInstance->status === 'Pending Sale') {
                    $existingInstance->status = 'In Stock'; // Revert to in-stock
                    $existingInstance->sale_transaction_item_id = null; // Unlink
                    $existingInstance->updated_by_user_id = $this->getCurrentUserId();
                    $existingInstance->updated_at = date('Y-m-d H:i:s');
                    $existingInstance->save();
                    Logger::log("Existing sold serial {$existingInstance->serial_number} reverted to 'In Stock'.");
                }
            }
        }
    }
    private function handleCustomerReturnSerials(TransactionItem $item, array $submittedSerials, $originalTransactionStatus, $newTransactionStatus)
    {
        $existingReturnedInstances = $item->returnedFromCustomerInstances->keyBy('serial_number');
        $serialsToKeep = [];

        foreach ($submittedSerials as $serialNumber) {
            $serialNumber = trim($serialNumber);
            if (empty($serialNumber)) continue;

            $instance = ProductInstance::where('serial_number', $serialNumber)
                                    ->where('product_id', $item->product_id)
                                    ->first();

            if ($instance) {
                $instance->returned_from_customer_transaction_item_id = $item->id;
                if ($newTransactionStatus === 'Completed' && $instance->status !== 'In Stock') { // Assuming 'In Stock' for resalable returns
                    $instance->status = 'In Stock'; // Or 'Returned - Resalable'
                } elseif ($newTransactionStatus !== 'Completed' && $instance->status !== 'Pending Stock') { // Adjusted 'Pending Customer Return'
                    $instance->status = 'Pending Stock';
                } elseif ($newTransactionStatus === 'Cancelled' && $instance->status === 'In Stock') {
                    $instance->status = 'Sold';
                }
                $instance->updated_by_user_id = $this->getCurrentUserId();
                $instance->updated_at = date('Y-m-d H:i:s');
                $instance->save();
                Logger::log("Updated status of customer returned serial {$serialNumber} to '{$instance->status}'.");
                $serialsToKeep[] = $serialNumber;
            } else {
                Logger::log('ERROR: Attempted to return non-existent or invalid serial number: ' . $serialNumber . ' for product ' . $item->product->sku);
                throw new \Exception('Invalid serial number for customer return: ' . $serialNumber);
            }
        }

        foreach ($existingReturnedInstances as $existingInstance) {
            if (!in_array($existingInstance->serial_number, $serialsToKeep)) {
                if ($existingInstance->status === 'In Stock' || $existingInstance->status === 'Pending Stock') { // Adjusted 'Pending Customer Return'
                    $existingInstance->status = 'Sold';
                    $existingInstance->returned_from_customer_transaction_item_id = null;
                    $existingInstance->updated_by_user_id = $this->getCurrentUserId();
                    $existingInstance->updated_at = date('Y-m-d H:i:s');
                    $existingInstance->save();
                    Logger::log("Existing customer returned serial {$existingInstance->serial_number} reverted to 'Sold'.");
                }
            }
        }
    }

    private function handleSupplierReturnSerials(TransactionItem $item, array $submittedSerials, $originalTransactionStatus, $newTransactionStatus)
    {
        $existingReturnedInstances = $item->returnedToSupplierInstances->keyBy('serial_number');
        $serialsToKeep = [];

        foreach ($submittedSerials as $serialNumber) {
            $serialNumber = trim($serialNumber);
            if (empty($serialNumber)) continue;

            $instance = ProductInstance::where('serial_number', $serialNumber)
                                    ->where('product_id', $item->product_id)
                                    ->first();

            if ($instance) {
                $instance->returned_to_supplier_transaction_item_id = $item->id;
                if ($newTransactionStatus === 'Completed' && $instance->status !== 'Removed') {
                    $instance->status = 'Removed';
                } elseif ($newTransactionStatus !== 'Completed' && $instance->status !== 'Pending Stock') { // Adjusted 'Pending Supplier Return'
                    $instance->status = 'Pending Stock';
                } elseif ($newTransactionStatus === 'Cancelled' && $instance->status === 'Removed') {
                    $instance->status = 'In Stock';
                }
                $instance->updated_by_user_id = $this->getCurrentUserId();
                $instance->updated_at = date('Y-m-d H:i:s');
                $instance->save();
                Logger::log("Updated status of supplier returned serial {$serialNumber} to '{$instance->status}'.");
                $serialsToKeep[] = $serialNumber;
            } else {
                Logger::log('ERROR: Attempted to return to supplier non-existent or invalid serial number: ' . $serialNumber . ' for product ' . $item->product->sku);
                throw new \Exception('Invalid serial number for supplier return: ' . $serialNumber);
            }
        }

        foreach ($existingReturnedInstances as $existingInstance) {
            if (!in_array($existingInstance->serial_number, $serialsToKeep)) {
                if ($existingInstance->status === 'Removed' || $existingInstance->status === 'Pending Stock') { // Adjusted 'Pending Supplier Return'
                    $existingInstance->status = 'In Stock';
                    $existingInstance->returned_to_supplier_transaction_item_id = null;
                    $existingInstance->updated_by_user_id = $this->getCurrentUserId();
                    $existingInstance->updated_at = date('Y-m-d H:i:s');
                    $existingInstance->save();
                    Logger::log("Existing supplier returned serial {$existingInstance->serial_number} reverted to 'In Stock'.");
                }
            }
        }
    }

    // You will also need a method in your Transaction model, or a service, to update the product's current_stock.
    // Example in Transaction model:
    // public function updateProductStock() {
    //     foreach ($this->items as $item) {
    //         if ($item->product->is_serialized) {
    //             // For serialized products, stock is implicitly managed by instance statuses
    //             // You might still have a 'current_stock' field for quick lookups
    //             // In this case, count 'In Stock' instances for the product.
    //             $item->product->current_stock = ProductInstance::where('product_id', $item->product_id)
    //                                                         ->where('status', 'In Stock')
    //                                                         ->count();
    //             $item->product->save();
    //         } else {
    //             // For non-serialized products, adjust stock based on transaction type and quantity
    //             // This logic would be more direct:
    //             // $product = Product::find($item->product_id);
    //             // if ($this->transaction_type === 'Sale') {
    //             //     $product->current_stock -= $item->quantity;
    //             // } elseif ($this->transaction_type === 'Purchase') {
    //             //     $product->current_stock += $item->quantity;
    //             // }
    //             // ... and so on for other types
    //             // $product->save();
    //         }
    //     }
    // }

}