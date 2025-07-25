<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany; // ADDED: Import HasMany

use Models\User;
use Models\Customer;
use Models\Supplier;
use Models\TransactionItem;

class Transaction extends Model
{
  protected $table = 'transactions';
  public $timestamps = true;

  protected $fillable = [
        'transaction_type',
        'customer_id',
        'supplier_id',
        'transaction_date',
        'invoice_bill_number',
        'total_amount',
        'status',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
  ];

  protected $casts = [ // ADDED: Casts for transaction-specific fields
      'transaction_date' => 'datetime',
      'total_amount' => 'decimal:2',
  ];

  /**
   * Get the customer associated with the transaction (for sales/returns from customer).
   */
  public function customer(): BelongsTo
    {
        // Assuming Customer model is in the same 'Models' namespace
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the supplier that provided the transaction (for purchases/returns to supplier).
     */
    public function supplier(): BelongsTo
    {
        // Assuming Supplier model is in the same 'Models' namespace
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * Get the user who created the transaction.
     */
    public function createdBy(): BelongsTo
    {
        // Assuming User model is in the 'Models' namespace
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the user who last updated the transaction.
     */
    public function updatedBy(): BelongsTo
    {
        // Assuming User model is in the 'Models' namespace
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * Get the transaction items for this transaction.
     */
    public function items(): HasMany // ADDED: HasMany relationship to TransactionItem
    {
        // Assuming TransactionItem model is in the same 'Models' namespace
        return $this->hasMany(TransactionItem::class, 'transaction_id');
    }
}