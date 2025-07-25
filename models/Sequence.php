<?php

namespace Models; // Or your actual namespace, e.g., App\Models

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;

class Sequence extends Model
{
    protected $table = 'sequences';

    // The 'type' column is your primary key for this table, not 'id'
    protected $primaryKey = 'type';
    public $incrementing = false; // Indicate that the primary key is not auto-incrementing
    protected $keyType = 'string'; // Indicate that the primary key is a string

    public $timestamps = true; // Assuming created_at and updated_at

    protected $fillable = [
        'type',
        'prefix',
        'last_number',
    ];

    protected $casts = [
        'last_number' => 'integer',
    ];

    /**
     * Generates the next sequential number for a given type.
     * This is an example of a helper method you might add to the model.
     *
     * @param string $type The type of sequence (e.g., 'invoice', 'purchase_order')
     * @return string The generated sequential number (e.g., 'INV-000001')
     * @throws \Exception If the sequence type is not found.
     */
    public static function generateNextNumber(string $type): string
    {
        // Use a pessimistic lock to prevent race conditions during number generation
        return Capsule::transaction(function () use ($type) {
            $sequence = self::lockForUpdate()->find($type);

            if (!$sequence) {
                throw new \Exception("Sequence type '{$type}' not found.");
            }

            $sequence->last_number++;
            $sequence->save();

            // Format the number with leading zeros
            $formattedNumber = str_pad($sequence->last_number, 6, '0', STR_PAD_LEFT);

            return $sequence->prefix . $formattedNumber;
        });
    }
}