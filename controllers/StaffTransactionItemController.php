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
            header('Location: /staff/transactions_list?error=' . urlencode('Parent transaction not found.'));
            exit();
        }

        // Only allow adding items to Pending or Confirmed transactions
        if (!in_array($transaction->status, ['Pending', 'Confirmed'])) {
            Logger::log("TRANSACTION_ITEM_ADD_PREVENTED: Cannot add items to transaction ID $transaction_id with status {$transaction->status}.");
            header('Location: /staff/transactions/show/' . $transaction_id . '?error=' . urlencode('Cannot add items to a ' . $transaction->status . ' transaction.'));
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

        $transaction_id = trim($this->input('transaction_id'));
        $product_id     = trim($this->input('product_id'));
        $quantity       = trim($this->input('quantity'));
        $unit_price     = trim($this->input('unit_price')); // This is the input value from the form
        $current_user_id = $this->getCurrentUserId();

        $errors = [];

        // 1. Basic Input Validation
        if (empty($transaction_id)) $errors[] = 'Transaction ID is required.';
        if (empty($product_id)) $errors[] = 'Product is required.';
        // Ensure quantity is a valid positive number
        if (!is_numeric($quantity) || $quantity <= 0) $errors[] = 'Quantity must be a positive number.';
        // Ensure unit_price is a valid non-negative number
        if (!is_numeric($unit_price) || $unit_price < 0) $errors[] = 'Unit Price must be a non-negative number.';
        if (empty($current_user_id)) $errors[] = 'User ID not found. Please log in.';

        $transaction = null;
        if ($transaction_id) {
            $transaction = Transaction::find($transaction_id);
            if (!$transaction) {
                $errors[] = 'Parent transaction not found.';
            } else {
                // Prevent adding items if the parent transaction status doesn't allow it
                if (!in_array($transaction->status, ['Pending', 'Confirmed'])) {
                    $errors[] = 'Cannot add items to a ' . $transaction->status . ' transaction.';
                }
            }
        }

        $product = null;
        if ($product_id) {
            $product = Product::find($product_id);
            if (!$product) {
                $errors[] = 'Selected product not found.';
            }
            // Optional: You might want to pull unit_price from the product if not provided,
            // or if the transaction type implies using the product's default price.
            // if (empty($unit_price) && $product) {
            //     $unit_price = $product->current_unit_price; // Assuming a 'current_unit_price' field on Product
            // }
        }

        // *** DUPLICATE PRODUCT VALIDATION (GOOD!) ***
        // Check if this product is already linked to this transaction
        if ($transaction && $product_id) { // Only check if both exist
            $existingItem = TransactionItem::where('transaction_id', $transaction_id)
                                          ->where('product_id', $product_id)
                                          ->first();
            if ($existingItem) {
                $errors[] = 'This product is already listed in this transaction. Please edit the existing item instead.';
            }
        }
        // ********************************************

        // 2. Handle Validation Errors (re-render form with errors)
        if (!empty($errors)) {
            Logger::log("TRANSACTION_ITEM_STORE_FAILED: Validation errors: " . implode(', ', $errors));
            $products = Product::all(); // Re-fetch products for the dropdown
            $this->view('staff/transaction_items/add', [
                'transaction_id' => $transaction_id,
                'transaction' => $transaction, // Pass transaction back for consistent view rendering
                'products' => $products,
                'error' => implode('<br>', $errors),
                'transaction_item' => (object)[ // Repopulate form fields with submitted data
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                    'unit_price' => $unit_price, // The input value
                ]
            ], 'staff');
            return;
        }

        // 3. Create and Save New Transaction Item
        try {
            // Consider wrapping this in a DB transaction if your framework supports it
            // (e.g., Capsule/Eloquent: \Illuminate\Database\Capsule\Manager::transaction(function() use (...) { ... });)
            // This ensures atomicity: either both item and total_amount are updated, or neither are.

            $transactionItem = new TransactionItem();
            $transactionItem->transaction_id = $transaction_id;
            $transactionItem->product_id = $product_id;
            $transactionItem->quantity = $quantity;
            // Use 'unit_price_at_transaction' to match DB column name
            $transactionItem->unit_price_at_transaction = $unit_price;
            // Calculate and assign to 'line_total' to match DB column name
            $transactionItem->line_total = (float)$quantity * (float)$unit_price; // Cast to float for precision

            $transactionItem->created_by_user_id = $current_user_id;
            $transactionItem->updated_by_user_id = $current_user_id;

            $transactionItem->save();

            // 4. Update Parent Transaction's Total Amount
            if ($transaction) {
                Logger::log(": STORE - Before reloading 'items' relationship for Transaction ID: {$transaction->id}.");

                // Reload the 'items' relationship to get the latest data, including the newly added item
                // This is crucial to sum the *current* items correctly.
                $transaction->load('items');

                Logger::log(": STORE - After reloading 'items' relationship for Transaction ID: {$transaction->id}.");

                // Sum 'line_total' from all associated items to update the parent transaction's total_amount
                $transaction->total_amount = $transaction->items->sum('line_total');
                $transaction->save(); // Save the updated total amount on the parent transaction
            }

            Logger::log("TRANSACTION_ITEM_STORE_SUCCESS: New item (Product ID: {$product_id}, Qty: {$quantity}) added to Transaction ID: {$transaction_id}.");
            header('Location: /staff/transactions/show/' . $transaction_id . '?success_message=' . urlencode('Item added successfully!'));
            exit();

        } catch (\Exception $e) {
            Logger::log("TRANSACTION_ITEM_STORE_DB_ERROR: Failed to add transaction item - " . $e->getMessage());
            $products = Product::all(); // Re-fetch products for the dropdown
            $this->view('staff/transaction_items/add', [
                'transaction_id' => $transaction_id,
                'transaction' => $transaction,
                'products' => $products,
                'error' => 'An error occurred while adding the item: ' . $e->getMessage(),
                'transaction_item' => (object)[
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                    'unit_price' => $unit_price, // The input value
                ]
            ], 'staff');
            return;
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
            header('Location: /staff/transactions_list?error=' . urlencode('Transaction item not found.'));
            exit();
        }

        $transaction = Transaction::find($transactionItem->transaction_id);

        if (!$transaction) {
            Logger::log("TRANSACTION_ITEM_EDIT_FAILED: Parent transaction for item ID $id not found.");
            header('Location: /staff/transactions_list?error=' . urlencode('Parent transaction for item not found.'));
            exit();
        }

        // Prevent editing item if parent transaction status doesn't allow it
        if (!in_array($transaction->status, ['Pending', 'Confirmed'])) {
            Logger::log("TRANSACTION_ITEM_EDIT_PREVENTED: Cannot edit item ID $id in transaction with status {$transaction->status}.");
            header('Location: /staff/transactions/show/' . $transaction->id . '?error=' . urlencode('Cannot edit items in a ' . $transaction->status . ' transaction.'));
            exit();
        }

        $products = Product::all(); // Fetch all products for the dropdown

        Logger::log("TRANSACTION_ITEM_EDIT_SUCCESS: Displaying edit form for item ID: $id (Transaction ID: {$transaction->id}).");
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

        $id             = trim($this->input('id'));
        $transaction_id = trim($this->input('transaction_id'));
        $product_id     = trim($this->input('product_id'));
        $quantity       = trim($this->input('quantity'));
        $unit_price     = trim($this->input('unit_price')); // This is the input value from the form
        $current_user_id = $this->getCurrentUserId();

        $transactionItem = TransactionItem::find($id);

        // 1. Initial Checks
        if (!$transactionItem) {
            Logger::log("TRANSACTION_ITEM_UPDATE_FAILED: Item ID $id not found for update.");
            header('Location: /staff/transactions/show/' . $transaction_id . '?error=' . urlencode('Transaction item not found.'));
            exit();
        }

        $transaction = Transaction::find($transaction_id);
        if (!$transaction) {
            Logger::log("TRANSACTION_ITEM_UPDATE_FAILED: Parent transaction ID $transaction_id not found for item $id.");
            header('Location: /staff/transactions_list?error=' . urlencode('Parent transaction not found for item.'));
            exit();
        }

        // Prevent updating item if parent transaction status doesn't allow it
        if (!in_array($transaction->status, ['Pending', 'Confirmed'])) {
            Logger::log("TRANSACTION_ITEM_UPDATE_PREVENTED: Cannot update item ID $id in transaction with status {$transaction->status}.");
            header('Location: /staff/transactions/show/' . $transaction->id . '?error=' . urlencode('Cannot update items in a ' . $transaction->status . ' transaction.'));
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

            $this->view('staff/transaction_items/edit', [
                'error' => implode('<br>', $errors),
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
            header('Location: /staff/transactions/show/' . $transaction_id . '?success_message=' . urlencode('Item updated successfully!'));
            exit();
        } catch (\Exception $e) {
            Logger::log("TRANSACTION_ITEM_UPDATE_DB_ERROR: Failed to update transaction item ID $id - " . $e->getMessage());
            $products = Product::all(); // Re-fetch products
            $this->view('staff/transaction_items/edit', [
                'error' => 'An error occurred while updating the item. Please try again. ' . $e->getMessage(),
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
            header('Location: /staff/transactions_list?error=' . urlencode('Transaction item not found for deletion.'));
            exit();
        }

        $transaction_id = $transactionItem->transaction_id;
        $transaction = Transaction::find($transaction_id);

        if (!$transaction) {
            Logger::log("TRANSACTION_ITEM_DELETE_FAILED: Parent transaction ID $transaction_id not found for item $id.");
            // This might happen if parent transaction was deleted, but items remained (e.g., no cascade delete or bug)
            // Redirect to a more general list, as the transaction it belonged to doesn't exist.
            header('Location: /staff/transactions_list?error=' . urlencode('Parent transaction not found for item deletion.'));
            exit();
        }

        // Prevent deletion if parent transaction status doesn't allow it
        if (!in_array($transaction->status, ['Pending', 'Confirmed'])) {
            Logger::log("TRANSACTION_ITEM_DELETE_PREVENTED: Cannot delete item ID $id from transaction with status {$transaction->status}.");
            header('Location: /staff/transactions/show/' . $transaction->id . '?error=' . urlencode('Cannot delete items from a ' . $transaction->status . ' transaction.'));
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

            header('Location: /staff/transactions/show/' . $transaction_id . '?success_message=' . urlencode('Item deleted successfully!'));
            exit();
        } catch (\Exception $e) {
            Logger::log("TRANSACTION_ITEM_DELETE_DB_ERROR: Failed to delete transaction item ID $id - " . $e->getMessage());
            header('Location: /staff/transactions/show/' . $transaction_id . '?error=' . urlencode('An error occurred while deleting the item: ' . $e->getMessage()));
            exit();
        }
    }
}