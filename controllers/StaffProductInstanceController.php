<?php
namespace Controllers;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Connection; // Assuming you still use this for DB connection init if not handled by Eloquent

use Models\Product;
use Models\ProductInstance;
use Models\TransactionItem; // Required for relationships
use Models\User; // Required for updated_by_user_id

// As previously discussed, 'vendor/autoload.php' should ideally be in your main application bootstrap
// require_once 'vendor/autoload.php'; // This should be handled by your application's entry point

class StaffProductInstanceController extends Controller {

    /**
     * Helper to get current user ID. Replace with your actual authentication method.
     * @return int|null
     */
    private function getCurrentUserId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Displays a single product instance's details.
     * Accessible via /staff/product_instances/show/{id}
     * (Primarily for detailed view, but often integrated into product show page)
     *
     * @param int $id The ID of the product instance to show.
     * @return void
     */
    public function show($id) {
        Logger::log("PRODUCT_INSTANCE_SHOW: Attempting to show product instance ID: {$id}");

        $instance = ProductInstance::with([
            'product',
            'purchaseTransactionItem.transaction',
            'saleTransactionItem.transaction',
            'returnedFromCustomerTransactionItem.transaction',
            'returnedToSupplierTransactionItem.transaction',
            'adjustedInTransactionItem.transaction',
            'adjustedOutTransactionItem.transaction',
            'createdBy',
            'updatedBy'
        ])->find($id);

        if (!$instance) {
            Logger::log("PRODUCT_INSTANCE_SHOW_ERROR: Product instance ID {$id} not found.");
            $_SESSION['error_message'] = "Product instance not found.";
            header('Location: /staff/products_list'); // Redirect to products list or a more appropriate page
            exit();
        }

        Logger::log("PRODUCT_INSTANCE_SHOW_SUCCESS: Displaying product instance ID: {$id}.");
        $this->view('staff/product_instances/show', ['instance' => $instance], 'staff'); // Changed path
    }

    /**
     * Displays the form to edit an existing product instance.
     * Accessible via /staff/product_instances/edit/{id}
     *
     * @param int $id The ID of the product instance to edit.
     * @return void
     */
    public function edit($id) {
        Logger::log("PRODUCT_INSTANCE_EDIT: Attempting to display edit form for product instance ID: {$id}");

        $instance = ProductInstance::with([
            'product',
            'purchaseTransactionItem.transaction',
            'saleTransactionItem.transaction',
            'returnedFromCustomerTransactionItem.transaction',
            'returnedToSupplierTransactionItem.transaction',
            'adjustedInTransactionItem.transaction',
            'adjustedOutTransactionItem.transaction',
        ])->find($id);

        if (!$instance) {
            Logger::log("PRODUCT_INSTANCE_EDIT_ERROR: Product instance ID {$id} not found for editing.");
            $_SESSION['error_message'] = "Product instance not found for editing.";
            header('Location: /staff/products_list', $_SESSION['error_message']); // Redirect to products list or appropriate page
            exit();
        }

        // Get all possible ProductInstance statuses for the filter dropdown
        $product_instance_statuses = [
            'In Stock',
            'Sold',
            'Returned - Resalable',
            'Returned - Defective',
            'Repairing',
            'Scrapped',
            'Pending Stock', // Used for items in pending transactions
            'Adjusted Out',  // Used for stock adjustments that remove items
            'Removed'        // Generic status for items no longer tracked/discarded
        ];

        Logger::log("PRODUCT_INSTANCE_EDIT_SUCCESS: Displaying edit form for product instance ID: {$id}.");
        $this->view('staff/product_instances/edit', [ // Changed path
            'instance' => $instance,
            'product_instance_statuses' => $product_instance_statuses,
        ], 'staff');
    }

    /**
     * Handles the POST request to update an existing product instance.
     * Accessible via /staff/product_instances/update
     *
     * @return void
     */
    public function update() {
        Logger::log("PRODUCT_INSTANCE_UPDATE: Attempting to update product instance.");

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Logger::log("PRODUCT_INSTANCE_UPDATE_ERROR: Invalid request method. Must be POST.");
            $_SESSION['error_message'] = 'Invalid request method.';
            header('Location: /staff/products_list');
            exit();
        }

        $instanceId = $this->input('id');
        $newStatus = $this->input('status');
        $warrantyExpiresAt = $this->input('warranty_expires_at');

        $instance = ProductInstance::with('product')->find($instanceId); // Eager load product for stock updates

        if (!$instance) {
            Logger::log("PRODUCT_INSTANCE_UPDATE_ERROR: Product instance ID {$instanceId} not found for update.");
            $_SESSION['error_message'] = "Product instance not found for update.";
            header('Location: /staff/products_list');
            exit();
        }

        $originalStatus = $instance->status;
        $productId = $instance->product_id;

        // 1. Validation
        $errors = [];
        $allowed_statuses = [
            'In Stock', 'Sold', 'Returned - Resalable', 'Returned - Defective',
            'Repairing', 'Scrapped', 'Pending Stock', 'Adjusted Out', 'Removed'
        ];

        if (empty($newStatus) || !in_array($newStatus, $allowed_statuses)) {
            $errors[] = 'Invalid status selected.';
        }
        if (!empty($warrantyExpiresAt) && !strtotime($warrantyExpiresAt)) {
            $errors[] = 'Invalid warranty expiration date format.';
        }

        if (!empty($errors)) {
            Logger::log("PRODUCT_INSTANCE_UPDATE_FAILED: Validation errors for instance ID {$instanceId}: " . implode(', ', $errors));
            $_SESSION['error_message'] = 'Validation error: ' . implode('<br>', $errors);
            header('Location: /staff/product_instances/edit/'.$instanceId); // Changed path
            exit();
        }

