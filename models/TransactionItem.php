<?php
namespace Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionItem extends Model {
    protected $table = 'transaction_items';
    protected $primaryKey = 'id';
    protected $fillable = [
        'transaction_id', 'product_id', 'quantity',
        'unit_price_at_transaction', 'line_total',
        'created_by_user_id', 'updated_by_user_id'
    ];

    // Define relationship with Transaction (many-to-one)
    public function transaction(): BelongsTo // Added return type hint
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    // Define relationship with Product (many-to-one)
    public function product(): BelongsTo // Added return type hint
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // Define relationship with the User who created this item
    public function createdBy(): BelongsTo // Added return type hint
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // Define relationship with the User who last updated this item
    public function updatedBy(): BelongsTo // Added return type hint
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    // Define relationship with ProductInstances (one-to-many)

    // A TransactionItem for a PURCHASE can have many ProductInstances
    public function purchasedInstances(): HasMany // Added return type hint
    {
        return $this->hasMany(ProductInstance::class, 'purchase_transaction_item_id');
    }

    // A TransactionItem for a SALE can have many ProductInstances
    public function soldInstances(): HasMany // Added return type hint
    {
        return $this->hasMany(ProductInstance::class, 'sale_transaction_item_id');
    }

    // NEW: A TransactionItem for a CUSTOMER RETURN can have many ProductInstances
    public function returnedFromCustomerInstances(): HasMany // Added return type hint
    {
        return $this->hasMany(ProductInstance::class, 'returned_from_customer_transaction_item_id');
    }

    // NEW: A TransactionItem for a SUPPLIER RETURN can have many ProductInstances
    public function returnedToSupplierInstances(): HasMany // Added return type hint
    {
        return $this->hasMany(ProductInstance::class, 'returned_to_supplier_transaction_item_id');
    }

    // NEW: A TransactionItem for an ADJUSTMENT INFLOW can have many ProductInstances
    public function adjustedInInstances(): HasMany // Added return type hint
    {
        return $this->hasMany(ProductInstance::class, 'adjusted_in_transaction_item_id');
    }

    // NEW: A TransactionItem for an ADJUSTMENT OUTFLOW can have many ProductInstances
    public function adjustedOutInstances(): HasMany // Added return type hint
    {
        return $this->hasMany(ProductInstance::class, 'adjusted_out_transaction_item_id');
    }
}