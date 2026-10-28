<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campanha extends Model
{
    protected $table = 'campanhas';

    protected $fillable = ['nome', 'descricao', 'mestre_id', 'banner_path', 'chave_maldicoes'];

    protected $casts = [
        'sessao_ativa'  => 'boolean',
        'sessao_inicio' => 'datetime',
        'som_em'        => 'datetime',
    ];

    public function mestre(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mestre_id');
    }

    public function participantes(): HasMany
    {
        return $this->hasMany(CampanhaJogador::class, 'campanha_id');
    }

    public function vinculosFichas(): HasMany
    {
        return $this->hasMany(CampanhaFicha::class, 'campanha_id');
    }

    public function sons(): HasMany
    {
        return $this->hasMany(CampanhaSom::class, 'campanha_id');
    }

    public function mesas(): HasMany
    {
        return $this->hasMany(Mesa::class, 'campanha_id');
    }

    public function ehMestre(?int $userId): bool
    {
        return $userId !== null && (int) $this->mestre_id === $userId;
    }

    /** Mestre ou jogador com convite aceito. */
    public function temMembro(?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        return $this->ehMestre($userId)
            || $this->participantes()->where('user_id', $userId)->where('status', 'ativo')->exists();
    }
}