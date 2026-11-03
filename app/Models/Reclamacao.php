<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Reclamação ou sugestão registrada no Reclame aqui do painel. */
class Reclamacao extends Model
{
    protected $table = 'reclamacoes';

    protected $fillable = ['user_id', 'categoria', 'titulo', 'texto', 'pagina', 'status', 'resposta', 'respondida_em'];

    protected $casts = ['respondida_em' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
