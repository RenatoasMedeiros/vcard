<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;

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
        'category_id',
        'description',
        'custom_options',
        'custom_data',
    ];

    public function vCard()
    {
        return $this->belongsTo(VCard::class, 'vcard', 'phone_number');
    }

    public function pairedTransaction()
    {
        return $this->hasOne(Transaction::class, 'id', 'pair_transaction');
    }

}