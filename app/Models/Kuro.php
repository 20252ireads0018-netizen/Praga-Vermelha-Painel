<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kuro de uma campanha (regras em config/kuros.php; a ficha calculada sai de public/js/campanha-kuros.js). */
class Kuro extends Model
{
    protected $table = 'kuros';

    protected $fillable = ['campanha_id', 'nome', 'dados', 'pv_atual', 'notas'];

    protected $casts = ['dados' => 'array', 'pv_atual' => 'integer'];

    public function campanha(): BelongsTo
    {
        return $this->belongsTo(Campanha::class);
    }

    public function dono(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** O que a página recebe. */
    public function paraTela(): array
    {
        return [
            'id'       => (int) $this->id,
            'nome'     => (string) $this->nome,
            'dados'    => $this->dados ?: (object) [],
            'pv_atual' => $this->pv_atual,
            'notas'    => (string) ($this->notas ?? ''),
            'dono'     => $this->user_id ? (int) $this->user_id : null,
        ];
    }
}
