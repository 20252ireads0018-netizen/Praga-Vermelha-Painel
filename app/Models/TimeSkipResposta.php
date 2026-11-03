<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Escolhas de um jogador num time skip, para uma ficha (ficha_id 0: personagem novo, sem ficha). */
class TimeSkipResposta extends Model
{
    protected $table = 'time_skip_respostas';

    protected $fillable = ['time_skip_id', 'user_id', 'ficha_id', 'dados'];

    protected $casts = ['dados' => 'array', 'ficha_id' => 'integer', 'user_id' => 'integer'];

    public function timeSkip(): BelongsTo
    {
        return $this->belongsTo(TimeSkip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
