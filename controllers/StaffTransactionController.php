<?php
namespace Controllers;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Connection;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Capsule\Manager as DB;
use Models\Transaction;
use Models\Customer;
use Models\Supplier;
use Models\ProductInstance;
use Models\Product;
use Models\TransactionItem;
use Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
require_once 'vendor/autoload.php';

class StaffTransactionController extends Controller {

    /**
     * Helper to get current user ID. Replace with your actual authentication method.
     * @return int|null
     */
    private function getCurrentUserId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }
    public function show($id) {
        Logger::log("STAFF_TRANSACTIONS_SHOW: Attempting to display transaction ID: $id.");

        $transaction = Transaction::with([
            'items.product',
            'customer',
            'supplier',
            'createdBy',
            'updatedBy'
        ])->find($id);

        if (!$transaction) {
            Logger::log("STAFF_TRANSACTIONS_SHOW_FAILED: Transaction ID $id not found.");

            $_SESSION['error_message']="Transaction not found.";
            $this->view('staff/transactions_list', [], 'staff');
            return;
        }

        $success_message = $_GET['success_message'] ?? null;
        $error_message = $_GET['error'] ?? null;

        Logger::log("STAFF_TRANSACTIONS_SHOW_SUCCESS: Displaying transaction ID: $id.");

        $this->view('staff/transactions/show', [
            'transaction' => $transaction,
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

        $customers = Customer::all();
        $suppliers = Supplier::all();

        $this->view('staff/transactions/add', [
            'customers' => $customers,
            'suppliers' => $suppliers,
            'transaction' => new Transaction()
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

        $transaction_type      = trim($this->input('transaction_type'));
        $customer_id           = trim($this->input('customer_id'));
        $supplier_id           = trim($this->input('supplier_id'));
        $transaction_date_str  = trim($this->input('transaction_date'));
        $status                = trim($this->input('status'));
        $notes                 = trim($this->input('notes'));
        $current_user_id       = $this->getCurrentUserId();

        Logger::log(message: 'DEBUG: Value of $current_user_id from getCurrentUserId(): ' . var_export($current_user_id, true));
        $errors = [];

        if (empty($transaction_type)) $errors[] = 'Transaction Type is required.';
        if (!in_array($transaction_type, ['Purchase', 'Sale', 'Customer Return', 'Supplier Return','Stock Adjustment'])) $errors[] = 'Invalid Transaction Type.';
        if (empty($transaction_date_str)) $errors[] = 'Transaction Date is required.';
        if (!strtotime($transaction_date_str)) $errors[] = 'Transaction Date is invalid.';

        $transaction_date_db = date('Y-m-d H:i:s', strtotime($transaction_date_str));

        if ($transaction_type === 'Sale') {
            if (empty($customer_id)) $errors[] = 'Customer is required for sales.';
            $supplier_id = null;
        } elseif ($transaction_type === 'Purchase') {
            if (empty($supplier_id)) $errors[] = 'Supplier is required for purchases.';
            $customer_id = null;
        } elseif ($transaction_type === 'Customer Return' || $transaction_type === 'Supplier Return') {
            if (empty($customer_id) && empty($supplier_id)) {
                $errors[] = 'Either a Customer or a Supplier must be selected for a Return transaction.';
            } elseif (!empty($customer_id) && !empty($supplier_id)) {
                $errors[] = 'A Return transaction cannot be associated with both a Customer and a Supplier. Please select one or the other.';
            }
        } elseif ($transaction_type === 'Stock Adjustment') {
            $customer_id = null;
            $supplier_id = null;
        }
        Logger::log("DEBUG_AFTER_CLEARING_LOGIC: transaction_type='{$transaction_type}', customer_id='" . var_export($customer_id, true) . "', supplier_id='" . var_export($supplier_id, true) . "'");
        if (empty($status)) $errors[] = 'Status is required.';
        if (!in_array($status, ['Pending','Completed', 'Cancelled'])) $errors[] = 'Invalid Status.';
        if (empty($current_user_id)) $errors[] = 'User ID not found. Please log in.';

        if (!empty($errors)) {
            Logger::log("TRANSACTION_STORE_FAILED: Validation errors: " . implode(', ', $errors));
            $customers = Customer::all()->toArray();
            $suppliers = Supplier::all()->toArray();

            $_SESSION['error_message']="Error: ". implode('<br>', $errors);
            $this->view('staff/transactions/add', [
                'customers' => $customers,
                'suppliers' => $suppliers,
                'transaction' => (object) [
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

        DB::beginTransaction();
        try {
            $transaction = new Transaction();
            $transaction->invoice_bill_number = 'INV-' . strtoupper(uniqid());
            $transaction->transaction_type = $transaction_type;
            $transaction->customer_id = $customer_id;
            $transaction->supplier_id = $supplier_id;
            $transaction->transaction_date = $transaction_date_db;
            $transaction->status = $status;
            $transaction->notes = $notes;
            $transaction->created_by_user_id = $_SESSION['user_id'] ?? null;
            $transaction->updated_by_user_id = $_SESSION['user_id'] ?? null;
            $relevant_for_amount_received = in_array($transaction_type, ['Sale', 'Purchase', 'Customer Return', 'Supplier Return']);
            if ($relevant_for_amount_received && isset($_POST['amount_received'])) {
                $transaction->amount_received = filter_var($_POST['amount_received'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                if ($transaction->amount_received === false) {
                    throw new Exception("Invalid amount received value.");
                }
            } else {
                $transaction->amount_received = null;
            }
            
            $transaction->save();

            DB::commit();
            Logger::log('TRANSACTION_STORE_SUCCESS: Transaction created successfully with ID: ' . $transaction->id);

            $_SESSION['success_message']="Transaction successfully added.";
            header('Location: /staff/transactions/show/' . $transaction->id);
            exit();

        } catch (Exception $e) {
            DB::rollBack();
            Logger::log('TRANSACTION_STORE_ERROR: Failed to store transaction. Exception: ' . $e->getMessage());

            Logger::log('TRANSACTION_STORE_ERROR: Stack Trace: ' . $e->getTraceAsString());
            $customers = Customer::all()->toArray();
            $suppliers = Supplier::all()->toArray();

            $_SESSION['error_message']="An unexpected error occured. " .$e->getMessage();
            $this->view('staff/transactions/add', [

                'customers' => $customers,
                'suppliers' => $suppliers,
                'transaction' => (object) [
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
    }

    /**
     * Displays the form to edit an existing transaction.
     * Accessible via /staff/transactions/edit/{id}
     *
     * @param int $id The ID of the transaction to edit.
     * @return void
     */
    public function edit($id) {
        Logger::log("TRANSACTION_EDIT: Attempting to display edit form for transaction ID: $id.");

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
            Logger::log("TRANSACTION_EDIT_FAILED: Transaction ID $id not found for editing.");

            $_SESSION['error_message']="Transaction not found for " . $id . ".";
            header('Location: /staff/transactions_list');
            exit();
        }

        $customers = Customer::all();
        $suppliers = Supplier::all();

        $available_serial_numbers_by_product = [];
        $in_stock_product_instances = ProductInstance::where('status', 'In Stock')->get()->groupBy('product_id');

        foreach ($in_stock_product_instances as $productId => $instances) {
            $available_serial_numbers_by_product[$productId] = $instances->map(function ($instance) {
                return ['serial_number' => $instance->serial_number, 'status' => $instance->status];
            })->toArray();
        }
        $potential_adjusted_out_serials_by_product = $available_serial_numbers_by_product;

        $potential_customer_return_serials_by_product = [];
            if ($transaction->customer_id) {
                $sold_to_customer_instances = ProductInstance::whereHas('saleTransactionItem.transaction', function ($query) use ($transaction) {
                    $query->where('customer_id', $transaction->customer_id);
                })->where('status', 'Sold')
                ->get()
                ->groupBy('product_id');

                foreach ($sold_to_customer_instances as $productId => $instances) {
                    $potential_customer_return_serials_by_product[$productId] = $instances->map(function ($instance) {
                        return ['serial_number' => $instance->serial_number, 'status' => $instance->status];
                    })->toArray();
                }
            }

        $potential_supplier_return_serials_by_product = $available_serial_numbers_by_product;

        $temp_submitted_serials = $_SESSION['temp_submitted_serials'] ?? [];
            unset($_SESSION['temp_submitted_serials']);

            $temp_submitted_adjustment_directions = $_SESSION['temp_submitted_adjustment_directions'] ?? [];
            unset($_SESSION['temp_submitted_adjustment_directions']);

            $error_data = $_SESSION['error_data'] ?? [];
            unset($_SESSION['error_data']);


        Logger::log("TRANSACTION_EDIT_SUCCESS: Displaying edit form for transaction ID: $id.");

        $this->view('staff/transactions/edit', [
            'transaction' => $transaction,
            'customers' => $customers,
            'suppliers' => $suppliers,
            'available_serial_numbers_by_product' => $available_serial_numbers_by_product,
            'potential_customer_return_serials_by_product' => $potential_customer_return_serials_by_product,
            'potential_supplier_return_serials_by_product' => $potential_supplier_return_serials_by_product,
            'potential_adjusted_out_serials_by_product' => $potential_adjusted_out_serials_by_product,
            'error_data' => $error_data,
            'temp_submitted_serials' => $temp_submitted_serials,
            'temp_submitted_adjustment_directions' => $temp_submitted_adjustment_directions,
            ], 'staff');
    }

    /**
     * Handles the POST request to update an existing transaction.
     * Accessible via /staff/transactions/update
     *
     * @return void
     */
    public function update() {
        Logger::log("TRANSACTION_UPDATE: Attempting to update transaction.");

        // Check if the request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Logger::log("TRANSACTION_UPDATE_ERROR: Invalid request method. Must be POST.");
            $_SESSION['error_message'] = 'Invalid request method.';
            header('Location: /staff/transactions_list');
            exit();
        }

        $transactionId = trim($this->input('id'));
        // Eager load existing items and their product instances for comparison and updates
        $transaction = Transaction::with([
            'items.product',
            'items.purchasedInstances',
            'items.soldInstances',
            'items.returnedFromCustomerInstances',
            'items.returnedToSupplierInstances',
            'items.adjustedInInstances',
            'items.adjustedOutInstances',
        ])->find($transactionId);

        if (!$transaction) {
            Logger::log("TRANSACTION_UPDATE_ERROR: Transaction not found. ID: {$transactionId}");
            $_SESSION['error_message'] = 'Transaction not found.';
            header('Location: /staff/transactions_list');
            exit();
        }

        $originalStatus = $transaction->status;
        $newStatus = trim($this->input('status')); // Get the new status from the form

        // Prevent modification if original status is Completed or Cancelled
        if ($originalStatus === 'Completed' || $originalStatus === 'Cancelled') {
            $_SESSION['error_message'] = "Transaction ID {$transactionId} cannot be modified because its status is '{$originalStatus}'.";
            header('Location: /staff/transactions/edit/' . $transaction->id);
            exit();
        }
        
        // Store original serial numbers for comparison later
        $originalSerialsByItemId = [];
        foreach ($transaction->items as $item) {
            if ($item->product->is_serialized) {
                // Collect all serials currently linked to this item, regardless of their current status
                // This is crucial for determining what was 'removed' from the transaction.
                $linkedInstances = ProductInstance::where(function($query) use ($item) {
                    $query->where('purchase_transaction_item_id', $item->id)
                          ->orWhere('sale_transaction_item_id', $item->id)
                          ->orWhere('returned_from_customer_transaction_item_id', $item->id)
                          ->orWhere('returned_to_supplier_transaction_item_id', $item->id)
                          ->orWhere('adjusted_in_transaction_item_id', $item->id)
                          ->orWhere('adjusted_out_transaction_item_id', $item->id);
                })->where('product_id', $item->product_id)->pluck('serial_number')->toArray();
                $originalSerialsByItemId[$item->id] = $linkedInstances;
            } else {
                $originalSerialsByItemId[$item->id] = [];
            }
        }

        DB::beginTransaction();
        try {
            // Update main transaction details
            $transaction->invoice_bill_number = trim($this->input('invoice_bill_number'));
            $transaction->customer_id = trim($this->input('customer_id')) ?: null;
            $transaction->supplier_id = trim($this->input('supplier_id')) ?: null;
            $transaction->transaction_date = trim($this->input('transaction_date'));
            $transaction->status = $newStatus; // Assign the new status
            $transaction->notes = trim($this->input('notes'));
            $transaction->updated_by_user_id = $this->getCurrentUserId();

            $items_data = $this->input('items') ?? [];
            $calculated_total_amount = 0.00;
            $processed_item_ids = []; // To track items that are still in the submission
            $product_ids_to_update_stock = []; // To track unique product IDs whose stock might need updating

            // Add this line to debug incoming item data from the frontend
            Logger::log('DEBUG: Incoming items_data from form: ' . json_encode($items_data));

            // Get submitted serial numbers for each item from the form
            // Ensure these input names match your frontend form structure
            $submitted_purchase_serials = $this->input('serial_numbers') ?? [];
            $submitted_sale_serials = $this->input('selected_serial_numbers') ?? [];
            $submitted_customer_return_serials = trim($this->input('returned_serial_numbers')) ?? [];
            $submitted_supplier_return_serials = $this->input('supplier_returned_serial_numbers') ?? [];
            $submitted_adjustment_serials =$this->input('adjustment_serial_numbers') ?? [];
            $submitted_adjustment_directions = [];
            // For stock adjustments, the direction is per item, so retrieve correctly
            foreach ($items_data as $idx => $item_data) {
                if ($transaction->transaction_type === 'Stock Adjustment') {
                    $itemId = $item_data['id'] ?? null; // Assuming item_data has 'id' for existing items
                    // This input name needs to match your form's adjustment direction radio/select
                    $submitted_adjustment_directions[$itemId] = trim($this->input("adjustment_direction_{$itemId}")) ?? '';
                }
            }

            // --- CONSOLIDATED ITEM PROCESSING LOOP ---
            foreach ($items_data as $index => $item_data) {
                // Basic item validation
                if (empty($item_data['product_id']) || empty($item_data['quantity'])) {
                    throw new Exception("Product ID and Quantity are required for all items.");
                }

                $product = Product::find($item_data['product_id']);
                if (!$product) {
                    throw new Exception("Product with ID {$item_data['product_id']} not found.");
                }

                $quantity = (int)$item_data['quantity'];
                $unit_price_at_transaction = 0.00;
                $purchase_cost_at_transaction = 0.00;

                // Determine unit price based on transaction type and product serialization
                if ($transaction->transaction_type === 'Sale') {
                    // Corrected: Fallback to product->cost_price for sales
                    $unit_price_at_transaction = (float)($item_data['unit_price'] ?? $product->cost_price ?? 0);
                    if ($unit_price_at_transaction <= 0) {
                        throw new Exception("Selling price must be greater than zero for sales.");
                    }
                    // For sales, cost_at_receipt for the ProductInstance is its original purchase cost, not the sale price.
                    // This is handled when the instance is created (during purchase) or when it's sold (it just links to sale).
                    // We don't update cost_at_receipt here.
                } elseif ($transaction->transaction_type === 'Purchase') {
                    if ($product->is_serialized) {
                        // Prioritize 'unit_price' for serialized purchases to match screenshot expectation
                        $purchase_cost_at_transaction = (float)($item_data['unit_price'] ?? $item_data['purchase_cost'] ?? 0);
                        $unit_price_at_transaction = $purchase_cost_at_transaction; // Use cost for general unit price
                        if ($purchase_cost_at_transaction <= 0) {
                            throw new Exception("Purchase cost must be greater than zero for serialized items.");
                        }
                    } else {
                        $unit_price_at_transaction = (float)($item_data['unit_price'] ?? $product->unit_price ?? 0);
                        if ($unit_price_at_transaction <= 0) {
                            throw new Exception("Unit cost must be greater than zero for non-serialized purchases.");
                        }
                        $purchase_cost_at_transaction = $unit_price_at_transaction; // For non-serialized, unit price is the purchase cost
                    }
                } elseif (in_array($transaction->transaction_type, ['Customer Return', 'Supplier Return', 'Stock Adjustment'])) {
                    $unit_price_at_transaction = (float)($item_data['unit_price'] ?? $product->unit_price ?? 0);
                    // For returns/adjustments, purchase_cost_at_transaction is usually not directly set on the item
                    // but rather refers to the original cost of the instance being returned/adjusted.
                    // We'll leave it as 0 here unless explicitly passed from the form for these types.
                }

                $line_total = $quantity * $unit_price_at_transaction;
                $calculated_total_amount += $line_total; // SUMMING UP THE TOTAL

                // Debugging values for each item
                Logger::log("DEBUG_ITEM_CALC: Item Index: {$index}, Product ID: {$product->id}, Quantity: {$quantity}, Unit Price (Form): " . ($item_data['unit_price'] ?? 'N/A') . ", Purchase Cost (Form): " . ($item_data['purchase_cost'] ?? 'N/A') . ", Calculated Unit Price: {$unit_price_at_transaction}, Calculated Purchase Cost: {$purchase_cost_at_transaction}, Line Total: {$line_total}, Running Total: {$calculated_total_amount}");


                // Handle item update/creation
                $transaction_item = null;
                $itemIdFromForm = $item_data['id'] ?? null; // Get item ID from form data

                if ($itemIdFromForm && !empty($itemIdFromForm)) {
                    $transaction_item = TransactionItem::find($itemIdFromForm);
                }

                if ($transaction_item) {
                    // Update existing item
                    $transaction_item->update([
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price_at_transaction' => $unit_price_at_transaction,
                        'purchase_cost_at_transaction' => $purchase_cost_at_transaction,
                        'line_total' => $line_total,
                        'updated_by_user_id' => $this->getCurrentUserId(),
                    ]);
                    $processed_item_ids[] = $transaction_item->id;
                } else {
                    // Create new item
                    $transaction_item = TransactionItem::create([
                        'transaction_id' => $transaction->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price_at_transaction' => $unit_price_at_transaction,
                        'purchase_cost_at_transaction' => $purchase_cost_at_transaction,
                        'line_total' => $line_total,
                        'created_by_user_id' => $this->getCurrentUserId(),
                        'updated_by_user_id' => $this->getCurrentUserId(),
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $processed_item_ids[] = $transaction_item->id;
                }

                // Add product ID to the list for potential stock update later
                $product_ids_to_update_stock[$product->id] = true;

                // --- Serial Number Handling for the current item ---
                if ($product->is_serialized) {
                    $itemSubmittedSerials = [];
                    $currentItemId = $transaction_item->id; // Use the ID of the current (new or updated) item

                    switch ($transaction->transaction_type) {
                        case 'Purchase':
                            $itemSubmittedSerials = array_map('trim', $submitted_purchase_serials[$currentItemId] ?? []);
                            // Pass the purchase_cost_at_transaction to handlePurchaseSerials
                            $this->handlePurchaseSerials($transaction_item, $itemSubmittedSerials, $originalStatus, $newStatus, $purchase_cost_at_transaction);
                            break;
                        case 'Sale':
                            $itemSubmittedSerials = array_map('trim', $submitted_sale_serials[$currentItemId] ?? []);
                            $this->handleSaleSerials($transaction_item, $itemSubmittedSerials, $originalStatus, $newStatus);
                            break;
                        case 'Customer Return':
                            $itemSubmittedSerials = array_map('trim', $submitted_customer_return_serials[$currentItemId] ?? []);
                            $this->handleCustomerReturnSerials($transaction_item, $itemSubmittedSerials, $originalStatus, $newStatus);
                            break;
                        case 'Supplier Return':
                            $itemSubmittedSerials = array_map('trim', $submitted_supplier_return_serials[$currentItemId] ?? []);
                            $this->handleSupplierReturnSerials($transaction_item, $itemSubmittedSerials, $originalStatus, $newStatus);
                            break;
                        case 'Stock Adjustment':
                            $adjustmentDirection = $submitted_adjustment_directions[$currentItemId] ?? null;
                            $itemSubmittedAdjustmentSerials = array_map('trim', $submitted_adjustment_serials[$currentItemId] ?? []);
                            $this->handleStockAdjustmentSerials($transaction_item, $adjustmentDirection, $itemSubmittedAdjustmentSerials, $itemSubmittedAdjustmentSerials, $originalStatus, $newStatus); // Pass same array for in/out for now
                            break;
                    }
                } else {
                    // --- Stock Update for Non-Serialized Products (when status changes to Completed) ---
                    // This logic only applies if the transaction is *transitioning* to 'Completed'
                    if ($originalStatus !== 'Completed' && $newStatus === 'Completed') {
                        switch ($transaction->transaction_type) {
                            case 'Purchase':
                            case 'Customer Return':
                                $product->increment('current_stock', $quantity);
                                Logger::log("STOCK_UPDATE: Increased stock for non-serialized Product ID: {$product->id} by {$quantity}. New stock: {$product->current_stock}");
                                break;
                            case 'Sale':
                            case 'Supplier Return':
                            case 'Stock Adjustment': // For non-serialized, covers outflow
                                if ($product->current_stock < $quantity) {
                                    throw new Exception("Insufficient stock for non-serialized Product '{$product->name}'. Attempted to decrement {$quantity}, but only {$product->current_stock} available.");
                                }
                                $product->decrement('current_stock', $quantity);
                                Logger::log("STOCK_UPDATE: Decreased stock for non-serialized Product ID: {$product->id} by {$quantity}. New stock: {$product->current_stock}");
                                break;
                        }
                    }
                     // If status changes from Completed to Pending/Draft/Cancelled, revert stock
                    elseif ($originalStatus === 'Completed' && $newStatus !== 'Completed') {
                        switch ($transaction->transaction_type) {
                            case 'Purchase':
                            case 'Customer Return':
                                // These were inflows, so decrement stock if un-completing
                                if ($product->current_stock < $quantity) {
                                    throw new Exception("Cannot revert stock for non-serialized Product '{$product->name}'. Attempted to decrement {$quantity}, but only {$product->current_stock} available.");
                                }
                                $product->decrement('current_stock', $quantity);
                                Logger::log("STOCK_REVERT: Decreased stock for non-serialized Product ID: {$product->id} by {$quantity} due to status change from Completed.");
                                break;
                            case 'Sale':
                            case 'Supplier Return':
                            case 'Stock Adjustment':
                                // These were outflows, so increment stock if un-completing
                                $product->increment('current_stock', $quantity);
                                Logger::log("STOCK_REVERT: Increased stock for non-serialized Product ID: {$product->id} by {$quantity} due to status change from Completed.");
                                break;
                        }
                    }
                    $product->save(); // Save the product with updated stock
                }
            }

            // Delete items that were in the original transaction but are no longer in the submitted data
            // This needs to be done carefully to revert any associated stock/serial changes
            foreach ($transaction->items as $original_item) {
                if (!in_array($original_item->id, $processed_item_ids)) {
                    // Add product ID to the list for potential stock update later
                    $product_ids_to_update_stock[$original_item->product->id] = true;

                    // Before deleting, if it was a serialized product, ensure its instances are unlinked/reverted
                    if ($original_item->product->is_serialized) {
                        $instancesToRevert = ProductInstance::where(function($query) use ($original_item) {
                            $query->where('purchase_transaction_item_id', $original_item->id)
                                  ->orWhere('sale_transaction_item_id', $original_item->id)
                                  ->orWhere('returned_from_customer_transaction_item_id', $original_item->id)
                                  ->orWhere('returned_to_supplier_transaction_item_id', $original_item->id)
                                  ->orWhere('adjusted_in_transaction_item_id', $original_item->id)
                                  ->orWhere('adjusted_out_transaction_item_id', $original_item->id);
                        })->get();

                        foreach ($instancesToRevert as $instance) {
                            // Revert status based on transaction type if it was 'Completed'
                            if ($originalStatus === 'Completed') {
                                switch ($transaction->transaction_type) {
                                    case 'Purchase':
                                        $instance->status = 'Removed'; // Or 'Discarded'
                                        break;
                                    case 'Sale':
                                        $instance->status = 'In Stock';
                                        break;
                                    case 'Customer Return':
                                        $instance->status = 'Sold';
                                        break;
                                    case 'Supplier Return':
                                        $instance->status = 'In Stock';
                                        break;
                                    case 'Stock Adjustment':
                                        if ($instance->adjusted_in_transaction_item_id === $original_item->id) {
                                            $instance->status = 'Removed'; // Revert inflow
                                        } elseif ($instance->adjusted_out_transaction_item_id === $original_item->id) {
                                            $instance->status = 'In Stock'; // Revert outflow
                                        }
                                        break;
                                }
                            } else {
                                // If transaction was not completed, just unlink
                                $instance->status = 'Removed'; // Or 'Unlinked' or 'Pending Removal'
                            }
                            // Unlink the item from the instance
                            $instance->purchase_transaction_item_id = null;
                            $instance->sale_transaction_item_id = null;
                            $instance->returned_from_customer_transaction_item_id = null;
                            $instance->returned_to_supplier_transaction_item_id = null;
                            $instance->adjusted_in_transaction_item_id = null;
                            $instance->adjusted_out_transaction_item_id = null;
                            $instance->updated_by_user_id = $this->getCurrentUserId();
                            $instance->save();
                            Logger::log("SERIAL_REVERT: ProductInstance {$instance->serial_number} unlinked and status reverted due to item deletion from transaction.");
                        }
                    } else {
                        // For non-serialized products, if transaction was completed, revert stock
                        if ($originalStatus === 'Completed') {
                            $product = $original_item->product; // Get product for stock update
                            switch ($transaction->transaction_type) {
                                case 'Purchase':
                                case 'Customer Return':
                                    $product->decrement('current_stock', $original_item->quantity);
                                    break;
                                case 'Sale':
                                case 'Supplier Return':
                                case 'Stock Adjustment':
                                    $product->increment('current_stock', $original_item->quantity);
                                    break;
                            }
                            $product->save();
                            Logger::log("STOCK_REVERT: Stock for non-serialized Product ID: {$product->id} reverted by {$original_item->quantity} due to item deletion.");
                        }
                    }
                    $original_item->delete(); // Delete the transaction item
                    Logger::log("TRANSACTION_ITEM_DELETED: Item ID {$original_item->id} removed from transaction.");
                }
            }

            // Assign the calculated total amount to the transaction
            $transaction->total_amount = $calculated_total_amount;

            // And assign amount_received
            $relevant_for_amount_received = in_array($transaction->transaction_type, ['Sale', 'Purchase', 'Customer Return', 'Supplier Return']);
            if ($relevant_for_amount_received && isset($_POST['amount_received'])) {
                $transaction->amount_received = filter_var($_POST['amount_received'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                if ($transaction->amount_received === false) { // filter_var returns false on failure
                    throw new Exception("Invalid amount received value.");
                }
            } else {
                // If not relevant or not submitted, set to null or default (depends on your DB schema default)
                $transaction->amount_received = null; // or 0.00 if you prefer a numeric default
            }

            // --- FINALLY, RUN THE VALIDATION WITH THE CORRECT DATA ---
            if ($transaction->transaction_type === 'Purchase') {
                $epsilon = 0.001;
                if (abs($transaction->amount_received - $transaction->total_amount) > $epsilon) {
                    DB::rollBack();
                    Logger::log('TRANSACTION_UPDATE_ERROR: Amount paid (' . $transaction->amount_received . ') does not match total amount (' . $transaction->total_amount . ') for purchase transaction ID: ' . $transaction->id);
                    $_SESSION['error_message'] = "For Purchase transactions, the 'Amount Paid' must exactly match the 'Total Amount' (" . number_format($transaction->total_amount, 2) . ").";
                    // Store submitted data to repopulate form on error
                    $_SESSION['error_data'] = $_POST;
                    header('Location: /staff/transactions/edit/' . $transaction->id);
                    exit();
                }
            }
            
            // Save the main transaction record
            $transaction->save();

            // --- Update Product Current Stock for Serialized Items (if status changed to/from Completed) ---
            if ($originalStatus !== 'Completed' || $newStatus === 'Completed') { // Only update if status is changing to or from 'Completed'
                foreach (array_keys($product_ids_to_update_stock) as $productId) {
                    $product = Product::find($productId);
                    if ($product && $product->is_serialized) {
                        $this->updateProductCurrentStockFromInstances($productId);
                        Logger::log("SERIALIZED_STOCK_SYNC: Synced current_stock for Product ID: {$productId} due to transaction status change.");
                    }
                }
            }


            // Store submitted serial numbers and adjustment directions for pending transactions (sticky form)
            if ($newStatus === 'Pending') {
                $submittedSerialsToPersist = [];
                $submittedAdjustmentDirectionsToPersist = [];

                foreach ($items_data as $item_data) {
                    $productId = $item_data['product_id'];
                    $product = Product::find($productId); // Re-fetch product if needed

                    if ($product && $product->is_serialized) {
                        $itemIdFromForm = $item_data['id'] ?? null; // Use the item ID from the form for existing items

                        // Determine the actual item ID that was saved/updated
                        $actualItemId = null;
                        if ($itemIdFromForm && in_array($itemIdFromForm, $processed_item_ids)) {
                            $actualItemId = $itemIdFromForm;
                        } else {
                            // If it was a newly created item, find its ID. This is tricky without returning it from create().
                            // A safer approach for new items is to pass the new item object directly.
                            // For simplicity, we'll assume item_data['id'] is reliable or handle new items separately if needed.
                            // For now, we'll rely on the $processed_item_ids and the form's item ID.
                            // If it's a new item, it won't have an ID in the original $transaction->items, but will have one after create().
                            // This part might need refinement depending on how you link new items back to their IDs.
                            // For now, we'll rely on the $processed_item_ids and the form's item ID.
                        }

                        if ($actualItemId) {
                            if ($transaction->transaction_type === 'Stock Adjustment') {
                                $itemAdjustmentSerials = array_map('trim', $submitted_adjustment_serials[$actualItemId] ?? []);
                                $submittedSerialsToPersist[$actualItemId] = array_filter($itemAdjustmentSerials);
                                $submittedAdjustmentDirectionsToPersist[$actualItemId] = $submitted_adjustment_directions[$actualItemId] ?? '';
                            } else {
                                // Adjust these input names based on your form's 'name' attributes for each transaction type
                                if ($transaction->transaction_type === 'Purchase') {
                                    $itemSerials = $submitted_purchase_serials[$actualItemId] ?? [];
                                } elseif ($transaction->transaction_type === 'Sale') {
                                    $itemSerials = $submitted_sale_serials[$actualItemId] ?? [];
                                } elseif ($transaction->transaction_type === 'Customer Return') {
                                    $itemSerials = $submitted_customer_return_serials[$actualItemId] ?? [];
                                } elseif ($transaction->transaction_type === 'Supplier Return') {
                                    $itemSerials = $submitted_supplier_return_serials[$actualItemId] ?? [];
                                } else {
                                    $itemSerials = []; // Fallback for other types
                                }

                                $itemSerials = array_map('trim', $itemSerials);
                                $submittedSerialsToPersist[$actualItemId] = array_filter($itemSerials);
                            }
                        }
                    }
                }

                $_SESSION['temp_submitted_serials'] = $submittedSerialsToPersist;
                $_SESSION['temp_submitted_adjustment_directions'] = $submittedAdjustmentDirectionsToPersist;
                Logger::log("DEBUG: Stored temp_submitted_serials and directions for pending/draft transaction.");
            }


            DB::commit();
            Logger::log("TRANSACTION_UPDATE_SUCCESS: Transaction and associated serial numbers updated successfully. ID: {$transaction->id}");
            $_SESSION['success_message'] = 'Transaction updated successfully!';
            header('Location: /staff/transactions_list');
            exit();

        } catch (Exception $e) {
            DB::rollBack();
            Logger::log("TRANSACTION_UPDATE_ERROR: " . $e->getMessage());
            $_SESSION['error_message'] = 'An error occurred while updating the transaction: ' . $e->getMessage();
            $_SESSION['error_data'] = $_POST; // Populate form with submitted data
            header('Location: /staff/transactions/edit/' . $transactionId);
            exit();
        }
    }

    /**
     * Helper method to update the current_stock of a Product based on its ProductInstances.
     * This is specifically for serialized products.
     * @param int $productId The ID of the product to update.
     * @return void
     */
    private function updateProductCurrentStockFromInstances(int $productId): void {
        $product = Product::find($productId);
        if (!$product) {
            Logger::log("STOCK_SYNC_ERROR: Product ID {$productId} not found for stock synchronization.");
            return;
        }

        // Count instances that are 'In Stock' for this product
        $inStockCount = ProductInstance::where('product_id', $productId)
                                       ->where('status', 'In Stock')
                                       ->count();

        // Update the product's current_stock
        $product->current_stock = $inStockCount;
        $product->save();
        Logger::log("STOCK_SYNC: Product ID {$productId} current_stock updated to {$inStockCount} based on 'In Stock' instances.");
    }


    // Helper methods for serial number handling (moved from the main update logic)
    // These methods should be defined within the StaffTransactionController class.

    /**
     * Handles serial number updates for Purchase transactions.
     * @param TransactionItem $item The transaction item.
     * @param array $submittedSerials Array of serial numbers submitted for this item.
     * @param string $originalTransactionStatus The original status of the transaction.
     * @param string $newTransactionStatus The new status of the transaction.
     * @param float $purchaseCostAtTransaction The purchase cost of the item at the time of transaction.
     */
    private function handlePurchaseSerials(TransactionItem $item, array $submittedSerials, $originalTransactionStatus, $newTransactionStatus, float $purchaseCostAtTransaction) {
        $existingInstances = ProductInstance::where('purchase_transaction_item_id', $item->id)->pluck('serial_number')->toArray();
        $serialsToKeep = [];

        foreach ($submittedSerials as $serialNumber) {
            $serialNumber = trim($serialNumber);
            if (empty($serialNumber)) continue;

            if (in_array($serialNumber, $existingInstances)) {
                // Existing serial number linked to this item
                $instance = ProductInstance::where('serial_number', $serialNumber)->first();
                if ($instance) {
                    if ($newTransactionStatus === 'Completed' && $instance->status !== 'In Stock') {
                        $instance->status = 'In Stock';
                        $instance->updated_by_user_id = $this->getCurrentUserId();
                        $instance->updated_at = date('Y-m-d H:i:s');
                        $instance->save();
                        Logger::log("Updated status of existing purchased serial {$serialNumber} to 'In Stock'.");
                    } elseif ($newTransactionStatus !== 'Completed' && $instance->status === 'In Stock') {
                        $instance->status = 'Pending Stock';
                        $instance->updated_by_user_id = $this->getCurrentUserId();
                        $instance->updated_at = date('Y-m-d H:i:s');
                        $instance->save();
                        Logger::log("Reverted status of existing purchased serial {$serialNumber} to 'Pending Stock'.");
                    }
                    $serialsToKeep[] = $serialNumber;
                }
            } else {
                // New serial number for this item
                $instance = ProductInstance::where('serial_number', $serialNumber)->where('product_id', $item->product_id)->first();
                if ($instance) {
                    // Serial already exists but is not linked to this item or has wrong status
                    // This is a potential conflict, decide how to handle (e.g., error, re-link)
                    if ($instance->purchase_transaction_item_id !== null && $instance->purchase_transaction_item_id !== $item->id) {
                         throw new Exception("Serial number '{$serialNumber}' is already linked to another purchase transaction item.");
                    }
                    $instance->purchase_transaction_item_id = $item->id;
                    $instance->status = ($newTransactionStatus === 'Completed' ? 'In Stock' : 'Pending Stock');
                    $instance->cost_at_receipt = $purchaseCostAtTransaction; // Store cost
                    $instance->updated_by_user_id = $this->getCurrentUserId();
                    $instance->updated_at = date('Y-m-d H:i:s');
                    $instance->save();
                    Logger::log("Relinked existing ProductInstance {$serialNumber} to item {$item->id}.");
                } else {
                    // Truly new serial, create new ProductInstance
                    ProductInstance::create([
                        'product_id' => $item->product_id,
                        'serial_number' => $serialNumber,
                        'status' => ($newTransactionStatus === 'Completed' ? 'In Stock' : 'Pending Stock'),
                        'purchase_transaction_item_id' => $item->id,
                        'cost_at_receipt' => $purchaseCostAtTransaction, // Store cost
                        'created_by_user_id' => $this->getCurrentUserId(),
                        'updated_by_user_id' => $this->getCurrentUserId(),
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    Logger::log("Created new purchased serial: {$serialNumber} for item {$item->id}.");
                }
                $serialsToKeep[] = $serialNumber;
            }
        }

        // Handle serials that were originally linked but are no longer submitted
        foreach ($existingInstances as $existingSerialNumber) {
            if (!in_array($existingSerialNumber, $serialsToKeep)) {
                $instance = ProductInstance::where('serial_number', $existingSerialNumber)->first();
                if ($instance) {
                    $instance->status = 'Removed'; // Or 'Scrapped'
                    $instance->purchase_transaction_item_id = null;
                    $instance->updated_by_user_id = $this->getCurrentUserId();
                    $instance->updated_at = date('Y-m-d H:i:s');
                    $instance->save();
                    Logger::log("Existing purchased serial {$existingSerialNumber} marked as 'Removed' due to unlinking.");
                }
            }
        }
    }

    /**
     * Handles serial number updates for Sale transactions.
     * @param TransactionItem $item The transaction item.
     * @param array $submittedSerials Array of serial numbers submitted for this item.
     * @param string $originalTransactionStatus The original status of the transaction.
     * @param string $newTransactionStatus The new status of the transaction.
     */
    private function handleSaleSerials(TransactionItem $item, array $submittedSerials, $originalTransactionStatus, $newTransactionStatus) {
        $existingSoldInstances = ProductInstance::where('sale_transaction_item_id', $item->id)->pluck('serial_number')->toArray();
        $serialsToKeep = [];

        foreach ($submittedSerials as $serialNumber) {
            $serialNumber = trim($serialNumber);
            if (empty($serialNumber)) continue;

            $instance = ProductInstance::where('serial_number', $serialNumber)
                                    ->where('product_id', $item->product->id)
                                    ->first();

            if (!$instance) {
                throw new Exception('Serial number ' . $serialNumber . ' not found for product ' . $item->product->name . '.');
            }

            // Check if this instance is already linked to another sale item, or not in stock
            if (($instance->sale_transaction_item_id !== null && $instance->sale_transaction_item_id !== $item->id) || $instance->status !== 'In Stock') {
                // Allow re-linking if it was previously sold by this transaction item
                if (!in_array($serialNumber, $existingSoldInstances)) {
                    throw new Exception("Serial number '{$serialNumber}' is not 'In Stock' or already linked to another sale.");
                }
            }

            $instance->sale_transaction_item_id = $item->id;
            if ($newTransactionStatus === 'Completed') {
                $instance->status = 'Sold';
            } elseif ($newTransactionStatus === 'Cancelled' && $originalTransactionStatus === 'Completed') {
                $instance->status = 'In Stock'; // Revert to In Stock if sale is cancelled
            } else {
                $instance->status = 'Pending Stock'; // Changed from 'Pending Sale' to 'Pending Stock' based on schema
            }
            $instance->updated_by_user_id = $this->getCurrentUserId();
            $instance->updated_at = date('Y-m-d H:i:s');
            $instance->save();
            Logger::log("Updated status of sold serial {$serialNumber} to '{$instance->status}'.");
            $serialsToKeep[] = $serialNumber;
        }

        // Handle serials that were originally linked but are no longer submitted
        foreach ($existingSoldInstances as $existingSerialNumber) {
            if (!in_array($existingSerialNumber, $serialsToKeep)) {
                $instance = ProductInstance::where('serial_number', $existingSerialNumber)->first();
                if ($instance) {
                    $instance->status = 'In Stock'; // Revert to in-stock
                    $instance->sale_transaction_item_id = null; // Unlink
                    $instance->updated_by_user_id = $this->getCurrentUserId();
                    $instance->updated_at = date('Y-m-d H:i:s');
                    $instance->save();
                    Logger::log("Existing sold serial {$existingSerialNumber} reverted to 'In Stock'.");
                }
            }
        }
    }

    /**
     * Handles serial number updates for Customer Return transactions.
     * @param TransactionItem $item The transaction item.
     * @param array $submittedSerials Array of serial numbers submitted for this item.
     * @param string $originalTransactionStatus The original status of the transaction.
     * @param string $newTransactionStatus The new status of the transaction.
     */
    private function handleCustomerReturnSerials(TransactionItem $item, array $submittedSerials, $originalTransactionStatus, $newTransactionStatus) {
        $existingReturnedInstances = ProductInstance::where('returned_from_customer_transaction_item_id', $item->id)->pluck('serial_number')->toArray();
        $serialsToKeep = [];

        foreach ($submittedSerials as $serialNumber) {
            $serialNumber = trim($serialNumber);
            if (empty($serialNumber)) continue;

            $instance = ProductInstance::where('serial_number', $serialNumber)
                                    ->where('product_id', $item->product->id)
                                    ->first();

            if (!$instance) {
                throw new Exception('Serial number ' . $serialNumber . ' not found for product ' . $item->product->name . '.');
            }

            // Check if it was previously sold to the customer associated with this transaction
            // This is a more robust check if you have a history of sales
            // For simplicity, we'll just check if it's currently 'Sold'
            if ($instance->status !== 'Sold' && !in_array($serialNumber, $existingReturnedInstances)) {
                throw new Exception("Serial number '{$serialNumber}' is not in 'Sold' status and cannot be returned by customer.");
            }

            $instance->returned_from_customer_transaction_item_id = $item->id;
            if ($newTransactionStatus === 'Completed') {
                $instance->status = 'In Stock'; // Assuming customer returns become 'In Stock'
            } elseif ($newTransactionStatus === 'Cancelled' && $originalTransactionStatus === 'Completed') {
                $instance->status = 'Sold'; // Revert to Sold if return is cancelled
            } else {
                $instance->status = 'Pending Stock'; // For pending returns
            }
            $instance->updated_by_user_id = $this->getCurrentUserId();
            $instance->updated_at = date('Y-m-d H:i:s');
            $instance->save();
            Logger::log("Updated status of customer returned serial {$serialNumber} to '{$instance->status}'.");
            $serialsToKeep[] = $serialNumber;
        }

        // Handle serials that were originally linked but are no longer submitted
        foreach ($existingReturnedInstances as $existingSerialNumber) {
            if (!in_array($existingSerialNumber, $serialsToKeep)) {
                $instance = ProductInstance::where('serial_number', $existingSerialNumber)->first();
                if ($instance) {
                    $instance->status = 'Sold'; // Revert to Sold
                    $instance->returned_from_customer_transaction_item_id = null;
                    $instance->updated_by_user_id = $this->getCurrentUserId();
                    $instance->updated_at = date('Y-m-d H:i:s');
                    $instance->save();
                    Logger::log("Existing customer returned serial {$existingSerialNumber} reverted to 'Sold'.");
                }
            }
        }
    }

    /**
     * Handles serial number updates for Supplier Return transactions.
     * @param TransactionItem $item The transaction item.
     * @param array $submittedSerials Array of serial numbers submitted for this item.
     * @param string $originalTransactionStatus The original status of the transaction.
     * @param string $newTransactionStatus The new status of the transaction.
     */
    private function handleSupplierReturnSerials(TransactionItem $item, array $submittedSerials, $originalTransactionStatus, $newTransactionStatus) {
        $existingReturnedInstances = ProductInstance::where('returned_to_supplier_transaction_item_id', $item->id)->pluck('serial_number')->toArray();
        $serialsToKeep = [];

        foreach ($submittedSerials as $serialNumber) {
            $serialNumber = trim($serialNumber);
            if (empty($serialNumber)) continue;

            $instance = ProductInstance::where('serial_number', $serialNumber)
                                    ->where('product_id', $item->product->id)
                                    ->first();

            if (!$instance) {
                throw new Exception('Serial number ' . $serialNumber . ' not found for product ' . $item->product->name . '.');
            }

            // Ensure it's in stock before returning to supplier
            if ($instance->status !== 'In Stock' && !in_array($serialNumber, $existingReturnedInstances)) {
                throw new Exception("Serial number '{$serialNumber}' is not in 'In Stock' and cannot be returned to supplier.");
            }

            $instance->returned_to_supplier_transaction_item_id = $item->id;
            if ($newTransactionStatus === 'Completed') {
                $instance->status = 'Removed'; // Or 'Returned to Supplier'
            } elseif ($newTransactionStatus === 'Cancelled' && $originalTransactionStatus === 'Completed') {
                $instance->status = 'In Stock'; // Revert to In Stock if return is cancelled
            } else {
                $instance->status = 'Pending Stock'; // For pending returns
            }
            $instance->updated_by_user_id = $this->getCurrentUserId();
            $instance->updated_at = date('Y-m-d H:i:s');
            $instance->save();
            Logger::log("Updated status of supplier returned serial {$serialNumber} to '{$instance->status}'.");
            $serialsToKeep[] = $serialNumber;
        }

        // Handle serials that were originally linked but are no longer submitted
        foreach ($existingReturnedInstances as $existingSerialNumber) {
            if (!in_array($existingSerialNumber, $serialsToKeep)) {
                $instance = ProductInstance::where('serial_number', $existingSerialNumber)->first();
                if ($instance) {
                    $instance->status = 'In Stock'; // Revert to In Stock
                    $instance->returned_to_supplier_transaction_item_id = null;
                    $instance->updated_by_user_id = $this->getCurrentUserId();
                    $instance->updated_at = date('Y-m-d H:i:s');
                    $instance->save();
                    Logger::log("Existing supplier returned serial {$existingSerialNumber} reverted to 'In Stock'.");
                }
            }
        }
    }

    /**
     * Handles serial number updates for Stock Adjustment transactions.
     * @param TransactionItem $item The transaction item.
     * @param string|null $adjustmentDirection The direction of adjustment ('inflow' or 'outflow').
     * @param array $submittedInSerials Array of serial numbers for inflow.
     * @param array $submittedOutSerials Array of serial numbers for outflow.
     * @param string $originalTransactionStatus The original status of the transaction.
     * @param string $newTransactionStatus The new status of the transaction.
     */
    private function handleStockAdjustmentSerials(TransactionItem $item, ?string $adjustmentDirection, array $submittedInSerials, array $submittedOutSerials, $originalTransactionStatus, $newTransactionStatus) {
        $existingInInstances = ProductInstance::where('adjusted_in_transaction_item_id', $item->id)->pluck('serial_number')->toArray();
        $existingOutInstances = ProductInstance::where('adjusted_out_transaction_item_id', $item->id)->pluck('serial_number')->toArray();
        
        $serialsToKeepForInflow = [];
        $serialsToKeepForOutflow = [];

        // Process submitted inflow serials
        if ($adjustmentDirection === 'inflow') {
            foreach ($submittedInSerials as $serialNumber) {
                $serialNumber = trim($serialNumber);
                if (empty($serialNumber)) continue;

                $instance = ProductInstance::where('serial_number', $serialNumber)->where('product_id', $item->product->id)->first();
                if ($instance) {
                    // If serial exists, check if it's already linked to this inflow item
                    if (!in_array($serialNumber, $existingInInstances)) {
                        // If it's linked to another adjustment or has an unexpected status, throw error
                        if ($instance->adjusted_in_transaction_item_id !== null && $instance->adjusted_in_transaction_item_id !== $item->id) {
                            throw new Exception("Serial number '{$serialNumber}' is already linked to another adjustment inflow.");
                        }
                        if ($instance->status === 'In Stock' && $newTransactionStatus === 'Completed') {
                            // Already in stock, no status change needed if completed, just link
                        } else if ($instance->status !== 'Removed' && $instance->status !== 'In Stock') {
                            // If it's not removed or in stock, it might be linked to something else
                            throw new Exception("Serial number '{$serialNumber}' has an unexpected status and cannot be adjusted in.");
                        }
                    }
                } else {
                    // Create new instance if it doesn't exist
                    $instance = new ProductInstance();
                    $instance->product_id = $item->product->id;
                    $instance->serial_number = $serialNumber;
                    $instance->created_by_user_id = $this->getCurrentUserId();
                    $instance->created_at = Carbon::now();
                }

                $instance->adjusted_in_transaction_item_id = $item->id;
                $instance->adjusted_out_transaction_item_id = null; // Ensure it's not linked to outflow
                if ($newTransactionStatus === 'Completed') {
                    $instance->status = 'In Stock';
                } elseif ($newTransactionStatus === 'Cancelled' && $originalTransactionStatus === 'Completed') {
                    $instance->status = 'Removed'; // If inflow is cancelled, remove it
                } else {
                    $instance->status = 'Pending Stock';
                }
                $instance->updated_by_user_id = $this->getCurrentUserId();
                $instance->save();
                Logger::log("Processed adjustment inflow serial {$serialNumber} to '{$instance->status}'.");
                $serialsToKeepForInflow[] = $serialNumber;
            }

            // Handle previously linked inflow serials that are no longer submitted
            foreach ($existingInInstances as $existingSerialNumber) {
                if (!in_array($existingSerialNumber, $serialsToKeepForInflow)) {
                    $instance = ProductInstance::where('serial_number', $existingSerialNumber)->first();
                    if ($instance) {
                        $instance->status = 'Removed'; // Revert if unlinked from inflow
                        $instance->adjusted_in_transaction_item_id = null;
                        $instance->updated_by_user_id = $this->getCurrentUserId();
                        $instance->save();
                        Logger::log("Existing adjusted-in serial {$existingSerialNumber} marked as 'Removed' due to unlinking.");
                    }
                }
            }

            // Ensure any existing outflow links for this item are removed if direction changed
            foreach ($existingOutInstances as $existingSerialNumber) {
                $instance = ProductInstance::where('serial_number', $existingSerialNumber)->first();
                if ($instance) {
                    $instance->status = 'In Stock'; // Revert outflow to in stock
                    $instance->adjusted_out_transaction_item_id = null;
                    $instance->updated_by_user_id = $this->getCurrentUserId();
                    $instance->save();
                    Logger::log("Existing adjusted-out serial {$existingSerialNumber} reverted to 'In Stock' due to direction change.");
                }
            }

        } elseif ($adjustmentDirection === 'outflow') {
            foreach ($submittedOutSerials as $serialNumber) {
                $serialNumber = trim($serialNumber);
                if (empty($serialNumber)) continue;

                $instance = ProductInstance::where('serial_number', $serialNumber)->where('product_id', $item->product->id)->first();
                if (!$instance) {
                    throw new Exception('Serial number ' . $serialNumber . ' not found for product ' . $item->product->name . '.');
                }

                // Ensure it's in stock before adjusting out
                if ($instance->status !== 'In Stock' && !in_array($serialNumber, $existingOutInstances)) {
                    throw new Exception("Serial number '{$serialNumber}' is not in 'In Stock' and cannot be adjusted out.");
                }

                $instance->adjusted_out_transaction_item_id = $item->id;
                $instance->adjusted_in_transaction_item_id = null; // Ensure it's not linked to inflow
                if ($newTransactionStatus === 'Completed') {
                    $instance->status = 'Adjusted Out';
                } elseif ($newTransactionStatus === 'Cancelled' && $originalTransactionStatus === 'Completed') {
                    $instance->status = 'In Stock'; // Revert to In Stock if outflow is cancelled
                } else {
                    $instance->status = 'Pending Stock'; // For pending outflow
                }
                $instance->updated_by_user_id = $this->getCurrentUserId();
                $instance->save();
                Logger::log("Processed adjustment outflow serial {$serialNumber} to '{$instance->status}'.");
                $serialsToKeepForOutflow[] = $serialNumber;
            }

            // Handle previously linked outflow serials that are no longer submitted
            foreach ($existingOutInstances as $existingSerialNumber) {
                if (!in_array($existingSerialNumber, $serialsToKeepForOutflow)) {
                    $instance = ProductInstance::where('serial_number', $existingSerialNumber)->first();
                    if ($instance) {
                        $instance->status = 'In Stock'; // Revert to In Stock if unlinked from outflow
                        $instance->adjusted_out_transaction_item_id = null;
                        $instance->updated_by_user_id = $this->getCurrentUserId();
                        $instance->save();
                        Logger::log("Existing adjusted-out serial {$existingSerialNumber} reverted to 'In Stock' due to unlinking.");
                    }
                }
            }

            // Ensure any existing inflow links for this item are removed if direction changed
            foreach ($existingInInstances as $existingSerialNumber) {
                $instance = ProductInstance::where('serial_number', $existingSerialNumber)->first();
                if ($instance) {
                    $instance->status = 'Removed'; // Revert inflow to removed
                    $instance->adjusted_in_transaction_item_id = null;
                    $instance->updated_by_user_id = $this->getCurrentUserId();
                    $instance->save();
                    Logger::log("Existing adjusted-in serial {$existingSerialNumber} marked as 'Removed' due to direction change.");
                }
            }

        } else {
            // If no direction is specified, unlink all existing adjustment instances for this item
            foreach ($existingInInstances as $existingSerialNumber) {
                $instance = ProductInstance::where('serial_number', $existingSerialNumber)->first();
                if ($instance) {
                    $instance->status = 'Removed';
                    $instance->adjusted_in_transaction_item_id = null;
                    $instance->updated_by_user_id = $this->getCurrentUserId();
                    $instance->save();
                    Logger::log("Existing adjusted-in serial {$existingSerialNumber} unlinked due to no direction specified.");
                }
            }
            foreach ($existingOutInstances as $existingSerialNumber) {
                $instance = ProductInstance::where('serial_number', $existingSerialNumber)->first();
                if ($instance) {
                    $instance->status = 'In Stock';
                    $instance->adjusted_out_transaction_item_id = null;
                    $instance->updated_by_user_id = $this->getCurrentUserId();
                    $instance->save();
                    Logger::log("Existing adjusted-out serial {$existingSerialNumber} unlinked due to no direction specified.");
                }
            }
            throw new Exception("Adjustment direction (inflow/outflow) must be specified for item '{$item->product->name}'.");
        }
    }
    public function printTransaction($id) {
        Logger::log("PRINT_TRANSACTION: Attempting to generate PDF for transaction ID: $id.");
        date_default_timezone_set('Asia/Manila');
        $transaction = Transaction::with([
            'items.product',
            'customer',
            'supplier',
            'createdBy',
            'updatedBy'
        ])->find($id);

        if (!$transaction) {
            Logger::log("PRINT_TRANSACTION_FAILED: Transaction ID $id not found for printing.");
            // Redirect back to the show page with an error message
            header('Location: /staff/transactions/show/' . $id . '?error=' . urlencode('Transaction not found for printing.'));
            exit();
        }

        // Configure Dompdf options
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans'); // Important for Unicode characters (e.g., currency symbols)
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true); // Enable loading remote assets if needed (e.g., images)

        // Instantiate Dompdf
        $dompdf = new Dompdf($options);

        // Prepare data for HTML generation
        $transaction_date_formatted = date('F j, Y', strtotime($transaction->transaction_date));
        $created_at_formatted = $transaction->created_at ? date('F j, Y, h:i A', strtotime($transaction->created_at)) : 'N/A';
        $updated_at_formatted = $transaction->updated_at ? date('F j, Y, h:i A', strtotime($transaction->updated_at)) : 'N/A';

        $party_type = 'N/A';
        $party_name = 'N/A';

        if ($transaction->transaction_type === 'Sale' && $transaction->customer) {
            $party_type = 'Customer';
            $party_name = htmlspecialchars($transaction->customer->company_name ?? $transaction->customer->contact_first_name . ' ' . $transaction->customer->contact_last_name);
        } elseif ($transaction->transaction_type === 'Purchase' && $transaction->supplier) {
            $party_type = 'Supplier';
            $party_name = htmlspecialchars($transaction->supplier->company_name ?? $transaction->supplier->contact_first_name . ' ' . $transaction->supplier->contact_last_name);
        } elseif ($transaction->transaction_type === 'Return') {
            if ($transaction->customer) {
                $party_type = 'Customer (Return From)';
                $party_name = htmlspecialchars($transaction->customer->company_name ?? $transaction->customer->contact_first_name . ' ' . $transaction->customer->contact_last_name);
            } elseif ($transaction->supplier) {
                $party_type = 'Supplier (Return To)';
                $party_name = htmlspecialchars($transaction->supplier->company_name ?? $transaction->supplier->contact_first_name . ' ' . $transaction->supplier->contact_last_name);
            } else {
                $party_type = 'N/A (Return)';
                $party_name = 'N/A';
            }
        } elseif ($transaction->transaction_type === 'Adjustment') {
            $party_type = 'N/A';
            $party_name = 'No specific party';
        }

        $amount_label = '';
        if ($transaction->transaction_type === 'Sale') {
            $amount_label = 'Amount Received:';
        } elseif ($transaction->transaction_type === 'Purchase') {
            $amount_label = 'Amount Paid:';
        } elseif ($transaction->transaction_type === 'Customer Return') {
            $amount_label = 'Amount Refunded:';
        } elseif ($transaction->transaction_type === 'Supplier Return') {
            $amount_label = 'Amount Received (Refund):';
        }
        $logoData = base64_encode(file_get_contents('resources/images/Heading.png'));
        $logoSrc = 'data:image/png;base64,' . $logoData;

        // Build the HTML content for the PDF
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Transaction Details: ' . htmlspecialchars($transaction->invoice_bill_number) . '</title>
            <style>
                body { font-family: "DejaVu Sans", sans-serif; font-size: 12px; line-height: 1.6; color: #333; }
                .container { width: 90%; margin: 0 auto; padding: 20px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
                .logo {padding-bottom: 10px;}
                .header { text-align: center; margin-bottom: 30px;}
                .header h1 { margin: 0; padding: 0; color: #0056b3; font-size:24px;}
                .details-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                .details-table td { padding: 8px; border-bottom: 1px solid #eee; }
                .details-table strong { display: inline-block; width: 150px; }
                .items-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                .items-table th, .items-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                .items-table th { background-color: #f2f2f2; }
                .total-row td { font-weight: bold; }
                .notes { margin-top: 20px; padding: 10px; border: 1px solid #eee; background-color: #f9f9f9; }
                .footer { text-align: center; margin-top: 50px; font-size: 10px; color: #777; }
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
                    
                    <h1>Transaction Details</h1>
                    <h2>Invoice/Bill Number: ' . htmlspecialchars($transaction->invoice_bill_number) . '</h2>
                </div>

                <table class="details-table">
                    <tr><td><strong>Transaction Type:</strong></td><td>' . htmlspecialchars($transaction->transaction_type) . '</td></tr>
                    <tr><td><strong>Transaction Date:</strong></td><td>' . $transaction_date_formatted . '</td></tr>
                    <tr><td><strong>' . $party_type . ':</strong></td><td>' . $party_name . '</td></tr>
                    <tr><td><strong>Status:</strong></td><td>' . htmlspecialchars($transaction->status) . '</td></tr>
                    <tr><td><strong>Total Amount:</strong></td><td>₱' . number_format($transaction->total_amount, 2) . '</td></tr>';
        
        if (in_array($transaction->transaction_type, ['Sale', 'Purchase', 'Customer Return', 'Supplier Return'])) {
            $html .= '<tr><td><strong>' . $amount_label . '</strong></td><td>₱' . number_format($transaction->amount_received !== null ? (float)$transaction->amount_received : 0.00, 2) . '</td></tr>';
        }

        $html .= '
                    <tr><td><strong>Created By:</strong></td><td>' . htmlspecialchars($transaction->createdBy->username ?? 'N/A') . '</td></tr>
                    <tr><td><strong>Created At:</strong></td><td>' . $created_at_formatted . '</td></tr>
                    <tr><td><strong>Updated By:</strong></td><td>' . htmlspecialchars($transaction->updatedBy->username ?? 'N/A') . '</td></tr>
                    <tr><td><strong>Updated At:</strong></td><td>' . $updated_at_formatted . '</td></tr>
                </table>

                <div class="notes">
                    <strong>Notes:</strong><br>' . nl2br(htmlspecialchars($transaction->notes ?? '')) . '
                </div>

                <h3>Transaction Items</h3>';

        if ((new \Illuminate\Support\Collection($transaction->items))->isEmpty()) {
            $html .= '<p>No items have been added to this transaction.</p>';
        } else {
            $html .= '
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>';
            $item_counter = 1;
            foreach ($transaction->items as $item) {
                $html .= '
                        <tr>
                            <td>' . $item_counter++ . '</td>
                            <td>' . htmlspecialchars($item->product->name ?? 'N/A') . '</td>
                            <td>' . htmlspecialchars($item->quantity !== null ? $item->quantity : 'N/A') . '</td>
                            <td>₱' . number_format($item->unit_price_at_transaction !== null ? (float)$item->unit_price_at_transaction : 0.00, 2) . '</td>
                            <td>₱' . number_format($item->line_total !== null ? (float)$item->line_total : 0.00, 2) . '</td>
                        </tr>';
            }
            $html .= '
                    </tbody>
                </table>';
        }

        $html .= '
                <div class="footer">
                    <p>Generated by Computer IMS on ' . date('F j, Y, h:i A') . '</p>
                </div>
            </div>
        </body>
        </html>';

        $dompdf->loadHtml($html);

        // Set paper size and orientation
        $dompdf->setPaper('letter', 'portrait');

        // Render the HTML as PDF
        $dompdf->render();

        // Output the generated PDF (inline display in browser)
        $dompdf->stream("Transaction_" . htmlspecialchars($transaction->invoice_bill_number) . ".pdf", ["Attachment" => false]);
        Logger::log("PRINT_TRANSACTION_SUCCESS: PDF generated and streamed for transaction ID: $id.");
        exit();
    }
    public function printTransactionsList() {
        Logger::log("PRINT_TRANSACTIONS_LIST: Attempting to generate PDF for transactions list.");

        // Retrieve search, filter, and sort parameters from GET request
        $search_query = trim($this->input('search_query'));
        $filter_type = trim($this->input('filter_type'));
        $filter_status = trim($this->input('filter_status'));
        $sort_by = trim($this->input('sort_by')) ?: 'transaction_date';
        $sort_order = trim($this->input('sort_order')) ?: 'desc';
        $filter_date_range = trim($this->input('filter_date_range'));

        $transactions_query = Transaction::with(['customer', 'supplier', 'createdBy', 'updatedBy']);

        // Apply search query
        if (!empty($search_query)) {
            $transactions_query->where(function($query) use ($search_query) {
                $query->where('invoice_bill_number', 'like', '%' . $search_query . '%')
                      ->orWhere('notes', 'like', '%' . $search_query . '%')
                      ->orWhereHas('customer', function($q) use ($search_query) {
                          $q->where('contact_first_name', 'like', '%' . $search_query . '%')
                            ->orWhere('contact_last_name', 'like', '%' . $search_query . '%')
                            ->orWhere('company_name', 'like', '%' . $search_query . '%');
                      })
                      ->orWhereHas('supplier', function($q) use ($search_query) {
                          $q->where('company_name', 'like', '%' . $search_query . '%')
                            ->orWhere('contact_first_name', 'like', '%' . $search_query . '%')
                            ->orWhere('contact_last_name', 'like', '%' . $search_query . '%');
                      });
            });
        }

        // Apply filters
        if (!empty($filter_type)) {
            $transactions_query->where('transaction_type', $filter_type);
        }
        if (!empty($filter_status)) {
            $transactions_query->where('status', $filter_status);
        }
        if (!empty($filter_date_range)) {
    $now = Carbon::now();

    switch ($filter_date_range) {
        case 'today':
            $from = $now->subDay();
            break;
        case 'yesterday':
            $from = $now->subYesterday();
            break;
        case 'week':
            $from = $now->subWeek();
            break;
        case 'month':
            $from = $now->subMonth();
            break;
        case 'year':
            $from = $now->subYear();
            break;
        default:
            $from = null;
    }

    if ($from) {
        $transactions_query->where('transaction_date', '>=', $from);
        Logger::log("DEBUG: Applied time filter for print: '{$filter_date_range}' from " . $from->toDateTimeString());
    }
}

        // Apply sorting
        // IMPORTANT: Avoid orderBy() on joined columns directly in Eloquent for complex queries
        // Fetch all and sort in PHP if necessary, or ensure proper indexes/relationships are set up.
        // For simplicity, assuming direct column sorting here.
        $transactions = $transactions_query->orderBy($sort_by, $sort_order)->get();

        if ($transactions->isEmpty()) {
            Logger::log("PRINT_TRANSACTIONS_LIST_FAILED: No transactions found matching criteria for printing.");
            // Redirect back to the list page with an error message
            $_SESSION['error_message']="No transactions found matching criteria for printing.";
            header('Location: /staff/transactions_list');
            exit();
        }

        // Configure Dompdf options
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        date_default_timezone_set('Asia/Manila');
        $dompdf = new Dompdf($options);
        $logoData = base64_encode(file_get_contents('resources/images/Heading.png'));
        $logoSrc = 'data:image/png;base64,' . $logoData;

        // Build the HTML content for the PDF list
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Transactions List Report</title>
            <style>
                body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; line-height: 1.4; color: #333; }
                .container { width: 95%; margin: 0 auto; padding: 15px; }
                .header { text-align: center; margin-bottom: 20px; }
                .header h1 { margin: 0; padding: 0; color: #0056b3; font-size: 20px; }
                .filters-info { margin-bottom: 20px; font-size: 10px; }
                .filters-info strong { display: inline-block; width: 80px; }
                .items-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                .items-table th, .items-table td { border: 1px solid #ddd; padding: 6px; text-align: left; }
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
                    <h1>Transactions List Report</h1>
                    
                    <p>Generated on: ' . date('F j, Y, h:i A') . '</p>
                </div>

                <div class="filters-info">
                    <p><strong>Search:</strong> ' . htmlspecialchars($search_query ?: 'N/A') . '</p>
                    <p><strong>Type Filter:</strong> ' . htmlspecialchars($filter_type ?: 'All Types') . '</p>
                    <p><strong>Status Filter:</strong> ' . htmlspecialchars($filter_status ?: 'All Statuses') . '</p>
                    <p><strong>Sort By:</strong> ' . htmlspecialchars(ucwords(str_replace('_', ' ', $sort_by))) . ' (' . htmlspecialchars(ucfirst($sort_order)) . ')</p>
                    <p><strong>Time Filter:</strong> ' . htmlspecialchars(ucwords(str_replace(['5min'], ['Last 5 Minutes'], $filter_date_range ?: 'All Time'))) . '</p>

                </div>

                <table class="items-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Customer/Supplier</th>
                            <th>Date</th>
                            <th>Invoice</th>
                            <th>Total (₱)</th>
                            <th>Status</th>
                            <th>Created By</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach ($transactions as $transaction) {
            $party_name = 'N/A';
            if ($transaction->customer) {
                $party_name = htmlspecialchars($transaction->customer->company_name ?? $transaction->customer->contact_first_name . ' ' . $transaction->customer->contact_last_name);
            } elseif ($transaction->supplier) {
                $party_name = htmlspecialchars($transaction->supplier->company_name ?? $transaction->supplier->contact_first_name . ' ' . $transaction->supplier->contact_last_name);
            }

            $html .= '
                        <tr>
                            <td>' . htmlspecialchars($transaction->id ?? '') . '</td>
                            <td>' . htmlspecialchars($transaction->transaction_type ?? 'N/A') . '</td>
                            <td>' . $party_name . '</td>
                            <td>' . htmlspecialchars($transaction->transaction_date ? date('Y-m-d', strtotime($transaction->transaction_date)) : 'N/A') . '</td>
                            <td>' . htmlspecialchars($transaction->invoice_bill_number ?? 'N/A') . '</td>
                            <td>₱' . number_format($transaction->total_amount ?? 0.00, 2) . '</td>
                            <td>' . htmlspecialchars($transaction->status ?? 'N/A') . '</td>
                            <td>' . htmlspecialchars($transaction->createdBy->username ?? 'N/A') . '</td>
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

        $dompdf->stream("Transactions_List_Report_" . date('Ymd_His') . ".pdf", ["Attachment" => false]);
        Logger::log("PRINT_TRANSACTIONS_LIST_SUCCESS: PDF list generated and streamed.");
        exit();
    }

}
