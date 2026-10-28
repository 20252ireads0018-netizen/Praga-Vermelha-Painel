<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Deck de Gladio montado por um jogador com as cartas da campanha. */
class GladioDeck extends Model
{
    public const MIN = 15;
    public const MAX = 60;
    public const COPIAS = 4;

    protected $table = 'gladio_decks';

    protected $fillable = ['campanha_id', 'user_id', 'nome', 'cartas'];

    protected $casts = ['cartas' => 'array'];

    /** Lista de ids (uma entrada por cópia), só com cartas que ainda existem no catálogo. */
    public function lista(array $catalogo): array
    {
        $ids = [];
        foreach ((array) $this->cartas as $id => $qtd) {
            if (isset($catalogo[(string) $id])) {
                for ($n = 0; $n < (int) $qtd; $n++) {
                    $ids[] = (string) $id;
                }
            }
        }

        return $ids;
    }

    public function paraLista(): array
    {
        return ['id' => $this->id, 'nome' => $this->nome, 'cartas' => (object) ($this->cartas ?: [])];
    }
}
