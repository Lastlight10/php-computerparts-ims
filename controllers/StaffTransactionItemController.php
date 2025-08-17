<?php
namespace Controllers;
use App\Core\Controller;
use App\Core\Logger;
use Models\Transaction;
use Models\TransactionItem;
use Models\Product;
use Illuminate\Support\Collection;
class StaffTransactionItemController extends Controller {

    private function getCurrentUserId(): ?int {
        return 1; // Placeholder: !!! IMPORTANT: Replace with actual authentication method !!!
    }

    /**
     * Displays the form to add a new transaction item to a specific transaction.
     * Accessible via /staff/transaction_items/add/{transaction_id}
     *
     * @param int $transaction_id The ID of the parent transaction.
     * @return void
     */
    public function add($transaction_id) {
        Logger::log("TRANSACTION_ITEM_ADD: Displaying new item form for Transaction ID: $transaction_id.");

        $transaction = Transaction::find($transaction_id);
        if (!$transaction) {
            Logger::log("TRANSACTION_ITEM_ADD_FAILED: Transaction ID $transaction_id not found.");

            $_SESSION['error_message']="Transaction not found for " . $transaction_id;
            header('Location: /staff/transactions_list');
            exit();
        }

        // Only allow adding items to Pending or Confirmed transactions
        if (!in_array($transaction->status, ['Pending', 'Confirmed'])) {
            Logger::log("TRANSACTION_ITEM_ADD_PREVENTED: Cannot add items to transaction ID $transaction_id with status {$transaction->status}.");
            header('Location: /staff/transactions/show/' . $transaction_id );
            exit();
        }

        $products = Product::all(); // Fetch all products for the dropdown

        $this->view('staff/transaction_items/add', [
            'transaction_id' => $transaction_id,
            'transaction' => $transaction,
            'products' => $products
        ], 'staff');
    }

    /**
     * Handles the POST request to store a new transaction item.
     * Accessible via /staff/transaction_items/store
     *
     * @return void
     */
    public function store() {
    Logger::log('TRANSACTION_ITEM_STORE: Attempting to store new transaction item.');

    $transaction_id = $this->input('transaction_id');
    $product_id     = trim($this->input('product_id'));
    $quantity       = trim($this->input('quantity'));
    $unit_price     = trim($this->input('unit_price')); // For sales
    $current_user_id = $this->getCurrentUserId();

    $errors = [];

    // Validation
    if (empty($transaction_id)) $errors[] = 'Transaction ID is required.';
    if (empty($product_id)) $errors[] = 'Product is required.';
    if (!is_numeric($quantity) || $quantity <= 0) $errors[] = 'Quantity must be a positive number.';
    if (!is_numeric($unit_price) || $unit_price < 0) $errors[] = 'Unit Price must be non-negative.';
    if (empty($current_user_id)) $errors[] = 'User ID not found. Please log in.';

    $transaction = Transaction::find($transaction_id);
    if (!$transaction) $errors[] = 'Parent transaction not found.';
    elseif (!in_array($transaction->status, ['Pending', 'Confirmed'])) {
        $errors[] = 'Cannot add items to a ' . $transaction->status . ' transaction.';
    }

    $product = Product::find($product_id);
    if (!$product) $errors[] = 'Selected product not found.';

    // Prevent duplicate items
    if ($transaction && $product_id) {
        $existingItem = TransactionItem::where('transaction_id', $transaction_id)
                                       ->where('product_id', $product_id)
                                       ->first();
        if ($existingItem) $errors[] = 'This product is already listed in this transaction. Please edit the existing item instead.';
    }

    if (!empty($errors)) {
        Logger::log("TRANSACTION_ITEM_STORE_FAILED: Validation errors: " . implode(', ', $errors));
        $_SESSION['error_message'] = "Error: " . implode('<br>', $errors);
        $products = Product::all();
        $this->view('staff/transaction_items/add', [
            'transaction_id' => $transaction_id,
            'transaction' => $transaction,
            'products' => $products,
            'transaction_item' => (object)[
                'product_id' => $product_id,
                'quantity' => $quantity,
                'unit_price' => $unit_price,
            ]
        ], 'staff');
        return;
    }

    try {
        $transactionItem = new TransactionItem();
        $transactionItem->transaction_id = $transaction_id;
        $transactionItem->product_id = $product_id;
        $transactionItem->quantity = (float)$quantity;
        $transactionItem->created_by_user_id = $current_user_id;
        $transactionItem->updated_by_user_id = $current_user_id;

        // Determine line total
        switch ($transaction->transaction_type) {
            case 'Sale':
            case 'Customer Return':
                $price = $unit_price ?: ($product->unit_price ?? 0);
                $transactionItem->unit_price_at_transaction = $price;
                $transactionItem->line_total = $quantity * $price;
                break;

            case 'Purchase':
            case 'Supplier Return':
                $price = $product->cost_price ?? 0; // use product cost directly
                $transactionItem->unit_price_at_transaction = $price; // for display only
                $transactionItem->line_total = $quantity * $price;
                break;

            case 'Stock Adjustment':
                $transactionItem->unit_price_at_transaction = 0;
                $transactionItem->line_total = 0;
                break;

            default:
                throw new \Exception("Unsupported transaction type: {$transaction->transaction_type}");
        }

        $transactionItem->save();

        // Recalculate totals for parent transaction
        $transaction->load('items');
        $total = $transaction->items->sum('line_total');
        $transaction->total_amount = $total;

        if (in_array($transaction->transaction_type, ['Sale', 'Purchase', 'Customer Return', 'Supplier Return'])) {
            $transaction->amount_received = $total;
        }

        $transaction->save();

        Logger::log("TRANSACTION_ITEM_STORE_SUCCESS: New item (Product ID: {$product_id}, Qty: {$quantity}) added to Transaction ID: {$transaction_id}.");
        $_SESSION['success_message'] = "New Item added to transaction.";
        header('Location: /staff/transactions/show/' . $transaction_id);
        exit();

    } catch (\Exception $e) {
        Logger::log("TRANSACTION_ITEM_STORE_DB_ERROR: Failed to add transaction item - " . $e->getMessage());
        $_SESSION['error_message'] = "Failed to add transaction item. " . $e->getMessage();
        $products = Product::all();
        $this->view('staff/transaction_items/add', [
            'transaction_id' => $transaction_id,
            'transaction' => $transaction,
            'products' => $products,
            'transaction_item' => (object)[
                'product_id' => $product_id,
                'quantity' => $quantity,
                'unit_price' => $unit_price,
            ]
        ], 'staff');
    }
}

