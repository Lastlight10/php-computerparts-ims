<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Don't forget to import related models (Product, Transaction, ProductInstance)
// Assuming they are all in the 'Models' namespace as per your setup.
use Models\Product;
use Models\Transaction;
use Models\ProductInstance;

class TransactionItem extends Model
{
    protected $table = 'transaction_items';
    public $timestamps = true;

    protected $fillable = [
        'transaction_id',
        'product_id',
        'quantity',
        'unit_price_at_transaction',
        'line_total',
        'is_returned_item',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_at_transaction' => 'decimal:2',
        'line_total' => 'decimal:2',
        'is_returned_item' => 'boolean',
    ];

    /**
     * Get the transaction that this item belongs to.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    /**
     * Get the product type for this transaction item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the individual serialized instances related to this transaction item when it was PURCHASED.
     */
    public function purchasedInstances(): HasMany // CORRECTED: Renamed for clarity
    {
        return $this->hasMany(ProductInstance::class, 'purchase_transaction_item_id');
    }

    /**
     * Get the individual serialized instances related to this transaction item when it was SOLD.
     */
    public function soldInstances(): HasMany // CORRECTED: Renamed for clarity
    {
        return $this->hasMany(ProductInstance::class, 'sale_transaction_item_id');
    }

    // You might also add an accessor to get all instances based on transaction type if needed
    // public function getAllRelatedInstancesAttribute()
    // {
    //     if ($this->transaction->transaction_type === 'Purchase') {
    //         return $this->purchasedInstances;
    //     } elseif ($this->transaction->transaction_type === 'Sale') {
    //         return $this->soldInstances;
    //     }
    //     return collect(); // Return empty collection for other types or if transaction type is missing
    // }
}