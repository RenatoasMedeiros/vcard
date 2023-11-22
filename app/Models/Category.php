<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'vcard',
        'type',
        'name',
        'custom_options',
        'custom_data',
    ];

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'category_id', 'id');
    }

}