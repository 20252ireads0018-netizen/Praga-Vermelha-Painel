<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Peça de Pela Dama criada por um membro: nome, símbolo, regras de movimento e, se quiser, uma foto (arquivo em
 * storage/app/pela_dama/pecas, sem coluna no banco). As peças do mestre valem para todos da campanha.
 */
class PelaDamaPeca extends Model
{
    protected $table = 'pela_dama_pecas';

    protected $fillable = ['campanha_id', 'user_id', 'nome', 'simbolo', 'regras'];

    protected $casts = ['regras' => 'array'];

    public function campanha(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Campanha::class);
    }

    /** Id do tipo dentro da partida (os tipos padrão usam nomes: rei, dama…). */
    public function tipoId(): string
    {
        return 'c' . $this->id;
    }

    public function fotoCaminho(): string
    {
        return 'pela_dama/pecas/' . $this->id . '.jpg';
    }

    /** Endereço da foto (com a versão, para o navegador não guardar a antiga) ou null sem foto. */
    public function fotoUrl(): ?string
    {
        $disco = Storage::disk('local');
        if (!$this->id || !$disco->exists($this->fotoCaminho())) {
            return null;
        }

        return route('campanhas.jogos.pecas.foto', [$this->campanha_id, $this->id]) . '?v=' . $disco->lastModified($this->fotoCaminho());
    }

    public function paraJogo(): array
    {
        return ['nome' => $this->nome, 'simbolo' => $this->simbolo, 'especial' => null, 'regras' => $this->regras ?: [], 'foto' => $this->fotoUrl()];
    }
}
