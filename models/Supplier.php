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
        'contact_person_first_name',
        'contact_person_last_name',
        'email',
        'phone_number',
        'address_street',
        'address_city',
        'address_state_province',
        'address_zip_code',
        'notes', 
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