<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Carta de Gladio criada pelo mestre da campanha. */
class GladioCarta extends Model
{
    protected $table = 'gladio_cartas';

    protected $fillable = ['campanha_id', 'nome', 'tipo', 'custo', 'ataque', 'vida', 'efeito', 'valor', 'palavras', 'subtipos', 'limite', 'descricao', 'imagem'];

    protected $casts = ['custo' => 'integer', 'ataque' => 'integer', 'vida' => 'integer', 'valor' => 'integer'];

    /** Formato usado pelo motor do jogo (sem a imagem, que é pesada). */
    public function paraJogo(): array
    {
        return [
            'nome' => $this->nome, 'tipo' => $this->tipo, 'custo' => $this->custo, 'ataque' => $this->ataque,
            'vida' => $this->vida, 'efeito' => $this->efeito, 'valor' => $this->valor, 'descricao' => (string) $this->descricao,
            'palavras' => (string) ($this->palavras ?? ''), // Sobrepujar, Ímpeto (separadas por vírgula)
            'subtipos' => (string) ($this->subtipos ?? ''), // hexomante, aluno, kuro… (separados por vírgula)
            'limite'   => $this->limite === null ? null : (int) $this->limite, // banlist: cópias por deck (0 = banida, null = livre)
        ];
    }
}
