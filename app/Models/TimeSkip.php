<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Time skip de uma campanha: regras montadas pelo mestre (contas em App\Support\TimeSkipCalc). */
class TimeSkip extends Model
{
    public const STATUS = ['rascunho', 'aberto', 'fechado'];

    protected $table = 'time_skips';

    protected $fillable = ['campanha_id', 'nome', 'status', 'config'];

    protected $casts = ['config' => 'array'];

    public function campanha(): BelongsTo
    {
        return $this->belongsTo(Campanha::class);
    }

    public function respostas(): HasMany
    {
        return $this->hasMany(TimeSkipResposta::class);
    }

    public function paraTela(): array
    {
        return [
            'id'     => (int) $this->id,
            'nome'   => (string) $this->nome,
            'status' => (string) $this->status,
            'config' => $this->config ?: (object) [],
        ];
    }
}
