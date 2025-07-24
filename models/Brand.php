<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Import the BelongsTo class

class Brand extends Model
{
  protected $table = 'brands';

  // Indicate that the model should use timestamps (created_at and updated_at)
  public $timestamps = true;
  protected $fillable = [
        'name',
        'website',
        'contact_email',
  ];
}