<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\VCard as Authenticatable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class VCard extends model //implements Authenticatable
{
    use SoftDeletes, HasApiTokens, HasFactory, Notifiable;

    protected $table = 'vcards';
    protected $primaryKey = 'phone_number';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'phone_number',
        'name',
        'email',
        'photo_url',
        'password',
        'confirmation_code',
        'blocked',
        'balance',
        'max_debit',
        'custom_options',
        'custom_data',
        'pin'
    ];

    protected $casts = [
        'balance' => 'float', // Change to the actual type
        'max_debit' => 'float', // Change to the actual type
        // Add other fields as needed
    ];
    

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'vcard', 'phone_number');
    }

    public function pairedTransactions()
    {
        return $this->hasMany(Transaction::class, 'pair_vcard', 'phone_number');
    }

    public function categories()
    {
        return $this->hasMany(Category::class, 'vcard', 'phone_number');
    }
}