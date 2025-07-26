<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;
use Models\Transaction;

use Illuminate\Database\Eloquent\Relations\HasMany; 



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
}