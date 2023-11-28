<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;

    protected $touches = ['vCard']; // This ensures that the vCard's timestamp is also updated when a related transaction is created

    
    protected $fillable = [
        'vcard',
        'date',
        'datetime',
        'type',
        'value',
        'old_balance',
        'new_balance',
        'payment_type',
        'payment_reference',
        'pair_transaction',
        'pair_vcard',
        'description',
        'custom_options',
        'custom_data',
    ];
    
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            $transaction->date = now()->toDateString();
            $transaction->datetime = now();
        });
    }

    public function vCard()
    {
        return $this->belongsTo(VCard::class, 'vcard', 'phone_number');
    }

    public function pairedTransaction()
    {
        return $this->hasOne(Transaction::class, 'id', 'pair_transaction');
    }

    // Add this relationship to link a transaction to a category
    public function category()
    {
        return $this->belongsTo(Category::class);
    }


}