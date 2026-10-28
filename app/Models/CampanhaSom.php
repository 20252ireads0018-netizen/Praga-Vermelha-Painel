<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampanhaSom extends Model
{
    protected $table = 'campanha_sons';

    protected $fillable = ['campanha_id', 'user_id', 'nome', 'arquivo', 'mime', 'tamanho'];

    public function campanha(): BelongsTo
    {
        return $this->belongsTo(Campanha::class, 'campanha_id');
    }
}