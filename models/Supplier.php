<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;
use Models\Transaction;

use Illuminate\Database\Eloquent\Relations\HasMany; 
use Illuminate\Database\Eloquent\Relations\BelongsToMany; 



class Supplier extends Model
{
  protected $table = 'suppliers'; 

  public $timestamps = true;

  protected $fillable = [
        'supplier_type',
        'company_name',
        // CORRECTED: Column names to match schema
        'contact_first_name',
        'contact_middle_name',
        'contact_last_name',
        'email',
        'phone_number',
        'address',
  ];

  // --- Relationships ---

  /**
   * Get the transactions (purchases or supplier returns) associated with the supplier.
   */
  public function transactions(): HasMany
  {
      return $this->hasMany(Transaction::class, 'supplier_id');
  }

  public function products(): BelongsToMany
    {
        // Eloquent assumes the junction table name is 'product_supplier' 
        // (alphabetical order of the two models in singular form) 
        // and the foreign keys are 'supplier_id' and 'product_id'.
        // Since your junction table is named 'product_suppliers', 
        // you should explicitly pass the table name for clarity and correctness 
        // if you want to avoid relying on Eloquent's naming convention.
        
        return $this->belongsToMany(
            Product::class,      // The related model
            'product_suppliers', // The pivot/junction table name
            'supplier_id',       // The foreign key on the pivot table for this model (Supplier)
            'product_id'         // The foreign key on the pivot table for the related model (Product)
        );
    }
}