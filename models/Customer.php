<?php

namespace Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany; 

use Models\Transaction;

class Customer extends Model
{
  protected $table = 'customers'; // CRITICAL FIX: This must be 'customers'

  // Indicate that the model should use timestamps (created_at and updated_at)
  public $timestamps = true;

  protected $fillable = [
        'customer_type',
        'company_name',
        'contact_person_first_name',
        'contact_person_last_name',
        'email',
        'phone_number',
        'address', // Assuming your 'customers' table now truly has a single 'address' column
        // If your customers table still has address_street, address_city, etc.,
        // you should list those specific columns here instead of a generic 'address'.
  ];

  // --- Common Relationships ---

  /**
   * Get the transactions associated with the customer.
   */
  public function transactions(): HasMany
  {
      return $this->hasMany(Transaction::class, 'customer_id');
  }
}