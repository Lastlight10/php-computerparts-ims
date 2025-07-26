<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Models\Transaction; // Assuming Customer has transactions

class Customer extends Model
{
  protected $table = 'customers';

  public $timestamps = true;

  protected $fillable = [
        'customer_type',
        'company_name',
        'contact_first_name',
        'contact_middle_name', // ADDED: contact_middle_name to fillable
        'contact_last_name',
        'email',
        'phone_number',
        'address',
  ];

  public function transactions(): HasMany
  {
      return $this->hasMany(Transaction::class, 'customer_id');
  }
}