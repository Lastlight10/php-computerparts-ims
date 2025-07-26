<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Models\Category; // Assuming you have a Category model
use Models\Brand;    // Assuming you have a Brand model
use Models\TransactionItem;
use Models\ProductInstance;

class Product extends Model
{
    protected $table = 'products';
    public $timestamps = true;

    protected $fillable = [
        'sku',
        'name', // CHANGED: from 'product_name' to 'name'
        'description',
        'category_id',
        'brand_id',
        'unit_price',
        'cost_price',
        'current_stock',
        'reorder_level', // CHANGED: from 'reorder_point' to 'reorder_level'
        'is_serialized',
        'is_active',
        'location_aisle',
        'location_bin',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'current_stock' => 'integer',
        'reorder_level' => 'integer',
        'is_serialized' => 'boolean',
        'is_active' => 'boolean',
    ];
    public function instances() {
        return $this->hasMany(ProductInstance::class, 'product_id');
    }
    // Relationships (assuming Category and Brand models exist)
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function transactionItems(): HasMany
    {
        return $this->hasMany(TransactionItem::class, 'product_id');
    }

    public function productInstances(): HasMany
    {
        return $this->hasMany(ProductInstance::class, 'product_id');
    }
}