    /**
     * Displays the form to edit an existing transaction item.
     * Accessible via /staff/transaction_items/edit/{id}
     *
     * @param int $id The ID of the transaction item to edit.
     * @return void
     */
    public function edit($id) {
        Logger::log("TRANSACTION_ITEM_EDIT: Attempting to display edit form for item ID: $id");

        $transactionItem = TransactionItem::find($id);

        if (!$transactionItem) {
            Logger::log("TRANSACTION_ITEM_EDIT_FAILED: Item ID $id not found for editing.");

            $_SESSION['error_message']="Item not found.";
            header('Location: /staff/transactions_list');
            exit();
        }

        $transaction = Transaction::find($transactionItem->transaction_id);

        if (!$transaction) {
            Logger::log("TRANSACTION_ITEM_EDIT_FAILED: Parent transaction for item ID $id not found.");

            $_SESSION['error_message']="Parent transaction not found.";
            header('Location: /staff/transactions_list');
            exit();
        }

        // Prevent editing item if parent transaction status doesn't allow it
        if (!in_array($transaction->status, ['Pending', 'Confirmed'])) {
            Logger::log("TRANSACTION_ITEM_EDIT_PREVENTED: Cannot edit item ID $id in transaction with status {$transaction->status}.");

            $_SESSION['error_message'] = "Cannot edit item with transaction status of " . $transaction->status;
            header('Location: /staff/transactions/show/' . $transaction->id);
            exit();
        }

        $products = Product::all(); // Fetch all products for the dropdown

        Logger::log("TRANSACTION_ITEM_EDIT_SUCCESS: Displaying edit form for item ID: $id (Transaction ID: {$transaction->id}).");

        $_SESSION['success_message']="Successfully edited item.";
        $this->view('staff/transaction_items/edit', [
            'transaction_item' => $transactionItem, // Pass the existing item for form population
            'transaction' => $transaction,         // Pass parent transaction for context
            'products' => $products,
        ], 'staff');
    }

