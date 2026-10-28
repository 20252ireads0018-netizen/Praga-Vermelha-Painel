<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Partida de Gladio ou Pela Dama dentro de uma campanha. */
class JogoPartida extends Model
{
    protected $table = 'jogo_partidas';

    protected $fillable = ['campanha_id', 'jogo', 'jogador1_id', 'jogador2_id', 'convidado_id', 'aposta', 'status', 'vencedor_id', 'config', 'estado', 'versao'];

    protected $casts = ['config' => 'array', 'estado' => 'array', 'aposta' => 'integer', 'versao' => 'integer'];

    public function jogador1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jogador1_id');
    }

    public function jogador2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jogador2_id');
    }

    public function convidado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'convidado_id');
    }

    /** Lado do usuário: Gladio 'a'/'b'; Pela Dama 'b' (brancas)/'p' (pretas); null para quem só assiste. */
    public function ladoDe(?int $uid): ?string
    {
        if ($uid === null) {
            return null;
        }
        if ((int) $this->jogador1_id === $uid) {
            return $this->jogo === 'gladio' ? 'a' : 'b';
        }
        if ((int) $this->jogador2_id === $uid) {
            return $this->jogo === 'gladio' ? 'b' : 'p';
        }

        return null;
    }
}
