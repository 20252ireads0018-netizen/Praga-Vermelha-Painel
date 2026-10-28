<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Peça de Pela Dama criada por um jogador: nome, símbolo e regras de movimento. */
class PelaDamaPeca extends Model
{
    protected $table = 'pela_dama_pecas';

    protected $fillable = ['campanha_id', 'user_id', 'nome', 'simbolo', 'regras'];

    protected $casts = ['regras' => 'array'];

    /** Id do tipo dentro da partida (os tipos padrão usam nomes: rei, dama…). */
    public function tipoId(): string
    {
        return 'c' . $this->id;
    }

    public function paraJogo(): array
    {
        return ['nome' => $this->nome, 'simbolo' => $this->simbolo, 'especial' => null, 'regras' => $this->regras ?: []];
    }
}
