<?php
namespace Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection; // Make sure to import Collection

class ProductInstance extends Model {
    protected $table = 'product_instances';
    protected $primaryKey = 'id';
    protected $fillable = [
        'product_id', 'serial_number', 'status', 'purchase_transaction_item_id',
        'sale_transaction_item_id', 'cost_at_receipt', 'warranty_expires_at',
        'returned_from_customer_transaction_item_id',
        'returned_to_supplier_transaction_item_id',
        'adjusted_in_transaction_item_id',
        'adjusted_out_transaction_item_id',
        'created_by_user_id',
        'updated_by_user_id'
    ];

    // Define relationship with Product (many-to-one)
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // Define relationship with the TransactionItem that *purchased* this instance
    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class, 'purchase_transaction_item_id');
    }

    // Define relationship with the TransactionItem that *sold* this instance
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class, 'sale_transaction_item_id');
    }

    // NEW: Define relationship with the TransactionItem that *returned from customer* this instance
    public function returnedFromCustomerItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class, 'returned_from_customer_transaction_item_id');
    }

    // NEW: Define relationship with the TransactionItem that *returned to supplier* this instance
    public function returnedToSupplierItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class, 'returned_to_supplier_transaction_item_id');
    }

    // NEW: Define relationship with the TransactionItem that *adjusted in* this instance
    public function adjustedInItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class, 'adjusted_in_transaction_item_id');
    }

    // NEW: Define relationship with the TransactionItem that *adjusted out* this instance
    public function adjustedOutItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class, 'adjusted_out_transaction_item_id');
    }

    // Define relationship with the User who created this item
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // Define relationship with the User who last updated this item
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public static function findBySerialNumber(string $serial_number): ?self
    {
        return self::where('serial_number', $serial_number)->first();
    }

    /**
     * Get available serial numbers for a given product ID based on status.
     * Optionally exclude serial numbers linked to a specific transaction item
     * (e.g., when editing an item, its previously linked serials should still be options).
     *
     * @param int $productId
     * @param array $statuses Array of statuses to consider as "available"
     * @param int|null $excludeTransactionItemId Optional: Exclude serials linked to this transaction item ID
     * @return \Illuminate\Support\Collection
     */
    public static function getAvailableSerialsByProduct(int $productId, array $statuses = ['In Stock', 'Returned'], ?int $excludeTransactionItemId = null): Collection
    {
        $query = self::where('product_id', $productId)
                      ->whereIn('status', $statuses);

        // Exclude serials currently linked to other transaction types if they are being used elsewhere
        // This is a more complex logic, but for now, we'll focus on the excludeTransactionItemId.
        if ($excludeTransactionItemId !== null) {
            $query->orWhere(function ($q) use ($excludeTransactionItemId) {
                $q->where('purchase_transaction_item_id', $excludeTransactionItemId)
                  ->orWhere('sale_transaction_item_id', $excludeTransactionItemId)
                  ->orWhere('returned_from_customer_transaction_item_id', $excludeTransactionItemId)
                  ->orWhere('returned_to_supplier_transaction_item_id', $excludeTransactionItemId)
                  ->orWhere('adjusted_in_transaction_item_id', $excludeTransactionItemId)
                  ->orWhere('adjusted_out_transaction_item_id', $excludeTransactionItemId);
            });
        }

        return $query->get(['id', 'serial_number', 'status']);
    }
}