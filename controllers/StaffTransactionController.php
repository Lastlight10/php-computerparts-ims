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

    // Get inputs
    $transaction_type = $this->input('transaction_type');
    $customer_id      = $this->input('customer_id') ? (int)$this->input('customer_id') : null;
    $supplier_id      = $this->input('supplier_id') !== '' ? (int)$this->input('supplier_id') : null;
    $transaction_date_str = $this->input('transaction_date');
    $status           = $this->input('status');
    $notes            = $this->input('notes');
    $current_user_id  = $this->getCurrentUserId();

    $errors = [];

    // Validate inputs
    if (empty($transaction_type)) $errors[] = 'Transaction Type is required.';
    if (!in_array($transaction_type, ['Purchase', 'Sale', 'Customer Return', 'Supplier Return', 'Stock Adjustment'])) {
        $errors[] = 'Invalid Transaction Type.';
    }

    if (empty($transaction_date_str)) $errors[] = 'Transaction Date is required.';
    elseif (!strtotime($transaction_date_str)) $errors[] = 'Transaction Date is invalid.';
    if (!empty($transaction_date_str)) {
    $transaction_date_db = date('Y-m-d', strtotime($transaction_date_str));
    } else {
        $transaction_date_db = null; // or set a default like date('Y-m-d')
    }


    // Validate customer/supplier based on type
    switch ($transaction_type) {
        case 'Sale':
        case 'Customer Return':
            if (!$customer_id) $errors[] = 'Customer is required.';
            $supplier_id = null;
            break;

        case 'Purchase':
        case 'Supplier Return':
            if (!$supplier_id) $errors[] = 'Supplier is required.';
            $customer_id = null;
            break;

        case 'Stock Adjustment':
            $customer_id = null;
            $supplier_id = null;
            break;
    }

    if (empty($status)) $errors[] = 'Status is required.';
    elseif (!in_array($status, ['Pending', 'Completed', 'Cancelled'])) $errors[] = 'Invalid Status.';

    if (empty($current_user_id)) $errors[] = 'User not found. Please log in.';

    if (!empty($errors)) {
        Logger::log("TRANSACTION_STORE_FAILED: " . implode(', ', $errors));
        $customers = Customer::all();
        $suppliers = Supplier::all();

        $_SESSION['error_message'] = "Error: " . implode('<br>', $errors);
        $this->view('staff/transactions/add', [
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

    // Store transaction
    DB::beginTransaction();
    try {
        $transaction = new Transaction();
        $transaction->invoice_bill_number = 'INV-' . strtoupper(uniqid());
        $transaction->transaction_type    = $transaction_type;
        $transaction->customer_id         = $customer_id;
        $transaction->supplier_id         = $supplier_id;
        $transaction->transaction_date    = $transaction_date_db;
        $transaction->status              = $status;
        $transaction->notes               = $notes;
        $transaction->created_by_user_id  = $current_user_id;
        $transaction->updated_by_user_id  = $current_user_id;
        $transaction->save();

        $items_data = $_POST['items'] ?? [];
        $calculated_total_amount = 0;

        foreach ($items_data as $item_data) {
            if (empty($item_data['product_id']) || empty($item_data['quantity'])) continue;

            $product = Product::find($item_data['product_id']);
            if (!$product) continue;

            $quantity = floatval($item_data['quantity']);
            $unit_price = 0;
            $purchase_cost = 0;
            $line_total = 0;

            switch ($transaction_type) {
                case 'Sale':
                case 'Customer Return':
                    $unit_price = floatval($item_data['unit_price'] ?? $product->unit_price ?? 0);
                    $line_total = $quantity * $unit_price;
                    break;

                case 'Purchase':
                case 'Supplier Return':
                    $purchase_cost = floatval($product->cost_price ?? 0);
                    $line_total = $quantity * $purchase_cost;
                    break;

                case 'Stock Adjustment':
                    $line_total = 0;
                    break;
            }

            $calculated_total_amount += $line_total;

            TransactionItem::create([
                'transaction_id' => $transaction->id,
                'product_id'     => $product->id,
                'quantity'       => $quantity,
                'unit_price_at_transaction' => $unit_price,
                'purchase_cost_at_transaction' => $purchase_cost,
                'line_total'     => $line_total,
                'created_by_user_id' => $current_user_id,
                'updated_by_user_id' => $current_user_id,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Automatically set totals
        $transaction->total_amount = $calculated_total_amount;
        $transaction->amount_received = $calculated_total_amount;

        $transaction->save();

        DB::commit();

        Logger::log('TRANSACTION_STORE_SUCCESS: Transaction created successfully with ID: ' . $transaction->id);
        $_SESSION['success_message'] = "Transaction successfully added.";
        header('Location: /staff/transactions/show/' . $transaction->id);
        exit();

    } catch (Exception $e) {
        DB::rollBack();
        Logger::log('TRANSACTION_STORE_ERROR: ' . $e->getMessage());
        Logger::log('TRANSACTION_STORE_ERROR: Stack Trace: ' . $e->getTraceAsString());

        $customers = Customer::all()->toArray();
        $suppliers = Supplier::all()->toArray();

        $_SESSION['error_message'] = "An unexpected error occurred: " . $e->getMessage();
        $this->view('staff/transactions/add', [
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
            'customer',
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

        if ($transaction->items->isEmpty()) {
            Logger::log("TRANSACTION_EDIT_FAILED: No items found for transaction ID: $id.");

            $_SESSION['error_message'] = "Please add items to this transaction.";
            header('Location: /staff/transactions/show/'.$id);
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

        // Calculate total amount based on items
        $calculated_total_amount = 0;

        foreach ($transaction->items as $item) {
            $quantity = (float)$item->quantity;
            $product = $item->product;

            if ($transaction->transaction_type === 'Sale' || $transaction->transaction_type === 'Customer Return') {
                $unit_price_at_transaction = (float)($item->unit_price ?? $product->unit_price ?? 0);
                $line_total = $quantity * $unit_price_at_transaction;
            } elseif ($transaction->transaction_type === 'Purchase' || $transaction->transaction_type === 'Supplier Return' || $transaction->transaction_type === 'Stock Adjustment') {
                $purchase_cost_at_transaction = (float)($item->cost_price ?? $product->cost_price ?? 0);
                $line_total = $quantity * $purchase_cost_at_transaction;
            } else {
                $line_total = 0;
            }

            $calculated_total_amount += $line_total;
        }

        // Pass it to the view
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
            'calculated_total_amount' => $calculated_total_amount, // <<< add this
            'amount_received' => $calculated_total_amount,
        ], 'staff');

    }

    /**
     * Handles the POST request to update an existing transaction.
     * Accessible via /staff/transactions/update
     *
     * @return void
     */
    private function normalizeValue($value): ?string {
        if ($value === null || $value === '') {
            return null;
        }
        return trim((string) $value);
    }


    private function detectTransactionChanges($transaction, $newData, $items_data, $serialsByType): array {
        $changes = [];

        // --- 1. Main Transaction Fields ---
        $fieldsToCheck = ['invoice_bill_number', 'customer_id', 'supplier_id', 'transaction_date', 'status', 'notes'];

        foreach ($fieldsToCheck as $field) {
            $oldValue = $this->normalizeValue($transaction->$field ?? null);
            $newValue = $this->normalizeValue($newData[$field] ?? null);

            if ($oldValue !== $newValue) {
                $changes[] = "Field '$field' changed from '$oldValue' to '$newValue'";
            }
        }

        // --- 2. Items Check ---
        $originalItems = $transaction->items->keyBy('id');
        $submittedIds = array_column($items_data, 'id');

        // Deleted items
        foreach ($originalItems as $id => $item) {
            if (!in_array($id, $submittedIds)) {
                $changes[] = "Item ID {$id} was deleted";
            }
        }

        // Added / Modified items
        foreach ($items_data as $item_data) {
            $id = $item_data['id'] ?? null;
            $quantity = (int)($item_data['quantity'] ?? 0);
            $unitPrice = (float)($item_data['unit_price'] ?? 0);

            if (!$id || !isset($originalItems[$id])) {
                $changes[] = "New item added (Product {$item_data['product_id']}, Qty {$quantity})";
                continue;
            }

            $existing = $originalItems[$id];
            if ($existing->product_id != $item_data['product_id'] ||
                $existing->quantity != $quantity ||
                $existing->unit_price_at_transaction != $unitPrice) {
                $changes[] = "Item ID {$id} modified";
            }
        }

        // --- 3. Serial Numbers Check ---
        foreach ($serialsByType as $type => $submittedSerialsByItem) {
            foreach ($transaction->items as $item) {
                if (!$item->product->is_serialized) continue;

                $oldSerials = $item->getLinkedSerials(); 
                $newSerials = array_map('trim', $submittedSerialsByItem[$item->id] ?? []);

                sort($oldSerials);
                sort($newSerials);

                if ($oldSerials !== $newSerials) {
                    $changes[] = "Serials changed for item ID {$item->id}";
                }
            }
        }

        return $changes;
    }

    public function update() {
    Logger::log("TRANSACTION_UPDATE: Attempting to update transaction.");

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Logger::log("TRANSACTION_UPDATE_ERROR: Invalid request method. Must be POST.");
        $_SESSION['error_message'] = 'Invalid request method.';
        header('Location: /staff/transactions_list');
        exit();
    }

    $transactionId = trim($this->input('id'));
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
    $newStatus = trim($this->input('status'));

    if ($originalStatus === 'Completed' || $originalStatus === 'Cancelled') {
        $_SESSION['error_message'] = "Transaction ID {$transactionId} cannot be modified because its status is '{$originalStatus}'.";
        header('Location: /staff/transactions/edit/' . $transaction->id);
        exit();
    }

    $items_data = $this->input('items') ?? [];
    $submitted_serials = [
        'purchase' => $this->input('serial_numbers') ?? [],
        'sale' => $this->input('selected_serial_numbers') ?? [],
        'customer_return' => $this->input('returned_serial_numbers') ?? [],
        'supplier_return' => $this->input('supplier_returned_serial_numbers') ?? [],
        'adjustment' => $this->input('adjustment_serial_numbers') ?? [],
    ];

    $submitted_adjustment_directions = [];
    foreach ($items_data as $item_data) {
        if ($transaction->transaction_type === 'Stock Adjustment') {
            $itemId = $item_data['id'] ?? null;
            $submitted_adjustment_directions[$itemId] = trim($this->input("adjustment_direction_{$itemId}")) ?? '';
        }
    }

    $newData = [
        'invoice_bill_number' => trim($this->input('invoice_bill_number')),
        'customer_id' => $this->input('customer_id') ?: null,
        'supplier_id' => $this->input('supplier_id') ?: null,
        'transaction_date' => trim($this->input('transaction_date')),
        'status' => $newStatus,
        'notes' => trim($this->input('notes')),
    ];

    $changes = $this->detectTransactionChanges($transaction, $newData, $items_data, $submitted_serials);
    Logger::log("TRANSACTION_UPDATE_CHANGES: " . print_r($changes, true));

    if (empty($changes)) {
        Logger::log("TRANSACTION_UPDATE_SKIPPED: No changes detected for Transaction ID {$transaction->id}");
        $_SESSION['warning_message'] = 'No changes detected.';
        header('Location: /staff/transactions/edit/' . $transaction->id);
        exit();
    }

    DB::beginTransaction();
    try {
        // Update main transaction
        $transaction->fill($newData);
        $transaction->updated_by_user_id = $this->getCurrentUserId();

        // Initialize totals and tracking arrays
        $calculated_total_amount = 0;
        $processed_item_ids = [];
        $product_ids_to_update_stock = [];

        foreach ($items_data as $item_data) {
            if (empty($item_data['product_id']) || empty($item_data['quantity'])) {
                throw new Exception("Product ID and Quantity are required for all items.");
            }

            $product = Product::find($item_data['product_id']);
            if (!$product) throw new Exception("Product ID {$item_data['product_id']} not found.");

            $quantity = floatval($item_data['quantity']);
            $unit_price = 0.0;
            $purchase_cost = 0.0;
            $line_total = 0.0;

            // Determine price for total and display
            switch ($transaction->transaction_type) {
                case 'Sale':
                case 'Customer Return':
                    $unit_price = floatval($item_data['unit_price'] ?? $product->unit_price ?? 0);
                    $line_total = $quantity * $unit_price;
                    break;
                case 'Purchase':
                case 'Supplier Return':
                    $purchase_cost = floatval($product->cost_price ?? 0);
                    $line_total = $quantity * $purchase_cost;
                    $unit_price = $purchase_cost; // for display only
                    break;
                case 'Stock Adjustment':
                    $purchase_cost = floatval($product->cost_price ?? 0);
                    $line_total = 0; // or whatever you want for adjustments
                    break;
            }
            $calculated_total_amount += $line_total;

            // Update or create transaction item
            $itemIdFromForm = $item_data['id'] ?? null;
            if ($itemIdFromForm && $transaction_item = TransactionItem::find($itemIdFromForm)) {
                $transaction_item->update([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price_at_transaction' => $unit_price,
                    'purchase_cost_at_transaction' => $purchase_cost,
                    'line_total' => $line_total,
                    'updated_by_user_id' => $this->getCurrentUserId(),
                ]);
            } else {
                $transaction_item = TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price_at_transaction' => $unit_price,
                    'purchase_cost_at_transaction' => $purchase_cost,
                    'line_total' => $line_total,
                    'created_by_user_id' => $this->getCurrentUserId(),
                    'updated_by_user_id' => $this->getCurrentUserId(),
                    'created_at' => date('Y-m-d'),
                    'updated_at' => date('Y-m-d'),
                ]);
            }

            $processed_item_ids[] = $transaction_item->id;
            $product_ids_to_update_stock[$product->id] = true;

            // Serialized and non-serialized stock handling
            if ($product->is_serialized) {
                $itemSubmittedSerials = [];
                switch ($transaction->transaction_type) {
                    case 'Purchase':
                        $itemSubmittedSerials = $submitted_serials['purchase'][$transaction_item->id] ?? [];
                        $this->handlePurchaseSerials($transaction_item, $itemSubmittedSerials, $originalStatus, $newStatus, $purchase_cost);
                        break;
                    case 'Sale':
                        $itemSubmittedSerials = $submitted_serials['sale'][$transaction_item->id] ?? [];
                        $this->handleSaleSerials($transaction_item, $itemSubmittedSerials, $originalStatus, $newStatus);
                        break;
                    case 'Customer Return':
                        $itemSubmittedSerials = $submitted_serials['customer_return'][$transaction_item->id] ?? [];
                        $this->handleCustomerReturnSerials($transaction_item, $itemSubmittedSerials, $originalStatus, $newStatus);
                        break;
                    case 'Supplier Return':
                        $itemSubmittedSerials = $submitted_serials['supplier_return'][$transaction_item->id] ?? [];
                        $this->handleSupplierReturnSerials($transaction_item, $itemSubmittedSerials, $originalStatus, $newStatus);
                        break;
                    case 'Stock Adjustment':
                        $itemSubmittedSerials = $submitted_serials['adjustment'][$transaction_item->id] ?? [];
                        $adjustmentDirection = $submitted_adjustment_directions[$transaction_item->id] ?? null;
                        $this->handleStockAdjustmentSerials($transaction_item, $adjustmentDirection, $itemSubmittedSerials, $itemSubmittedSerials, $originalStatus, $newStatus);
                        break;
                }
            } else {
                // Non-serialized stock
                if ($originalStatus !== 'Completed' && $newStatus === 'Completed') {
                    switch ($transaction->transaction_type) {
                        case 'Purchase':
                        case 'Customer Return':
                            $product->increment('current_stock', $quantity);
                            break;
                        case 'Sale':
                        case 'Supplier Return':
                        case 'Stock Adjustment':
                            if ($product->current_stock < $quantity) throw new Exception("Insufficient stock for '{$product->name}'.");
                            $product->decrement('current_stock', $quantity);
                            break;
                    }
                    $product->save();
                }
                if ($originalStatus === 'Completed' && $newStatus !== 'Completed') {
                    switch ($transaction->transaction_type) {
                        case 'Purchase':
                        case 'Customer Return':
                            if ($product->current_stock < $quantity) throw new Exception("Cannot revert stock for '{$product->name}'.");
                            $product->decrement('current_stock', $quantity);
                            break;
                        case 'Sale':
                        case 'Supplier Return':
                        case 'Stock Adjustment':
                            $product->increment('current_stock', $quantity);
                            break;
                    }
                    $product->save();
                }
            }
        }

        // Handle removed items
        foreach ($transaction->items as $original_item) {
            if (!in_array($original_item->id, $processed_item_ids)) {
                $product_ids_to_update_stock[$original_item->product->id] = true;

                if ($original_item->product->is_serialized) {
                    $instances = ProductInstance::where('product_id', $original_item->product_id)
                        ->where(function($q) use ($original_item) {
                            $q->where('purchase_transaction_item_id', $original_item->id)
                            ->orWhere('sale_transaction_item_id', $original_item->id)
                            ->orWhere('returned_from_customer_transaction_item_id', $original_item->id)
                            ->orWhere('returned_to_supplier_transaction_item_id', $original_item->id)
                            ->orWhere('adjusted_in_transaction_item_id', $original_item->id)
                            ->orWhere('adjusted_out_transaction_item_id', $original_item->id);
                        })->get();

                    foreach ($instances as $instance) {
                        $instance->purchase_transaction_item_id = null;
                        $instance->sale_transaction_item_id = null;
                        $instance->returned_from_customer_transaction_item_id = null;
                        $instance->returned_to_supplier_transaction_item_id = null;
                        $instance->adjusted_in_transaction_item_id = null;
                        $instance->adjusted_out_transaction_item_id = null;
                        $instance->updated_by_user_id = $this->getCurrentUserId();
                        $instance->save();
                    }
                } else {
                    if ($originalStatus === 'Completed') {
                        $product = $original_item->product;
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
                    }
                }
                $original_item->delete();
            }
        }

        // Set totals and amount_received
        $transaction->total_amount = $calculated_total_amount;
        if (in_array($transaction->transaction_type, ['Sale', 'Purchase', 'Customer Return', 'Supplier Return'])) {
            $transaction->amount_received = $calculated_total_amount;
        }


        $transaction->save();

        foreach (array_keys($product_ids_to_update_stock) as $productId) {
            $product = Product::find($productId);
            if ($product && $product->is_serialized) {
                $this->updateProductCurrentStockFromInstances($productId);
            }
        }

        DB::commit();
        Logger::log("TRANSACTION_UPDATE_SUCCESS: Transaction ID {$transaction->id} updated successfully.");
        $_SESSION['success_message'] = 'Transaction updated successfully!';
        header('Location: /staff/transactions_list');
        exit();

    } catch (Exception $e) {
        DB::rollBack();
        Logger::log("TRANSACTION_UPDATE_ERROR: " . $e->getMessage());
        $_SESSION['error_message'] = 'Error updating transaction: ' . $e->getMessage();
        $_SESSION['error_data'] = $_POST;
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

    // Load transaction with related data
    $transaction = Transaction::with([
        'items.product',
        'customer',
        'supplier',
        'createdBy',
        'updatedBy'
    ])->find($id);

    if (!$transaction) {
        Logger::log("PRINT_TRANSACTION_FAILED: Transaction ID $id not found.");
        header('Location: /staff/transactions/show/' . $id . '?error=' . urlencode('Transaction not found for printing.'));
        exit();
    }

    // Dompdf configuration
    $options = new Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);

    $transaction_date = date('F j, Y', strtotime($transaction->transaction_date));
    $created_at = $transaction->created_at ? date('F j, Y, h:i A', strtotime($transaction->created_at)) : 'N/A';
    $updated_at = $transaction->updated_at ? date('F j, Y, h:i A', strtotime($transaction->updated_at)) : 'N/A';

    // Determine party info
    $party_name = 'N/A';
    if ($transaction->customer) {
        $party_name = htmlspecialchars($transaction->customer->company_name ?? trim(($transaction->customer->contact_first_name ?? '') . ' ' . ($transaction->customer->contact_last_name ?? '')));
    } elseif ($transaction->supplier) {
        $party_name = htmlspecialchars($transaction->supplier->company_name ?? trim(($transaction->supplier->contact_first_name ?? '') . ' ' . ($transaction->supplier->contact_last_name ?? '')));
    }

    // Determine amount label
    $amount_label_map = [
        'Sale' => 'Amount Received:',
        'Purchase' => 'Amount Paid:',
        'Customer Return' => 'Amount Refunded:',
        'Supplier Return' => 'Amount Received (Refund):'
    ];
    $amount_label = $amount_label_map[$transaction->transaction_type] ?? '';

    // Prepare logo
    $logoData = base64_encode(file_get_contents('resources/images/Heading.png'));
    $logoSrc = 'data:image/png;base64,' . $logoData;

    // Begin HTML
    $html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Transaction Details: ' . htmlspecialchars($transaction->invoice_bill_number) . '</title>
<style>
    body { font-family: "DejaVu Sans", sans-serif; font-size:12px; color:#333; line-height:1.5; }
    .container { width:90%; margin:0 auto; padding:10px; border:1px solid #eee; box-shadow:0 0 10px rgba(0,0,0,0.1); }
    .logo { text-align:center; padding-bottom:10px; }
    .header { text-align:center; margin-bottom:15px; }
    .header h1 { margin:0; font-size:14px; color:#0056b3; }
    .header h2 { margin:0; font-size:10px; }
    table { width:100%; border-collapse:collapse; margin-bottom:15px; }
    table th, table td { border:1px solid #ddd; padding:6px; font-size:10px; text-align:left; }
    table th { background-color:#f2f2f2; }
    .details-table td strong { display:inline-block; width:150px; }
    .notes { margin-top:5px; padding:10px; border:1px solid #eee; background:#f9f9f9; font-size:10px; }
    .footer { text-align:center; font-size:6px; color:#777; margin-top:10px; }
</style>
</head>
<body>
<div class="container">
    <div class="logo">
        <img src="' . $logoSrc . '" alt="Company Logo" style="height:80px;width:80px;border-radius:50%;object-fit:cover;">
        <div class="company-info" style="margin-top:10px;font-size:10px;color:#333;">
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
        <tr><td><strong>Transaction Date:</strong></td><td>' . $transaction_date . '</td></tr>
        <tr><td><strong>Party:</strong></td><td>' . $party_name . '</td></tr>
        <tr><td><strong>Status:</strong></td><td>' . htmlspecialchars($transaction->status) . '</td></tr>
        <tr><td><strong>Total Amount:</strong></td><td>₱' . number_format($transaction->total_amount,2) . '</td></tr>';

    if ($amount_label) {
        $html .= '<tr><td><strong>' . $amount_label . '</strong></td><td>₱' . number_format($transaction->amount_received ?? 0, 2) . '</td></tr>';
    }

    $html .= '
        <tr><td><strong>Created By:</strong></td><td>' . htmlspecialchars($transaction->createdBy->username ?? 'N/A') . '</td></tr>
        <tr><td><strong>Created At:</strong></td><td>' . $created_at . '</td></tr>
        <tr><td><strong>Updated By:</strong></td><td>' . htmlspecialchars($transaction->updatedBy->username ?? 'N/A') . '</td></tr>
        <tr><td><strong>Updated At:</strong></td><td>' . $updated_at . '</td></tr>
    </table>

    <div class="notes"><strong>Notes:</strong><br>' . nl2br(htmlspecialchars($transaction->notes ?? '')) . '</div>

    <h3>Transaction Items</h3>';

    if ($transaction->items->isEmpty()) {
    $html .= '<p>No items added to this transaction.</p>';
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
                <th>Serial Number</th>
            </tr>
        </thead>
        <tbody>';

    $item_counter = 1;
    
    foreach ($transaction->items as $item) {
        $quantity = (int)($item->quantity ?? 0);
        $unit_price = (float)($item->unit_price_at_transaction ?? 0);
        $line_total = (float)($item->line_total ?? $unit_price * $quantity);

        // Merge all instances for this item
        $serials = $item->allInstancesCollection(); // must return a Collection of all related instances
        
        if ($serials->isNotEmpty()) {
            $first = true;
            $count = $serials->count();

            foreach ($serials as $serial) {
                $html .= '<tr>';

                if ($first) {
                    $html .= '<td rowspan="' . $count . '">' . $item_counter . '</td>';
                    $html .= '<td rowspan="' . $count . '">' . htmlspecialchars($item->product->name ?? 'N/A') . '</td>';
                    $html .= '<td rowspan="' . $count . '">' . $quantity . '</td>';
                    $html .= '<td rowspan="' . $count . '">₱' . number_format($unit_price, 2) . '</td>';
                    $html .= '<td rowspan="' . $count . '">₱' . number_format($line_total, 2) . '</td>';
                    $first = false;
                }

                $html .= '<td>' . htmlspecialchars($serial->serial_number ?? 'N/A') . '</td>';
                $html .= '</tr>';
            }
        } else {
            // No serials, single row
            $html .= '<tr>
                <td>' . $item_counter . '</td>
                <td>' . htmlspecialchars($item->product->name ?? 'N/A') . '</td>
                <td>' . $quantity . '</td>
                <td>₱' . number_format($unit_price, 2) . '</td>
                <td>₱' . number_format($line_total, 2) . '</td>
                <td>N/A</td>
            </tr>';
        }

        $item_counter++;
    }

    $html .= '</tbody></table>';
}
    $html .= '<div class="footer">Generated by Computer IMS on ' . date('F j, Y, h:i A') . '</div>
</div>
</body>
</html>';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();
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

    public function delete($id) {
    $transaction = Transaction::find($id);
    if (!$transaction) {
        $_SESSION['error_message'] = 'Transaction not found.';
        header('Location: /staff/transactions_list');
        exit();
    }

    // Optional: prevent deleting completed/cancelled ones
    /*
    if (in_array($transaction->status, ['Completed', 'Cancelled'])) {
        $_SESSION['error_message'] = "Transaction ID {$transaction->id} cannot be deleted.";
        header('Location: /staff/transactions/edit/' . $transaction->id);
        exit();
    }
        */

    // Cascade delete items + serials
    foreach ($transaction->items as $item) {
        $item->delete(); // Or manual DB delete
    }

    $transaction->delete();

    $_SESSION['success_message'] = 'Transaction deleted successfully.';
    header('Location: /staff/transactions_list');
    exit();
}

}
