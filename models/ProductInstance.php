<?php
namespace Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection; // Make sure to import Collection
use Illuminate\Database\Eloquent\Builder; // Import Builder for type hinting in query scopes or complex whereHas

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

    // Define relationships with the specific TransactionItem types

    public function purchaseTransactionItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class, 'purchase_transaction_item_id');
    }

    public function saleTransactionItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class, 'sale_transaction_item_id');
    }

    public function returnedFromCustomerTransactionItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class, 'returned_from_customer_transaction_item_id');
    }

    public function returnedToSupplierTransactionItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class, 'returned_to_supplier_transaction_item_id');
    }

    public function adjustedInTransactionItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class, 'adjusted_in_transaction_item_id');
    }

    public function adjustedOutTransactionItem(): BelongsTo
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
     * Optionally include serial numbers linked to a specific transaction item ID
     * (e.g., when editing an item, its previously linked serials should still be options).
     *
     * @param int $productId
     * @param array $statuses Array of statuses to consider as "available" (e.g., ['In Stock'])
     * @param int|null $currentTransactionItemId Optional: Include serials currently linked to THIS transaction item ID
     * @return \Illuminate\Support\Collection
     */
    public static function getAvailableSerialsByProduct(int $productId, array $statuses = ['In Stock'], ?int $currentTransactionItemId = null): Collection
    {
        $query = self::where('product_id', $productId);

        // Start a group for the main availability conditions
        $query->where(function (Builder $q) use ($statuses, $currentTransactionItemId) {
            // Include instances that are in the specified "available" statuses
            $q->whereIn('status', $statuses);

            // If a current transaction item ID is provided, also include instances
            // that are currently linked to this specific item, regardless of their status.
            // This is crucial for pre-selecting previously chosen serials.
            if ($currentTransactionItemId !== null) {
                $q->orWhere('purchase_transaction_item_id', $currentTransactionItemId)
                  ->orWhere('sale_transaction_item_id', $currentTransactionItemId)
                  ->orWhere('returned_from_customer_transaction_item_id', $currentTransactionItemId)
                  ->orWhere('returned_to_supplier_transaction_item_id', $currentTransactionItemId)
                  ->orWhere('adjusted_in_transaction_item_id', $currentTransactionItemId)
                  ->orWhere('adjusted_out_transaction_item_id', $currentTransactionItemId);
            }
        });

        // Additionally, exclude serials that are currently linked to *other* transaction items
        // unless they are the $currentTransactionItemId.
        // This prevents showing serials already tied up in other sales, returns, etc.
        $query->where(function (Builder $q) use ($currentTransactionItemId) {
            $q->whereNull('purchase_transaction_item_id')
              ->whereNull('sale_transaction_item_id')
              ->whereNull('returned_from_customer_transaction_item_id')
              ->whereNull('returned_to_supplier_transaction_item_id')
              ->whereNull('adjusted_in_transaction_item_id')
              ->whereNull('adjusted_out_transaction_item_id');

            // If a specific item ID is provided, allow that item's linked serials
            if ($currentTransactionItemId !== null) {
                $q->orWhere('purchase_transaction_item_id', $currentTransactionItemId)
                  ->orWhere('sale_transaction_item_id', $currentTransactionItemId)
                  ->orWhere('returned_from_customer_transaction_item_id', $currentTransactionItemId)
                  ->orWhere('returned_to_supplier_transaction_item_id', $currentTransactionItemId)
                  ->orWhere('adjusted_in_transaction_item_id', $currentTransactionItemId)
                  ->orWhere('adjusted_out_transaction_item_id', $currentTransactionItemId);
            }
        });


        return $query->get(['id', 'serial_number', 'status']);
    }

}