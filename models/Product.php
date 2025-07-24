<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Import the BelongsTo class

class Product extends Model
{
    // Define the table associated with the model
    protected $table = 'products';

    // Indicate that the model should use timestamps (created_at and updated_at)
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     * These are the fields you allow to be set via mass assignment (e.g., Product::create()).
     * Ensure this list matches the columns you intend to fill dynamically, excluding 'id' and timestamps.
     * Foreign key columns (category_id, brand_id) should be fillable.
     * Default values set in migration (e.g., current_stock, reorder_level, is_serialized, is_active) don't mean
     * they can't be filled, but rather define their value if not explicitly provided during creation.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sku',
        'name',
        'description',
        'category_id',
        'brand_id',
        'unit_price',
        'cost_price',
        'current_stock',
        'reorder_level',
        'is_serialized',
        'is_active',
        'location_aisle',
        'location_bin',
    ];

    /**
     * The attributes that should be cast.
     * This tells Eloquent how to interpret certain database column types in your PHP code.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_serialized' => 'boolean',
        'is_active' => 'boolean',
        'unit_price' => 'decimal:2', // Cast to float with 2 decimal places
        'cost_price' => 'decimal:2',  // Cast to float with 2 decimal places
        'current_stock' => 'integer',
        'reorder_level' => 'integer',
    ];

    // --- Relationships ---

    /**
     * Get the category that owns the product.
     *
     * @return BelongsTo
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the brand that owns the product.
     *
     * @return BelongsTo
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    // You might also add relationships to product_instances if you track serialized items
    // public function instances(): HasMany
    // {
    //     return $this->hasMany(ProductInstance::class);
    // }

    // And potentially to transaction_items if you want to see which transactions this product type was part of
    // public function transactionItems(): HasMany
    // {
    //     return $this->hasMany(TransactionItem::class);
    // }
}