<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampanhaIniciativa extends Model
{
    protected $table = 'campanha_iniciativas';

    protected $fillable = ['campanha_id', 'ficha_id', 'user_id', 'nome', 'dado', 'bonus', 'resultado'];

    protected $casts = [
        'dado'      => 'integer',
        'bonus'     => 'integer',
        'resultado' => 'integer',
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
