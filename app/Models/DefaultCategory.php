<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DefaultCategory extends Model
{
    protected $fillable = [
        'type',
        'name',
        'custom_options',
        'custom_data',
    ];

    // Additional relationships or methods can be added as needed
}