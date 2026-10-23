<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Faz um model "pertencer" à conta logada, sem mexer nos controllers:
 *
 *  - ao criar: grava user_id = usuário logado (sempre, ignorando o que vier do form);
 *  - ao consultar: só devolve registros do usuário logado
 *    (Personagem::findOrFail($id) de outra conta vira 404);
 *  - ao atualizar: user_id não pode ser trocado.
 *
 * Visitante (sem login) não enxerga nada. No console (artisan, seeders)
 * o filtro não se aplica.
 */
trait PertenceAoUsuario
{
    protected static function bootPertenceAoUsuario(): void
    {
        static::addGlobalScope('dono', function (Builder $query) {
            $tabela = $query->getModel()->getTable();

            if (Auth::check()) {
                $query->where($tabela . '.user_id', Auth::id());
            } elseif (! app()->runningInConsole()) {
                $query->whereRaw('1 = 0');
            }
        });

        static::creating(function ($model) {
            if (Auth::check()) {
                $model->user_id = Auth::id();
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('user_id')) {
                $model->user_id = $model->getOriginal('user_id');
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}