    /**
     * Handles the POST request to update an existing transaction item.
     * Accessible via /staff/transaction_items/update
     *
     * @return void
     */
    public function update() {
        Logger::log('TRANSACTION_ITEM_UPDATE: Attempting to update transaction item.');

        $id             = $this->input('id');
        $transaction_id = $this->input('transaction_id');
        $product_id     = trim($this->input('product_id'));
        $quantity       = trim($this->input('quantity'));
        $unit_price     = trim($this->input('unit_price')); // This is the input value from the form
        $current_user_id = $this->getCurrentUserId();

        $transactionItem = TransactionItem::find($id);

        // 1. Initial Checks
        if (!$transactionItem) {
            Logger::log("TRANSACTION_ITEM_UPDATE_FAILED: Item ID $id not found for update.");

            $_SESSION['error_message']="Transaction item not found to edit.";
            header('Location: /staff/transactions/show/' . $transaction_id);
            exit();
        }

        $transaction = Transaction::find($transaction_id);
        if (!$transaction) {
            Logger::log("TRANSACTION_ITEM_UPDATE_FAILED: Parent transaction ID $transaction_id not found for item $id.");

            $_SESSION['error_message']="Parent transaction not found.";
            header('Location: /staff/transactions_list');
            exit();
        }

        // Prevent updating item if parent transaction status doesn't allow it
        if (!in_array($transaction->status, ['Pending', 'Confirmed'])) {
            Logger::log("TRANSACTION_ITEM_UPDATE_PREVENTED: Cannot update item ID $id in transaction with status {$transaction->status}.");

            $_SESSION['error_message']= "Cannot update transaction item with transaction status: " .$transaction->status;

            header('Location: /staff/transactions/show/' . $transaction->id);
            exit();
        }

        $errors = [];

        // 2. Input Validation
        if (empty($product_id)) $errors[] = 'Product is required.';
        if (!is_numeric($quantity) || $quantity <= 0) $errors[] = 'Quantity must be a positive number.';
        if (!is_numeric($unit_price) || $unit_price < 0) $errors[] = 'Unit Price must be a non-negative number.';
        if (empty($current_user_id)) $errors[] = 'User ID not found. Cannot update item.';

        $product = null;
        if ($product_id) {
            $product = Product::find($product_id);
            if (!$product) {
                $errors[] = 'Selected product not found.';
            }
        }

        // *** DUPLICATE PRODUCT VALIDATION ON UPDATE (GOOD!) ***
        // Check if this product is already linked to this transaction by a *different* item
        if ($transaction && $product_id) {
            $existingItem = TransactionItem::where('transaction_id', $transaction_id)
                                          ->where('product_id', $product_id)
                                          ->where('id', '!=', $id) // IMPORTANT: Exclude the current item being updated
                                          ->first();
            if ($existingItem) {
                $errors[] = 'Another item in this transaction already uses this product. Please select a different product.';
            }
        }
        // ********************************************************

        // 3. Handle Validation Errors (re-render form with errors)
        if (!empty($errors)) {
            Logger::log("TRANSACTION_ITEM_UPDATE_FAILED: Validation errors for Item ID $id: " . implode(', ', $errors));

            $products = Product::all(); // Re-fetch products for the dropdown
            // Re-populate the transactionItem object with submitted data for form display
            $transactionItem->product_id = $product_id;
            $transactionItem->quantity = $quantity;
            $transactionItem->unit_price_at_transaction = $unit_price; // This is the input value (temporarily for view)
            $transactionItem->line_total = (float)$quantity * (float)$unit_price; // Calculated for form display

            $_SESSION['error_message']="Error: " . implode('<br>', $errors);

            $this->view('staff/transaction_items/edit', [
                'transaction_item' => $transactionItem,
                'transaction' => $transaction,
                'products' => $products,
            ], 'staff');
            return;
        }

        // 4. Assign New Values to Transaction Item and Check for Dirtiness
        $transactionItem->product_id = $product_id;
        $transactionItem->quantity = $quantity;
        // Use 'unit_price_at_transaction' to match DB column
        $transactionItem->unit_price_at_transaction = $unit_price;
        // Calculate and assign to 'line_total' to match DB column
        $transactionItem->line_total = (float)$quantity * (float)$unit_price; // Cast to float for precision

        $transactionItem->updated_by_user_id = $current_user_id;

        // If no actual changes, inform the user and don't hit the DB
        if (!$transactionItem->isDirty()) {
            Logger::log("TRANSACTION_ITEM_UPDATE_INFO: Item ID $id submitted form with no changes.");
            $products = Product::all(); // Re-fetch products

            $_SESSION['warning_message']="No changes were made on items.";
            
            $this->view('staff/transaction_items/edit', [
                'success_message' => 'No changes were made to the transaction item.',
                'transaction_item' => $transactionItem,
                'transaction' => $transaction,
                'products' => $products
            ], 'staff');
            return;
        }

        // 5. Save Changes and Update Parent Transaction's Total Amount
        try {
            // Consider wrapping this in a DB transaction as well
            $transactionItem->save();

            // Update parent transaction's total_amount after item update
            if ($transaction) {
                Logger::log(": UPDATE - Before reloading 'items' relationship for Transaction ID: {$transaction->id}.");

                // Reload the 'items' relationship for the parent transaction
                $transaction->load('items');

                Logger::log(": UPDATE - After reloading 'items' relationship for Transaction ID: {$transaction->id}.");

                $transaction->total_amount = (new Collection($transaction->items))->sum('line_total');

                $transaction->save();
            }

            Logger::log("TRANSACTION_ITEM_UPDATE_SUCCESS: Transaction Item (ID: {$transactionItem->id}) updated successfully for Transaction ID: {$transaction_id}.");

            $_SESSION['success_message']="Item updated successfully";
            header('Location: /staff/transactions/show/' . $transaction_id);
            exit();
        } catch (\Exception $e) {
            Logger::log("TRANSACTION_ITEM_UPDATE_DB_ERROR: Failed to update transaction item ID $id - " . $e->getMessage());
            $products = Product::all(); // Re-fetch products

            $_SESSION['error_message']='An error occurred while updating the item. Please try again. ' . $e->getMessage();

            $this->view('staff/transaction_items/edit', [
                'transaction_item' => $transactionItem,
                'transaction' => $transaction,
                'products' => $products,
            ], 'staff');
            return;
        }
    }

