<?php
namespace Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Models\Transaction; 

class User extends Model {
    protected $table = 'users';
    public $timestamps = true;

    protected $fillable = [
        'username',
        'email',
        'password', // Still fillable, but you'll hash it manually before setting it
        'first_name',
        'last_name',
        'middle_name',
        'birthdate',
        'type',
    ];

    /**
     * The attributes that should be hidden for serialization.
     * This ensures the password (and other sensitive data) is not accidentally
     * exposed when the model is converted to an array or JSON.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        // 'remember_token', // Remove this if you're not using Laravel's built-in 'remember me' functionality
    ];

    /**
     * The attributes that should be cast.
     * This tells Eloquent how to interpret certain database column types in your PHP code.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'birthdate' => 'date', // Cast birthdate to a Carbon date instance
        // 'email_verified_at' => 'datetime', // Remove if this column/feature is not used in your schema/logic
        // REMOVED: 'password' => 'hashed', // This cast is Laravel-specific and won't work out-of-the-box with Eloquent-only
    ];

    // --- Relationships ---

    /**
     * Get the transactions created by this user.
     */
    public function createdTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'created_by_user_id');
    }

    /**
     * Get the transactions last updated by this user.
     */
    public function updatedTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'updated_by_user_id');
    }

    // --- Example of a Mutator for Password Hashing (Recommended) ---
    // This allows you to set the password like $user->password = 'plain_text',
    // and it will automatically be hashed before saving.
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = password_hash($value, PASSWORD_DEFAULT);
    }
}