<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mesa extends Model
{
    protected $table = 'mesas';

    protected $fillable = ['nome', 'estado'];

    protected $casts = [
        'estado' => 'array',
    ];
}