<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampanhaCatalogo extends Model
{
    protected $table = 'campanha_catalogos';

    protected $fillable = ['campanha_id', 'user_id', 'maldicao_slug', 'texto', 'pontuacao', 'detalhes'];

    protected $casts = [
        'pontuacao' => 'integer',
        'detalhes'  => 'array',
    ];

    public function campanha(): BelongsTo
    {
        return $this->belongsTo(Campanha::class, 'campanha_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
