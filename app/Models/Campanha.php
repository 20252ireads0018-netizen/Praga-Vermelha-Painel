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
        'trilha'        => 'array',
        'sessao_ativa'  => 'boolean',
        'sessao_inicio' => 'datetime',
        'som_em'        => 'datetime',
    ];

    private static ?bool $colunaCassino = null;

    /** A coluna campanha_jogadores.cassino só existe depois do SQL da migração 2026_11_03_000028. */
    public static function temControleCassino(): bool
    {
        if (self::$colunaCassino === null) {
            try {
                self::$colunaCassino = \App\Support\Esquema::coluna('campanha_jogadores', 'cassino');
            } catch (\Throwable $e) {
                self::$colunaCassino = false;
            }
        }

        return self::$colunaCassino;
    }

    /** Só para os testes, que criam a tabela depois do primeiro acesso. */
    public static function esquecerControleCassino(): void
    {
        self::$colunaCassino = null;
    }

    /** O cassino (minijogos) está aberto para $userId? O mestre sempre; os jogadores, se o mestre não fechou para eles. */
    public function cassinoLiberado(?int $userId): bool
    {
        if ($userId === null || !$this->temMembro($userId)) {
            return false;
        }
        if ($this->ehMestre($userId) || !self::temControleCassino()) {
            return true;
        }

        return (bool) ($this->participantes()->where('user_id', $userId)->where('status', 'ativo')->value('cassino') ?? true);
    }

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