    /**
     * Handles the deletion of a transaction item.
     * Accessible via /staff/transaction_items/delete/{id}
     *
     * @param int $id The ID of the transaction item to delete.
     * @return void
     */
    public function delete($id) {
        Logger::log("TRANSACTION_ITEM_DELETE: Attempting to delete transaction item ID: $id");

        $transactionItem = TransactionItem::find($id);

        // 1. Initial Checks
        if (!$transactionItem) {
            Logger::log("TRANSACTION_ITEM_DELETE_FAILED: Item ID $id not found for deletion.");

            $_SESSION['error_message']="Item to delete not found.";
            header('Location: /staff/transactions_list');
            exit();
        }

        $transaction_id = $transactionItem->transaction_id;
        $transaction = Transaction::find($transaction_id);

        if (!$transaction) {
            Logger::log("TRANSACTION_ITEM_DELETE_FAILED: Parent transaction ID $transaction_id not found for item $id.");
            // This might happen if parent transaction was deleted, but items remained (e.g., no cascade delete or bug)
            // Redirect to a more general list, as the transaction it belonged to doesn't exist.
            $_SESSION['error_message']="Parent transaction not found";

            header('Location: /staff/transactions_list');
            exit();
        }

        // Prevent deletion if parent transaction status doesn't allow it
        if (!in_array($transaction->status, ['Pending', 'Confirmed'])) {
            Logger::log("TRANSACTION_ITEM_DELETE_PREVENTED: Cannot delete item ID $id from transaction with status {$transaction->status}.");

            $_SESSION['error_message']="Cannot delete item with transaction status: " . $transaction->status;
            header('Location: /staff/transactions/show/' . $transaction->id);
            exit();
        }

        // 2. Perform Deletion and Update Parent Total
        try {
            // Consider wrapping this in a DB transaction as well
            $transactionItem->delete();
            Logger::log("TRANSACTION_ITEM_DELETE_SUCCESS: Transaction Item (ID: {$id}) deleted successfully from Transaction ID: {$transaction_id}.");

            // Update parent transaction's total_amount after item deletion
            if ($transaction) {
                Logger::log(": DELETE - Before reloading 'items' relationship for Transaction ID: {$transaction->id}.");

                // Reload the 'items' relationship for the parent transaction
                $transaction->load('items');

                Logger::log(": DELETE - After reloading 'items' relationship for Transaction ID: {$transaction->id}.");

                // Recalculate and save the parent transaction's total_amount
                $transaction->total_amount = (new Collection($transaction->items))->sum('line_total');
                $transaction->save();
            }

            $_SESSION['success_message']="Item successfully deleted";
            header('Location: /staff/transactions/show/' . $transaction_id);
            exit();
        } catch (\Exception $e) {
            Logger::log("TRANSACTION_ITEM_DELETE_DB_ERROR: Failed to delete transaction item ID $id - " . $e->getMessage());

            $_SESSION['error_message']= "Failed to delete item. " .$e->getMessage();
            header('Location: /staff/transactions/show/' . $transaction_id);
            exit();
        }
    }
}