        // 2. Update Product Instance Properties
        $instance->status = $newStatus;
        $instance->warranty_expires_at = !empty($warrantyExpiresAt) ? $warrantyExpiresAt : null;
        $instance->updated_by_user_id = $this->getCurrentUserId();

        // Check if any actual changes were made to the instance itself
        if (!$instance->isDirty()) {
            Logger::log("PRODUCT_INSTANCE_UPDATE_INFO: Instance ID {$instanceId} submitted form with no changes.");
            $_SESSION['success_message'] = 'No changes were made to the product unit.';
            header('Location: /staff/products/show/'.$instanceId); // Redirect back to product details
            exit();
        }

        // 3. Handle Stock Adjustments based on Status Change
        // This logic applies if the instance's status changes in a way that affects the parent product's stock.
        $product = $instance->product; // Already eager loaded

        if ($product && $product->is_serialized) {
            if ($originalStatus === 'In Stock' && !in_array($newStatus, ['In Stock', 'Pending Stock', 'Repairing'])) {
                // Was in stock, now moved out (Sold, Scrapped, Adjusted Out, Removed, Returned - Defective)
                $product->decrement('current_stock');
                Logger::log("STOCK_ADJUSTMENT: Decremented stock for Product ID {$productId} due to instance {$instanceId} status change from 'In Stock' to '{$newStatus}'.");
            } elseif (!in_array($originalStatus, ['In Stock', 'Pending Stock', 'Repairing']) && $newStatus === 'In Stock') {
                // Was out of stock, now moved back in (e.g., Returned - Resalable)
                $product->increment('current_stock');
                Logger::log("STOCK_ADJUSTMENT: Incremented stock for Product ID {$productId} due to instance {$instanceId} status change from '{$originalStatus}' to 'In Stock'.");
            }
            // Save the product with updated stock
            $product->save();
        }

        try {
            $instance->save();
            Logger::log("PRODUCT_INSTANCE_UPDATE_SUCCESS: Product instance ID {$instanceId} updated successfully. Status changed from '{$originalStatus}' to '{$newStatus}'.");
            
            $_SESSION['success_message'] = 'Product unit updated successfully!';
            header('Location: /staff/products/show/'.$instanceId); // Redirect back to product details
            exit();

        } catch (\Exception $e) {
            Logger::log("PRODUCT_INSTANCE_UPDATE_DB_ERROR: Failed to update product instance ID {$instanceId} - " . $e->getMessage());
            
            $_SESSION['error_message'] = 'An error occurred while updating the product unit: ' . $e->getMessage();
            header('Location: /staff/product_instances/edit/'.$instanceId); // Changed path
            exit();
        }
    }

    /**
     * Handles the deletion of a product instance.
     * Accessible via /staff/product_instances/delete/{id}
     *
     * @param int $id The ID of the product instance to delete.
     * @return void
     */
    public function delete($id) {
        Logger::log("PRODUCT_INSTANCE_DELETE: Attempting to delete product instance ID: $id");

        $instance = ProductInstance::with('product')->find($id);

        if (!$instance) {
            Logger::log("PRODUCT_INSTANCE_DELETE_FAILED: Product instance ID $id not found for deletion.");
            $_SESSION['error_message'] = 'Product instance not found for deletion.';
            header('Location: /staff/products_list'); // Redirect to products list or appropriate page
            exit();
        }

        $productId = $instance->product_id;
        $originalStatus = $instance->status;

        try {
            // Prevent deletion if the instance is currently marked as 'In Stock'
            // This prevents accidental deletion of active inventory.
            if ($originalStatus === 'In Stock') {
                Logger::log("PRODUCT_INSTANCE_DELETE_FAILED: Product instance ID $id cannot be deleted because its status is 'In Stock'.");
                
                $_SESSION['error_message'] = 'Product unit cannot be deleted while its status is "In Stock". Change its status first (e.g., to "Removed" or "Scrapped").';
                header('Location: /staff/product_instances/edit/'.$id); // Changed path
                exit();
            }

            // If the instance was 'In Stock' before deletion, decrement parent product's stock
            // This handles cases where its status might have been changed to something like 'Removed'
            // just before deletion. If it was 'In Stock' and then deleted, stock should decrease.
            // However, the check above prevents deleting 'In Stock' items directly.
            // So, this stock adjustment is more relevant if you allow deleting 'Removed' or 'Scrapped' items
            // that *were* in stock at some point.
            if ($instance->product->is_serialized && $originalStatus === 'In Stock') {
                 $instance->product->decrement('current_stock');
                 $instance->product->save();
                 Logger::log("STOCK_ADJUSTMENT: Decremented stock for Product ID {$productId} due to deletion of instance {$id} (was 'In Stock').");
            }


            $instance->delete();
            Logger::log("PRODUCT_INSTANCE_DELETE_SUCCESS: Product instance ID {$id} deleted successfully.");
            $_SESSION['success_message'] = 'Product unit deleted successfully!';
            header('Location: /staff/products/show/'.$id); // Redirect back to parent product details
            exit();
        } catch (\Exception $e) {
            Logger::log("PRODUCT_INSTANCE_DELETE_DB_ERROR: Failed to delete product instance ID $id - " . $e->getMessage());
            $_SESSION['error_message'] = 'An error occurred while deleting the product unit: ' . $e->getMessage();
            header('Location: /staff/product_instances/edit/' . $id); // Changed path
            exit();
        }
    }
}
