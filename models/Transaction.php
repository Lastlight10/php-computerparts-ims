<?php
namespace Models;

use Models\User;
use Models\Customer;
use Models\Supplier;
use Models\TransactionItem;
use Models\Product; // Import the Product model
use App\Core\Logger; // Import the Logger
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Core\Connection;

class Transaction extends Model {
    protected $table = 'transactions';
    protected $primaryKey = 'id';
    protected $fillable = [
        'transaction_type', 'customer_id', 'supplier_id',
        'transaction_date', 'invoice_bill_number', 'total_amount',
        'status', 'notes', 'created_by_user_id', 'updated_by_user_id'
    ];

    // Define relationship with TransactionItem (one-to-many)
    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class, 'transaction_id','id');
    }

    // Define relationship with Customer (many-to-one)
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id','id');
    }

    // Define relationship with Supplier (many-to-one)
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id','id');
    }

    // Define relationship with User who created the transaction
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id','id');
    }

    // Define relationship with User who last updated the transaction
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id','id');
    }

    /**
     * Update product stock quantities and manage serialized product instances
     * based on the transaction type.
     * This method should be called when a transaction's status changes to 'Completed'.
     *
     * @param int $userId The ID of the user performing the update.
     * @return bool True if stock update is successful, false otherwise.
     */
    public function updateProductStock(int $userId): bool
    {
        Logger::log("STOCK_UPDATE: Processing stock for transaction ID: {$this->id}, Type: {$this->transaction_type}");

        if ($this->status !== 'Completed') {
            Logger::log("STOCK_UPDATE: Transaction ID: {$this->id} not 'Completed'. No stock update performed.");
            return true;
        }

        // --- REMOVED PDO TRANSACTION CALLS HERE ---
        // The DB::transaction() in the controller's update method
        // already handles the transaction for the entire process.

        try {
            foreach ($this->items as $item) {
                $product = $item->product;
                if (!$product) {
                    throw new \Exception("Product not found for item ID: {$item->id}");
                }

                $quantityChange = 0;
                $serialNumbers = [];
                $productInstanceRelField = null;
                $productInstanceStatus = null;

                switch ($this->transaction_type) {
                    case 'Purchase':
                        $quantityChange = $item->quantity;
                        $productInstanceRelField = 'purchase_transaction_item_id';
                        $productInstanceStatus = 'In Stock';
                        if ($product->is_serialized) {
                            $serialNumbers = array_column($item->purchasedInstances->toArray(), 'serial_number');
                        }
                        Logger::log("STOCK_UPDATE: Item {$item->id} (Purchase). Quantity change: {$quantityChange}.");
                        break;
                    case 'Sale':
                        $quantityChange = -$item->quantity;
                        $productInstanceRelField = 'sale_transaction_item_id';
                        $productInstanceStatus = 'Sold';
                        if ($product->is_serialized) {
                            $serialNumbers = array_column($item->soldInstances->toArray(), 'serial_number');
                        }
                        Logger::log("STOCK_UPDATE: Item {$item->id} (Sale). Quantity change: {$quantityChange}.");
                        break;
                    case 'Customer Return':
                        $quantityChange = $item->quantity;
                        $productInstanceRelField = 'returned_from_customer_transaction_item_id';
                        $productInstanceStatus = 'In Stock';
                        if ($product->is_serialized) {
                            $serialNumbers = array_column($item->returnedFromCustomerInstances->toArray(), 'serial_number');
                        }
                        Logger::log("STOCK_UPDATE: Item {$item->id} (Customer Return). Quantity change: {$quantityChange}.");
                        break;
                    case 'Supplier Return':
                        $quantityChange = -$item->quantity;
                        $productInstanceRelField = 'returned_to_supplier_transaction_item_id';
                        $productInstanceStatus = 'Removed';
                        if ($product->is_serialized) {
                            $serialNumbers = array_column($item->returnedToSupplierInstances->toArray(), 'serial_number');
                        }
                        Logger::log("STOCK_UPDATE: Item {$item->id} (Supplier Return). Quantity change: {$quantityChange}.");
                        break;
                    case 'Stock Adjustment':
                        if ($item->adjusted_in_instances->isNotEmpty()) {
                             $quantityChange = $item->quantity;
                             $productInstanceRelField = 'adjusted_in_transaction_item_id';
                             $productInstanceStatus = 'In Stock';
                             $serialNumbers = array_column($item->adjusted_in_instances->toArray(), 'serial_number');
                             Logger::log("STOCK_UPDATE: Item {$item->id} (Adjustment In). Quantity change: {$quantityChange}.");
                        } elseif ($item->adjusted_out_instances->isNotEmpty()) {
                             $quantityChange = -$item->quantity;
                             $productInstanceRelField = 'adjusted_out_transaction_item_id';
                             $productInstanceStatus = 'Adjusted Out';
                             $serialNumbers = array_column($item->adjusted_out_instances->toArray(), 'serial_number');
                             Logger::log("STOCK_UPDATE: Item {$item->id} (Adjustment Out). Quantity change: {$quantityChange}.");
                        } else {
                            Logger::log("STOCK_UPDATE: Warning - Stock Adjustment item {$item->id} has no linked instances. No quantity change for product.");
                            continue;
                        }
                        break;
                    default:
                        Logger::log("STOCK_UPDATE: Unknown transaction type '{$this->transaction_type}' for item ID: {$item->id}. Skipping stock update for this item.");
                        continue 2;
                }

                if (!$product->is_serialized) {
                    $product->stock_quantity += $quantityChange;
                    $product->save();
                    Logger::log("STOCK_UPDATE: Non-serialized Product '{$product->name}' (ID: {$product->id}) stock updated by {$quantityChange}. New stock: {$product->stock_quantity}.");
                } else {
                    foreach ($serialNumbers as $serialNum) {
                        $productInstance = ProductInstance::findBySerialNumber($serialNum);
                        if (!$productInstance) {
                            // If a serialized instance is not found, and it's not an inflow, throw an error.
                            // Otherwise, if it's an inflow, it should have been created in the controller logic.
                            if ($this->transaction_type != 'Purchase' && !($this->transaction_type == 'Stock Adjustment' && $productInstanceRelField == 'adjusted_in_transaction_item_id')) {
                                throw new \Exception("Serialized product instance with serial number '{$serialNum}' not found for item ID: {$item->id}.");
                            }
                            // If it's a purchase/inflow type and instance wasn't found here,
                            // it means it wasn't created in the controller. This might be a logic error.
                            // For simplicity, we assume it was created or will be handled correctly by the controller.
                            // Or, we might need to create it here if the controller doesn't.
                            // But for this specific error, we focus on transaction nesting.
                        } else {
                            // Detach previously linked relationships before re-assigning for clarity and to prevent issues
                            $productInstance->fill([
                                'purchase_transaction_item_id'          => null,
                                'sale_transaction_item_id'              => null,
                                'returned_from_customer_transaction_item_id' => null,
                                'returned_to_supplier_transaction_item_id'   => null,
                                'adjusted_in_transaction_item_id'          => null,
                                'adjusted_out_transaction_item_id'         => null,
                            ]);

                            $productInstance->{$productInstanceRelField} = $item->id;
                            $productInstance->status = $productInstanceStatus;
                            $productInstance->updated_by_user_id = $userId;
                            $productInstance->save();
                            Logger::log("STOCK_UPDATE: Serial '{$serialNum}' (Product ID: {$product->id}) updated. Status: '{$productInstanceStatus}', linked to TransactionItem ID: {$item->id}.");
                        }
                    }
                    $product->stock_quantity = ProductInstance::where('product_id', $product->id)
                                                              ->where('status', 'In Stock')
                                                              ->count();
                    $product->save();
                    Logger::log("STOCK_UPDATE: Serialized Product '{$product->name}' (ID: {$product->id}) stock recalculated to: {$product->stock_quantity}.");
                }
            }

            // --- REMOVED $pdo->commit(); ---
            Logger::log("STOCK_UPDATE: Transaction ID: {$this->id} stock update completed successfully.");
            return true; // Return true on success
        } catch (\Exception $e) {
            // --- REMOVED $pdo->rollBack(); ---
            // The exception will be caught by DB::transaction() in the controller,
            // which will then perform the rollback for the entire process.
            Logger::log("ERROR: Stock update failed for transaction ID: {$this->id}. Error: " . $e->getMessage());
            throw $e; // Re-throw the exception so DB::transaction in the controller can catch it and roll back.
        }
    }
}