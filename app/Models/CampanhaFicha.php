<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampanhaFicha extends Model
{
    protected $table = 'campanha_fichas';

    protected $fillable = ['campanha_id', 'ficha_id', 'user_id', 'foto_path'];

    public function campanha(): BelongsTo
    {
        return $this->belongsTo(Campanha::class, 'campanha_id');
    }

    // ficha_id não tem FK no banco (a tabela de fichas é de outro módulo), mas a relação funciona normalmente.
    // withoutGlobalScopes: o mestre precisa enxergar fichas que pertencem a outros jogadores.
    public function ficha(): BelongsTo
    {
        return $this->belongsTo(Personagem::class, 'ficha_id')->withoutGlobalScopes();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
