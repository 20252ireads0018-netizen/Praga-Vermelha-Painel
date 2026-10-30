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

    private static ?bool $temComandante = null;

    /** A coluna comandante_id só existe depois do SQL da migração 2026_10_29_000014. */
    public static function temComandante(): bool
    {
        return self::$temComandante ??= \Illuminate\Support\Facades\Schema::hasColumn('gladio_decks', 'comandante_id');
    }

    protected $casts = ['cartas' => 'array'];

    /** Cópias permitidas da carta num deck: o limite da banlist do mestre (0 = banida) ou COPIAS. */
    public static function copiasDe(array $carta): int
    {
        $lim = $carta['limite'] ?? null;

        return $lim === null || $lim === '' ? self::COPIAS : max(0, min(self::COPIAS, (int) $lim));
    }

    /** Lista de ids (uma entrada por cópia), só com cartas que ainda existem no catálogo. */
    public function lista(array $catalogo): array
    {
        $ids = [];
        foreach ((array) $this->cartas as $id => $qtd) {
            if (isset($catalogo[(string) $id])) {
                // a banlist vale também para decks montados antes dela: banida sai, limitada fica no limite
                for ($n = 0; $n < min((int) $qtd, self::copiasDe($catalogo[(string) $id])); $n++) {
                    $ids[] = (string) $id;
                }
            }
        }

        return $ids;
    }

    public function paraLista(): array
    {
        return ['id' => $this->id, 'nome' => $this->nome, 'cartas' => (object) ($this->cartas ?: []),
                'comandante_id' => self::temComandante() && $this->comandante_id ? (int) $this->comandante_id : null];
    }
}
