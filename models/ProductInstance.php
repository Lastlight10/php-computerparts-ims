<?php

namespace Models; // Or your actual namespace, e.g., App\Models

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Don't forget to import related models
use Models\Product;
use Models\TransactionItem; // Assuming you have this model

class ProductInstance extends Model
{
    protected $table = 'product_instances';
    public $timestamps = true; // Assuming created_at and updated_at

    protected $fillable = [
        'product_id',
        'serial_number',
        'status',
        'purchase_transaction_item_id',
        'sale_transaction_item_id',
        'cost_at_receipt',
        'warranty_expires_at',
    ];

    protected $casts = [
        'cost_at_receipt' => 'decimal:2',
        'warranty_expires_at' => 'datetime',
    ];

    /**
     * Get the product type this instance belongs to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the transaction item that this unit was purchased under.
     */
    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class, 'purchase_transaction_item_id');
    }

    /**
     * Get the transaction item that this unit was sold under.
     */
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class, 'sale_transaction_item_id');
    }
}